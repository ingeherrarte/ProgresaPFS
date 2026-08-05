<?php

// Lógica compartida para adjuntar una foto (boleta de depósito, comprobante
// de transferencia, etc.) a un registro. Centralizada porque las mismas
// reglas de seguridad (tipo real de archivo, tamaño, nombre generado en
// servidor, no repetir la misma imagen) deben mantenerse iguales en todos
// los formularios que suben imágenes; duplicarlas por controlador arriesga
// que una corrección se aplique en un lugar y se olvide en otro.
class SubidaImagen {

    private const TIPOS_IMAGEN = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    // Si el POST completo supera post_max_size, PHP vacía $_POST y $_FILES
    // sin marcar ningún error de archivo individual: sin este chequeo previo,
    // guardar() interpretaría eso como "no se adjuntó nada" y el registro se
    // guardaría descartando el archivo en silencio.
    public static function postTruncado(): bool {
        return empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    }

    // Valida y guarda una imagen subida por $_FILES. Devuelve [nombreGuardado, error]:
    // si no se adjuntó nada, ambos son null.
    //
    // El tipo se detecta con getimagesize() sobre el contenido real del
    // archivo, no por la extensión ni el Content-Type que manda el navegador
    // (ambos falsificables), y el nombre final lo genera esta función en vez
    // de reutilizar el nombre original subido.
    //
    // Si se pasa $db, además rechaza la imagen si su contenido (hash SHA-256)
    // ya está registrado en `imagenes_subidas` -- la misma boleta/comprobante
    // no se puede adjuntar dos veces, sea en Depósitos o en Recibos. El
    // registro se marca "usado" recién al guardar el archivo; si el registro
    // padre termina fallando, el llamador debe invocar eliminar() con el
    // mismo $db para liberar el hash y permitir reintentar con la misma foto.
    public static function guardar(
        array $archivo,
        string $carpetaDestino,
        string $prefijo,
        int $maxBytes,
        ?PDO $db = null,
        string $contexto = '',
        string $referencia = ''
    ): array {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        $limiteMb = round($maxBytes / (1024 * 1024), 1);

        if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, "El archivo supera el tamaño máximo permitido ({$limiteMb} MB)."];
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return [null, "No se pudo subir el archivo. Intente de nuevo."];
        }
        if (($archivo['size'] ?? 0) > $maxBytes) {
            return [null, "El archivo supera el tamaño máximo permitido ({$limiteMb} MB)."];
        }

        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false || !isset(self::TIPOS_IMAGEN[$info[2]])) {
            return [null, "El archivo debe ser una imagen JPG, PNG o WEBP."];
        }

        $hash = null;
        if ($db !== null) {
            $hash = hash_file('sha256', $archivo['tmp_name']);
            $existente = self::buscarPorHash($db, $hash);
            if ($existente) {
                return [null, "Esta imagen ya fue adjuntada anteriormente (referencia: {$existente['referencia']}). No se puede subir el mismo archivo más de una vez."];
            }
        }

        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0755, true);
        }

        $prefijoLimpio = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) ?: 'archivo';
        $nombre = $prefijoLimpio . '_' . bin2hex(random_bytes(8)) . '.' . self::TIPOS_IMAGEN[$info[2]];

        if (!move_uploaded_file($archivo['tmp_name'], $carpetaDestino . '/' . $nombre)) {
            return [null, "No se pudo guardar el archivo. Intente de nuevo."];
        }

        if ($db !== null) {
            try {
                self::registrarHash($db, $hash, $nombre, $contexto, $referencia);
            } catch (PDOException $e) {
                // Otra solicitud registró el mismo hash mientras se procesaba ésta.
                @unlink($carpetaDestino . '/' . $nombre);
                return [null, "Esta imagen ya fue adjuntada anteriormente. No se puede subir el mismo archivo más de una vez."];
            }
        }

        return [$nombre, null];
    }

    private static function buscarPorHash(PDO $db, string $hash): ?array {
        $stmt = $db->prepare("SELECT contexto, referencia FROM imagenes_subidas WHERE hash = ?");
        $stmt->execute([$hash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    private static function registrarHash(PDO $db, string $hash, string $archivo, string $contexto, string $referencia): void {
        $stmt = $db->prepare("INSERT INTO imagenes_subidas (hash, archivo, contexto, referencia) VALUES (?, ?, ?, ?)");
        $stmt->execute([$hash, $archivo, $contexto, $referencia]);
    }

    // Borra el archivo del disco y, si se pasa $db, libera también el hash
    // registrado para que la misma imagen pueda volver a intentarse (p. ej.
    // cuando el registro padre falla por otro motivo y el usuario reintenta
    // el formulario con la misma foto adjunta).
    public static function eliminar(string $carpetaDestino, ?string $nombre, ?PDO $db = null): void {
        if (!$nombre) {
            return;
        }
        @unlink($carpetaDestino . '/' . $nombre);
        if ($db !== null) {
            $stmt = $db->prepare("DELETE FROM imagenes_subidas WHERE archivo = ?");
            $stmt->execute([$nombre]);
        }
    }
}
?>
