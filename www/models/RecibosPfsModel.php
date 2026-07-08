<?php
require_once __DIR__ . "/../config/Conexion.php";

class RecibosPfsModel {

    // Lista blanca de columnas buscables. El nombre de columna se interpola
    // en el SQL, pero solo puede ser una de estas claves fijas: nunca un
    // valor tomado del request (a diferencia del buscador legacy, que
    // aceptaba cualquier nombre de columna vía SHOW FIELDS + $_POST).
    private static array $columnasBuscables = [
        'numero'  => 'Número de recibo',
        'carne'   => 'Carné',
        'detalle' => 'Detalle',
        'usuario' => 'Registrado por',
        'banco'   => 'Banco',
    ];

    public static function columnasBuscables(): array {
        return self::$columnasBuscables;
    }

    public static function buscar(PDO $db, string $campo, string $palabra): array {
        if (!array_key_exists($campo, self::$columnasBuscables)) {
            throw new InvalidArgumentException("Campo de búsqueda no permitido: $campo");
        }

        $sql = "SELECT * FROM recibospfs WHERE `$campo` LIKE ? ORDER BY horaregistro DESC LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $palabra . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function siguienteNumero(PDO $db): int {
        return (int)$db->query("SELECT COALESCE(MAX(numero),0)+1 FROM recibospfs")->fetchColumn();
    }

    // El correlativo se calcula con MAX(numero)+1 (tabla legacy MyISAM sin
    // soporte de autoincrement/transacciones). Ante una colisión (numero es
    // PRIMARY KEY) se reintenta con un nuevo correlativo en vez de fallar.
    public static function insertar(PDO $db, array $datos): array {
        $sql = "INSERT INTO recibospfs
            (numero, carne, fechadelpago, primero, segundo, tercero, cuarto,
             mesquepaga, mensualidad, inscripcion, otro, detalle,
             efectivo, deposito, nodeposito, fechadep, cheque, nocheque, banco,
             usuario, horaregistro, aleatorio)
            VALUES
            (?, ?, ?, 0, 0, 0, 0,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?,
             ?, NOW(), ?)";

        $intentos = 0;
        do {
            $intentos++;
            $numero = self::siguienteNumero($db);
            $aleatorio = random_int(1000, 9999);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute([
                    $numero, $datos['carne'], $datos['fechadelpago'],
                    $datos['mesquepaga'], $datos['mensualidad'], $datos['inscripcion'], $datos['otro'], $datos['detalle'],
                    $datos['efectivo'], $datos['deposito'], $datos['nodeposito'], $datos['fechadep'],
                    $datos['cheque'], $datos['nocheque'], $datos['banco'],
                    $datos['usuario'], $aleatorio,
                ]);
                return ['numero' => $numero, 'aleatorio' => $aleatorio];
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $intentos < 5) {
                    continue;
                }
                throw $e;
            }
        } while ($intentos < 5);

        throw new RuntimeException("No se pudo generar un número de recibo único.");
    }

    public static function obtenerPorNumero(PDO $db, int $numero): ?array {
        $stmt = $db->prepare("SELECT * FROM recibospfs WHERE numero = ?");
        $stmt->execute([$numero]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public static function estadisticasHoy(PDO $db): array {
        $stmt = $db->query(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = CURDATE() AND anulado = 0"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function estadisticasMes(PDO $db): array {
        $stmt = $db->query(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs
             WHERE YEAR(horaregistro) = YEAR(CURDATE()) AND MONTH(horaregistro) = MONTH(CURDATE()) AND anulado = 0"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Marca un recibo como anulado (nunca se borra ni se altera el detalle
    // original). Devuelve false si el número no existe o ya estaba anulado.
    public static function anular(PDO $db, int $numero, string $motivo, string $usuario): bool {
        $stmt = $db->prepare(
            "UPDATE recibospfs
             SET anulado = 1, motivo_anulacion = ?, anulado_por = ?, fecha_anulacion = NOW()
             WHERE numero = ? AND anulado = 0"
        );
        $stmt->execute([$motivo, $usuario, $numero]);
        return $stmt->rowCount() > 0;
    }

    // Detalle + resumen por concepto de un día específico (reemplazo de
    // cierrehoy_new.php). Se usa DATE(horaregistro) = :fecha con un
    // parámetro enlazado, en vez de interpolar la fecha en el SQL.
    public static function cierreDia(PDO $db, string $fecha): array {
        $stmtDetalle = $db->prepare(
            "SELECT * FROM recibospfs WHERE DATE(horaregistro) = ? ORDER BY horaregistro"
        );
        $stmtDetalle->execute([$fecha]);

        // Los recibos anulados se siguen mostrando en el detalle (para que
        // quede visible qué pasó ese día), pero no cuentan en las sumas.
        $stmtResumen = $db->prepare(
            "SELECT
                COALESCE(SUM(efectivo), 0) AS efectivo,
                COALESCE(SUM(deposito), 0) AS deposito,
                COALESCE(SUM(cheque), 0) AS cheque,
                COALESCE(SUM(inscripcion), 0) AS inscripcion,
                COALESCE(SUM(mensualidad), 0) AS mensualidad,
                COALESCE(SUM(otro), 0) AS otro,
                COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 0"
        );
        $stmtResumen->execute([$fecha]);

        // Cuenta y monto de los anulados del día, para explicar en el
        // resumen por qué el total no coincide con la suma cruda del detalle.
        $stmtAnulados = $db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 1"
        );
        $stmtAnulados->execute([$fecha]);

        return [
            'detalle' => $stmtDetalle->fetchAll(PDO::FETCH_ASSOC),
            'resumen' => $stmtResumen->fetch(PDO::FETCH_ASSOC),
            'anulados' => $stmtAnulados->fetch(PDO::FETCH_ASSOC),
        ];
    }

    // Totales por mes de un año (reemplazo de la tabla de 12 meses de
    // cierre_anio_moderno.php). El detalle día-por-día de un mes puntual
    // ya lo cubre reporte_recibospfs.php, así que no se duplica aquí.
    public static function cierreAnioPorMes(PDO $db, int $anio): array {
        $stmt = $db->prepare(
            "SELECT MONTH(horaregistro) AS mes,
                    COALESCE(SUM(efectivo), 0) AS efectivo,
                    COALESCE(SUM(deposito), 0) AS deposito,
                    COALESCE(SUM(cheque), 0) AS cheque
             FROM recibospfs
             WHERE YEAR(horaregistro) = ? AND anulado = 0
             GROUP BY MONTH(horaregistro)"
        );
        $stmt->execute([$anio]);

        $porMes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porMes[(int)$fila['mes']] = $fila;
        }

        $resultado = [];
        for ($m = 1; $m <= 12; $m++) {
            $resultado[$m] = $porMes[$m] ?? ['efectivo' => 0, 'deposito' => 0, 'cheque' => 0];
        }
        return $resultado;
    }

    public static function anuladosDelAnio(PDO $db, int $anio): array {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE YEAR(horaregistro) = ? AND anulado = 1"
        );
        $stmt->execute([$anio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
