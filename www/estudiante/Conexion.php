<?php
class Conexion {
    public static function conectar() {
        try {
            $conexion = new PDO("mysql:host=mysql;dbname=cetecpro;charset=utf8", "root", "CLAVE12345+");
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conexion;
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
}
?>