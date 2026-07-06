<?php
require_once "models/UsuarioModel.php";
require_once "views/LoginView.php";
require_once __DIR__ . "/../helpers/Auth.php";

class AuthController {

    private function destinoSeguro(?string $destino): string {
        // Solo se permite redirigir a un .php local, para evitar open-redirect.
        if ($destino && preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[^ ]*)?$/', $destino)) {
            return $destino;
        }
        return 'inicio.php';
    }

    public function handle($action) {
        switch ($action) {

            case 'autenticar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $usuario = trim($_POST['usuario'] ?? '');
                    $password = $_POST['password'] ?? '';
                    $destino = $this->destinoSeguro($_POST['destino'] ?? null);

                    $cuenta = UsuarioModel::verificar($usuario, $password);
                    if ($cuenta) {
                        Auth::iniciarSesion($cuenta);
                        header("Location: $destino");
                        exit;
                    }
                    LoginView::mostrar("Usuario o contraseña incorrectos.", $destino);
                }
                break;

            case 'logout':
                Auth::cerrarSesion();
                header("Location: login.php");
                exit;

            default:
                if (Auth::estaAutenticado()) {
                    header("Location: " . $this->destinoSeguro($_GET['destino'] ?? null));
                    exit;
                }
                LoginView::mostrar(null, $this->destinoSeguro($_GET['destino'] ?? null));
                break;
        }
    }
}
?>
