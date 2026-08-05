<?php
require_once __DIR__ . "/../config/Conexion.php";

class AccesoModel {

    public static function registrar(int $usuarioId, string $usuario, string $tipo): void {
        $db = Conexion::conectar();
        $stmt = $db->prepare("INSERT INTO accesos (usuario_id, usuario, tipo) VALUES (?, ?, ?)");
        $stmt->execute([$usuarioId, $usuario, $tipo]);
    }

    public static function obtenerTodos(int $limite = 300): array {
        $db = Conexion::conectar();
        $stmt = $db->prepare("SELECT usuario, tipo, fecha_hora FROM accesos ORDER BY fecha_hora DESC LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
