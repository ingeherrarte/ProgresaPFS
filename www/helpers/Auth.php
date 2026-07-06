<?php
class Auth {

    public static function iniciar(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function estaAutenticado(): bool {
        self::iniciar();
        return !empty($_SESSION['usuario_id']);
    }

    public static function requerirSesion(): void {
        self::iniciar();
        if (!self::estaAutenticado()) {
            $destino = urlencode($_SERVER['REQUEST_URI'] ?? 'recibospfs.php');
            header("Location: login.php?destino=$destino");
            exit;
        }
    }

    public static function requerirSesionJson(): void {
        self::iniciar();
        if (!self::estaAutenticado()) {
            header('Content-Type: application/json; charset=utf-8', true, 401);
            echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada. Vuelve a iniciar sesión.']);
            exit;
        }
    }

    public static function iniciarSesion(array $usuario): void {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    }

    public static function cerrarSesion(): void {
        self::iniciar();
        $_SESSION = [];
        session_destroy();
    }

    public static function usuarioActual(): string {
        self::iniciar();
        return $_SESSION['usuario'] ?? '';
    }

    public static function nombreActual(): string {
        self::iniciar();
        return $_SESSION['nombre_completo'] ?? '';
    }

    public static function usuarioId(): ?int {
        self::iniciar();
        return isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
    }
}
?>
