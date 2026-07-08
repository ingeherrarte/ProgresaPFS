<?php
require_once "models/RecibosPfsModel.php";
require_once "models/UsuarioModel.php";
require_once "views/AdminView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class AdminController {

    public function handle(string $action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'anular':
                $this->anularBuscar();
                break;

            case 'anular_confirmar':
                $this->anularConfirmar();
                break;

            case 'form':
            default:
                AdminView::mostrarMenu();
                break;
        }
    }

    private function anularBuscar() {
        $numero = trim($_GET['numero'] ?? '');
        $recibo = null;
        $errores = [];

        if ($numero !== '') {
            if (!ctype_digit($numero)) {
                $errores[] = "Número de recibo inválido.";
            } else {
                $db = Conexion::conectar();
                $recibo = RecibosPfsModel::obtenerPorNumero($db, (int)$numero);
                if (!$recibo) {
                    $errores[] = "No existe ningún recibo con ese número.";
                } elseif ($recibo['anulado']) {
                    $errores[] = "Este recibo ya fue anulado el "
                        . date('d/m/Y H:i', strtotime($recibo['fecha_anulacion'])) . ".";
                }
            }
        }

        AdminView::mostrarAnular($numero, $recibo, $errores);
    }

    // La contraseña se vuelve a pedir aquí (re-autenticación) para que
    // anular un recibo no sea posible con solo dejar la sesión abierta;
    // tiene que ser la persona con la contraseña quien lo confirme.
    private function anularConfirmar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin.php?action=anular");
            exit;
        }

        $numero = (int)($_POST['numero'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $password = $_POST['password_actual'] ?? '';

        $db = Conexion::conectar();
        $recibo = RecibosPfsModel::obtenerPorNumero($db, $numero);

        if (!$recibo) {
            header("Location: admin.php?action=anular");
            exit;
        }

        $errores = [];
        if ($recibo['anulado']) {
            $errores[] = "Este recibo ya fue anulado.";
        }
        if ($motivo === '') {
            $errores[] = "Debe indicar el motivo de la anulación.";
        }
        if (!UsuarioModel::verificar(Auth::usuarioActual(), $password)) {
            $errores[] = "La contraseña no es correcta.";
        }

        if (!empty($errores)) {
            AdminView::mostrarAnular((string)$numero, $recibo, $errores);
            return;
        }

        RecibosPfsModel::anular($db, $numero, $motivo, Auth::usuarioActual());

        header("Location: recibospfs.php?action=ver&numero=$numero");
        exit;
    }
}
?>
