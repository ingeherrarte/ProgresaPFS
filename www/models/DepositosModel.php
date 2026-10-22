<?php
require_once __DIR__ . "/../config/Conexion.php";

class DepositosModel {

    // La columna `banco` existe en la tabla pero el script legacy nunca la
    // llenaba (el <select> solo listaba números de cuenta). Se deriva aquí
    // de una lista blanca fija en vez de aceptar el nombre del banco desde
    // el request.
    private static array $cuentas = [
        '2870011720' => 'BANTRAB',
        '3099203897' => 'BANRURAL',
    ];

    public static function cuentas(): array {
        return self::$cuentas;
    }

    public static function bancoDeCuenta(string $cuenta): ?string {
        return self::$cuentas[$cuenta] ?? null;
    }

    // Devuelve true, 'duplicado' (nodeposito ya existe) o false (error de BD).
    public static function crear(PDO $db, array $datos): bool|string {
        $sql = "INSERT INTO depositos
            (nodeposito, fechadep, cuenta, banco, correspondiente, efectivo, chpropio, chotrobanco, responsable, usuario, horaregistro, foto_boleta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $db->prepare($sql);

        try {
            $stmt->execute([
                $datos['nodeposito'], $datos['fechadep'], $datos['cuenta'], $datos['banco'],
                $datos['correspondiente'], $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'],
                $datos['responsable'], $datos['usuario'], $datos['foto_boleta'],
            ]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'duplicado';
            }
            error_log("Error al guardar depósito: " . $e->getMessage());
            return false;
        }
    }

    public static function obtenerRecientes(PDO $db, int $limite = 30): array {
        $stmt = $db->prepare("SELECT * FROM depositos ORDER BY horaregistro DESC LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorNumero(PDO $db, string $nodeposito): ?array {
        $stmt = $db->prepare("SELECT * FROM depositos WHERE nodeposito = ?");
        $stmt->execute([$nodeposito]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    // nodeposito NO está aquí a propósito: es la llave con la que se busca
    // el registro (igual que `numero` en recibos), no un campo editable —
    // si de verdad se digitó mal, se corrige borrando y volviendo a
    // capturar, para no perder la trazabilidad del historial (ver
    // historialEdiciones, que queda indexado por este número).
    private static array $camposEditables = [
        'fechadep' => 'Fecha de depósito',
        'cuenta' => 'Cuenta',
        'correspondiente' => 'Correspondiente a',
        'efectivo' => 'Efectivo',
        'chpropio' => 'Cheque propio banco',
        'chotrobanco' => 'Cheque otros bancos',
        'responsable' => 'Responsable',
    ];

    private const CAMPOS_MONTO = ['efectivo', 'chpropio', 'chotrobanco'];

    public static function camposEditables(): array {
        return self::$camposEditables;
    }

    public static function etiquetaCampo(string $campo): string {
        return self::$camposEditables[$campo] ?? $campo;
    }

    // Misma razón que RecibosPfsModel::normalizar(): la BD devuelve los
    // montos como "1900" y el formulario los manda como "1900.00" — son el
    // mismo valor y no deben quedar registrados como un cambio real.
    private static function normalizar(string $campo, $valor): string {
        if (in_array($campo, self::CAMPOS_MONTO, true)) {
            return number_format((float)$valor, 2, '.', '');
        }
        return trim((string)$valor);
    }

    // Deja una fila por campo modificado en depositos_ediciones (motivo +
    // usuario + antes/después), igual que RecibosPfsModel::actualizar().
    // Sin transacción que cubra ambas escrituras porque depositos es
    // MyISAM (legacy) y no soporta transacciones — se actualiza primero el
    // depósito y solo después se escribe la bitácora, para no registrar un
    // cambio que la BD rechazó. Devuelve la lista de cambios aplicados, o
    // [] si no hubo ninguno.
    public static function actualizar(PDO $db, string $nodeposito, array $datos, string $motivo, string $usuario): array {
        $actual = self::obtenerPorNumero($db, $nodeposito);
        if (!$actual) {
            return [];
        }

        $cambios = [];
        foreach (array_keys(self::$camposEditables) as $campo) {
            $antes = self::normalizar($campo, $actual[$campo]);
            $despues = self::normalizar($campo, $datos[$campo]);
            if ($antes !== $despues) {
                $cambios[] = ['campo' => $campo, 'anterior' => $antes, 'nuevo' => $despues];
            }
        }

        if (empty($cambios)) {
            return [];
        }

        $stmt = $db->prepare(
            "UPDATE depositos SET fechadep = ?, cuenta = ?, banco = ?, correspondiente = ?,
                efectivo = ?, chpropio = ?, chotrobanco = ? , responsable = ?
             WHERE nodeposito = ?"
        );
        $stmt->execute([
            $datos['fechadep'], $datos['cuenta'], $datos['banco'], $datos['correspondiente'],
            $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'], $datos['responsable'],
            $nodeposito,
        ]);

        if ($stmt->rowCount() === 0) {
            return [];
        }

        $log = $db->prepare(
            "INSERT INTO depositos_ediciones (nodeposito, campo, valor_anterior, valor_nuevo, motivo, usuario)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($cambios as $c) {
            $log->execute([$nodeposito, $c['campo'], $c['anterior'], $c['nuevo'], $motivo, $usuario]);
        }

        return $cambios;
    }

    public static function historialEdiciones(PDO $db, string $nodeposito): array {
        $stmt = $db->prepare(
            "SELECT campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM depositos_ediciones WHERE nodeposito = ? ORDER BY fecha DESC, id DESC"
        );
        $stmt->execute([$nodeposito]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // A diferencia de historialEdiciones() (un depósito a la vez), esto
    // es la bitácora completa: se muestra en "Editar Depósito" cuando
    // todavía no se ha buscado ningún número, para poder ver de un vistazo
    // qué depósitos se han corregido sin tener que adivinar cuál buscar.
    public static function edicionesRecientes(PDO $db, int $limite = 50): array {
        $stmt = $db->prepare(
            "SELECT nodeposito, campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM depositos_ediciones ORDER BY fecha DESC, id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mismas reglas que al crear (ver DepositosController::validar),
    // adaptadas a edición: aquí además es obligatorio el motivo, que queda
    // en la bitácora junto con el valor anterior de cada campo modificado.
    public static function validarEdicion(array $post): array {
        $errores = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['fechadep'] ?? '')) {
            $errores[] = "La fecha de depósito no es válida.";
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['correspondiente'] ?? '')) {
            $errores[] = "La fecha a la que corresponde el depósito no es válida.";
        }

        $cuenta = trim($post['cuenta'] ?? '');
        if (!array_key_exists($cuenta, self::$cuentas)) {
            $errores[] = "Seleccione una cuenta válida.";
        }

        foreach (self::CAMPOS_MONTO as $campo) {
            $valor = $post[$campo] ?? '0';
            if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
                $errores[] = "El campo '$campo' debe ser un monto numérico válido.";
            }
        }

        $total = (float)($post['efectivo'] ?? 0) + (float)($post['chpropio'] ?? 0) + (float)($post['chotrobanco'] ?? 0);
        if ($total <= 0) {
            $errores[] = "Debe ingresar al menos un monto en efectivo o cheque.";
        }

        if (trim($post['responsable'] ?? '') === '') {
            $errores[] = "El responsable es obligatorio.";
        }
        if (trim($post['motivo'] ?? '') === '') {
            $errores[] = "Debe indicar el motivo de la edición.";
        }

        return $errores;
    }
}
?>
