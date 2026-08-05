<?php

// Lógica compartida para adjuntar una foto (boleta de depósito, comprobante
// de transferencia, etc.) a un registro. Centralizada porque las mismas
// reglas de seguridad (tipo real de archivo, tamaño, nombre generado en
// servidor) deben mantenerse iguales en todos los formularios que suben
// imágenes; duplicarlas por controlador arriesga que una corrección se
// aplique en un lugar y se olvide en otro.
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
    public static function guardar(array $archivo, string $carpetaDestino, string $prefijo, int $maxBytes): array {
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

        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0755, true);
        }

        $prefijoLimpio = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) ?: 'archivo';
        $nombre = $prefijoLimpio . '_' . bin2hex(random_bytes(8)) . '.' . self::TIPOS_IMAGEN[$info[2]];

        if (!move_uploaded_file($archivo['tmp_name'], $carpetaDestino . '/' . $nombre)) {
            return [null, "No se pudo guardar el archivo. Intente de nuevo."];
        }

        return [$nombre, null];
    }

    public static function eliminar(string $carpetaDestino, ?string $nombre): void {
        if ($nombre) {
            @unlink($carpetaDestino . '/' . $nombre);
        }
    }
}
?>
