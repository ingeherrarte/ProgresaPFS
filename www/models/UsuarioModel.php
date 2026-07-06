<?php
require_once __DIR__ . "/../config/Conexion.php";

class UsuarioModel {

    public static function verificar(string $usuario, string $password): ?array {
        $db = Conexion::conectar();
        $stmt = $db->prepare("SELECT id, usuario, password_hash, nombre_completo FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila && password_verify($password, $fila['password_hash'])) {
            unset($fila['password_hash']);
            return $fila;
        }
        return null;
    }

    // Devuelve true, 'duplicado' (usuario ya existe) o false (error de BD).
    public static function crear(string $usuario, string $password, string $nombreCompleto): bool|string {
        $db = Conexion::conectar();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (usuario, password_hash, nombre_completo) VALUES (?, ?, ?)");

        try {
            $stmt->execute([$usuario, $hash, $nombreCompleto]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'duplicado';
            }
            error_log("Error al crear usuario: " . $e->getMessage());
            return false;
        }
    }

    public static function obtenerTodos(): array {
        $db = Conexion::conectar();
        $stmt = $db->query("SELECT id, usuario, nombre_completo, activo, creado_en FROM usuarios ORDER BY creado_en DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorId(int $id): ?array {
        $db = Conexion::conectar();
        $stmt = $db->prepare("SELECT id, usuario, password_hash, nombre_completo FROM usuarios WHERE id = ? AND activo = 1");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public static function cambiarPassword(int $id, string $nuevaPassword): bool {
        $db = Conexion::conectar();
        $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$hash, $id]);
    }
}
?>
