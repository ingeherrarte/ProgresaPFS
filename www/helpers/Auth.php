<?php
require_once __DIR__ . "/../models/AccesoModel.php";

class Auth {

    const ROL_USUARIO = 'usuario';
    const ROL_EDITOR = 'editor';
    const ROL_ADMINISTRADOR = 'administrador';

    // 15 minutos sin actividad cierran la sesión automáticamente.
    const TIEMPO_INACTIVIDAD_SEGUNDOS = 900;

    public static function iniciar(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function estaAutenticado(): bool {
        self::iniciar();
        return !empty($_SESSION['usuario_id']);
    }

    private static function sesionExpiradaPorInactividad(): bool {
        return isset($_SESSION['ultima_actividad'])
            && (time() - $_SESSION['ultima_actividad']) > self::TIEMPO_INACTIVIDAD_SEGUNDOS;
    }

    // Registra la "salida" en el historial de accesos antes de destruir la
    // sesión, igual que un logout manual, para que quede el mismo rastro.
    private static function expirarPorInactividad(): void {
        AccesoModel::registrar((int)$_SESSION['usuario_id'], $_SESSION['usuario'], 'salida');
        $_SESSION = [];
        session_destroy();
    }

    public static function requerirSesion(): void {
        self::iniciar();
        if (self::estaAutenticado() && self::sesionExpiradaPorInactividad()) {
            self::expirarPorInactividad();
            $destino = urlencode($_SERVER['REQUEST_URI'] ?? 'recibospfs.php');
            header("Location: login.php?destino=$destino&motivo=inactividad");
            exit;
        }
        if (!self::estaAutenticado()) {
            $destino = urlencode($_SERVER['REQUEST_URI'] ?? 'recibospfs.php');
            header("Location: login.php?destino=$destino");
            exit;
        }
        $_SESSION['ultima_actividad'] = time();
    }

    public static function requerirSesionJson(): void {
        self::iniciar();
        if (self::estaAutenticado() && self::sesionExpiradaPorInactividad()) {
            self::expirarPorInactividad();
        }
        if (!self::estaAutenticado()) {
            header('Content-Type: application/json; charset=utf-8', true, 401);
            echo json_encode(['ok' => false, 'mensaje' => 'Sesión expirada. Vuelve a iniciar sesión.']);
            exit;
        }
        $_SESSION['ultima_actividad'] = time();
    }

    // Redirige a inicio.php si la sesión no tiene uno de los roles permitidos.
    // Debe llamarse después de requerirSesion() (o incluirla implícitamente aquí).
    public static function requerirRol(array $roles): void {
        self::requerirSesion();
        if (!in_array(self::rolActual(), $roles, true)) {
            header("Location: inicio.php?error=permiso");
            exit;
        }
    }

    public static function iniciarSesion(array $usuario): void {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario'] = $usuario['usuario'];
        $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
        $_SESSION['rol'] = $usuario['rol'] ?? self::ROL_EDITOR;
        $_SESSION['ultima_actividad'] = time();
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

    public static function rolActual(): string {
        self::iniciar();
        return $_SESSION['rol'] ?? self::ROL_EDITOR;
    }

    public static function esAdministrador(): bool {
        return self::rolActual() === self::ROL_ADMINISTRADOR;
    }

    // Solo el rol "usuario" tiene restringida la anulación de recibos.
    public static function puedeAnularRecibos(): bool {
        return self::rolActual() !== self::ROL_USUARIO;
    }

    // Igual que la anulación de recibos: disponible para editor y
    // administrador, no para el rol "usuario".
    public static function puedeEditarEstudiantes(): bool {
        return self::rolActual() !== self::ROL_USUARIO;
    }
}
?>
