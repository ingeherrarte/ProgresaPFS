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
        'foto_boleta' => 'Foto de la boleta',
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
                efectivo = ?, chpropio = ?, chotrobanco = ? , responsable = ?, foto_boleta = ?
             WHERE nodeposito = ?"
        );
        $stmt->execute([
            $datos['fechadep'], $datos['cuenta'], $datos['banco'], $datos['correspondiente'],
            $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'], $datos['responsable'],
            $datos['foto_boleta'], $nodeposito,
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

    // ── Cuadre boletas ↔ Cierre del Día ───────────────────────────────
    // Lo que entró en caja un día (efectivo + cheques de los recibos no
    // anulados) debe salir al banco en boletas cuyo "Correspondiente a"
    // sea ese mismo día. Se comparan efectivo + cheques juntos porque en la
    // práctica los cheques recibidos se depositan en la misma boleta y a
    // veces se anotan como efectivo; comparar solo efectivo marcaría
    // descuadres que no existen.

    public const TOLERANCIA_CUADRE = 0.005;

    public static function cuadreDia(PDO $db, string $fecha): array {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(efectivo), 0) AS efectivo, COALESCE(SUM(cheque), 0) AS cheque
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 0"
        );
        $stmt->execute([$fecha]);
        $cierre = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            "SELECT nodeposito, fechadep, banco, efectivo, chpropio, chotrobanco, responsable, foto_boleta
             FROM depositos WHERE correspondiente = ? ORDER BY fechadep, horaregistro"
        );
        $stmt->execute([$fecha]);
        $boletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cierreTotal = (float)$cierre['efectivo'] + (float)$cierre['cheque'];
        $depositado = 0.0;
        foreach ($boletas as $b) {
            $depositado += (float)$b['efectivo'] + (float)$b['chpropio'] + (float)$b['chotrobanco'];
        }
        $diferencia = round($cierreTotal - $depositado, 2);

        return [
            'fecha' => $fecha,
            'cierre_efectivo' => (float)$cierre['efectivo'],
            'cierre_cheque' => (float)$cierre['cheque'],
            'cierre_total' => $cierreTotal,
            'depositado' => $depositado,
            // > 0: falta depositar; < 0: se depositó de más (o la fecha está mal)
            'diferencia' => $diferencia,
            'cuadra' => abs($diferencia) < self::TOLERANCIA_CUADRE,
            'boletas' => $boletas,
        ];
    }

    // Días del rango donde caja y boletas no coinciden. Incluye días con
    // boletas pero sin cierre (típico de un "Correspondiente a" mal puesto).
    // MySQL no tiene FULL OUTER JOIN: se arma la lista de días con UNION y
    // se suma cada lado por separado.
    public static function diasDescuadrados(PDO $db, string $desde, string $hasta): array {
        $stmt = $db->prepare(
            "SELECT d.dia,
                    COALESCE(c.total, 0) AS cierre_total,
                    COALESCE(b.total, 0) AS depositado,
                    COALESCE(b.cantidad, 0) AS boletas
             FROM (
                SELECT DATE(horaregistro) AS dia FROM recibospfs
                 WHERE anulado = 0 AND horaregistro >= ? AND horaregistro < DATE_ADD(?, INTERVAL 1 DAY)
                UNION
                SELECT correspondiente FROM depositos WHERE correspondiente BETWEEN ? AND ?
             ) d
             LEFT JOIN (
                SELECT DATE(horaregistro) AS dia, SUM(efectivo + cheque) AS total
                  FROM recibospfs
                 WHERE anulado = 0 AND horaregistro >= ? AND horaregistro < DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY DATE(horaregistro)
             ) c ON c.dia = d.dia
             LEFT JOIN (
                SELECT correspondiente AS dia, SUM(efectivo + chpropio + chotrobanco) AS total, COUNT(*) AS cantidad
                  FROM depositos WHERE correspondiente BETWEEN ? AND ?
                 GROUP BY correspondiente
             ) b ON b.dia = d.dia
             WHERE ABS(COALESCE(c.total, 0) - COALESCE(b.total, 0)) >= ?
             ORDER BY d.dia DESC"
        );
        $stmt->execute([$desde, $hasta, $desde, $hasta, $desde, $hasta, $desde, $hasta, self::TOLERANCIA_CUADRE]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['diferencia'] = round((float)$f['cierre_total'] - (float)$f['depositado'], 2);
        }
        unset($f);
        return $filas;
    }
}
?>
