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
            (nodeposito, fechadep, cuenta, banco, correspondiente, efectivo, chpropio, chotrobanco, responsable, usuario, horaregistro)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);

        try {
            $stmt->execute([
                $datos['nodeposito'], $datos['fechadep'], $datos['cuenta'], $datos['banco'],
                $datos['correspondiente'], $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'],
                $datos['responsable'], $datos['usuario'],
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
}
?>
