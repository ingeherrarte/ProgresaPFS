<?php
require_once "models/UsuarioModel.php";
require_once "views/UsuariosView.php";
require_once __DIR__ . "/../helpers/Auth.php";

class UsuariosController {

    private function validar(array $post): array {
        $errores = [];

        $usuario = trim($post['usuario'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $usuario)) {
            $errores[] = "El usuario debe tener entre 3 y 30 caracteres (letras, números, punto, guion o guion bajo).";
        }

        if (trim($post['nombre_completo'] ?? '') === '') {
            $errores[] = "El nombre completo es obligatorio.";
        }

        $password = $post['password'] ?? '';
        if (strlen($password) < 8) {
            $errores[] = "La contraseña debe tener al menos 8 caracteres.";
        }

        if ($password !== ($post['confirmar_password'] ?? '')) {
            $errores[] = "Las contraseñas no coinciden.";
        }

        return $errores;
    }

    private function validarCambioPassword(array $post): array {
        $errores = [];

        if (trim($post['password_actual'] ?? '') === '') {
            $errores[] = "Debe ingresar su contraseña actual.";
        }

        $nueva = $post['password_nueva'] ?? '';
        if (strlen($nueva) < 8) {
            $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        }

        if ($nueva !== ($post['confirmar_password_nueva'] ?? '')) {
            $errores[] = "Las contraseñas nuevas no coinciden.";
        }

        return $errores;
    }

    // Solo un usuario ya autenticado puede registrar nuevos usuarios;
    // no existe registro público en este sistema interno.
    public function handle($action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'password':
                UsuariosView::mostrarCambiarPassword();
                break;

            case 'cambiarPassword':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    header("Location: usuarios.php?action=password");
                    exit;
                }

                $errores = $this->validarCambioPassword($_POST);
                $cuenta = UsuarioModel::obtenerPorId(Auth::usuarioId());

                if (!$cuenta) {
                    // La cuenta fue desactivada/eliminada mientras había sesión activa.
                    Auth::cerrarSesion();
                    header("Location: login.php");
                    exit;
                }

                if (empty($errores) && !password_verify($_POST['password_actual'], $cuenta['password_hash'])) {
                    $errores[] = "La contraseña actual no es correcta.";
                }

                if (!empty($errores)) {
                    UsuariosView::mostrarCambiarPassword($errores);
                    break;
                }

                UsuarioModel::cambiarPassword($cuenta['id'], $_POST['password_nueva']);
                UsuariosView::mostrarCambiarPassword([], "Contraseña actualizada correctamente.");
                break;

            case 'crear':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    header("Location: usuarios.php");
                    exit;
                }

                $errores = $this->validar($_POST);

                if (empty($errores)) {
                    $resultado = UsuarioModel::crear(
                        trim($_POST['usuario']),
                        $_POST['password'],
                        trim($_POST['nombre_completo'])
                    );

                    if ($resultado === true) {
                        header("Location: usuarios.php?msg=creado");
                        exit;
                    } elseif ($resultado === 'duplicado') {
                        $errores[] = "Ya existe un usuario con ese nombre.";
                    } else {
                        $errores[] = "No se pudo crear el usuario. Intente de nuevo.";
                    }
                }

                UsuariosView::mostrar($errores, $_POST, UsuarioModel::obtenerTodos());
                break;

            case 'listar':
            default:
                $mensaje = ($_GET['msg'] ?? '') === 'creado' ? 'Usuario creado correctamente.' : null;
                UsuariosView::mostrar([], [], UsuarioModel::obtenerTodos(), $mensaje);
                break;
        }
    }
}
?>
