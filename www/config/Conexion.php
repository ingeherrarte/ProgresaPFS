<?php
require_once __DIR__ . "/env.php";
cargarEnv(__DIR__ . "/../.env");

class Conexion {
    public static function conectar() {
        $host = getenv('DB_HOST') ?: 'mysql';
        $dbname = getenv('DB_NAME') ?: 'cetecpro';
        $usuario = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS');

        if ($password === false) {
            die("Error de conexión: falta configurar DB_PASS (ver .env.example).");
        }

        try {
            $conexion = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $password);
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Defensa además del --default-time-zone del contenedor mysql:
            // sin esto, NOW()/CURRENT_TIMESTAMP quedan en UTC y los registros
            // de la tarde/noche caen bajo la fecha del día siguiente.
            $conexion->exec("SET time_zone = '-06:00'");
            return $conexion;
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
}
?>