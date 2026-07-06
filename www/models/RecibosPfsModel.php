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
}
?>
