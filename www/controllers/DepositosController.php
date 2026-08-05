<?php
require_once "models/DepositosModel.php";
require_once "views/DepositosView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class DepositosController {

    public function handle(string $action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'guardar':
                $this->guardar();
                break;

            case 'form':
            default:
                $this->form();
                break;
        }
    }

    private function form(array $errores = [], array $data = []) {
        $db = Conexion::conectar();
        $recientes = DepositosModel::obtenerRecientes($db);
        $mensaje = (empty($errores) && ($_GET['msg'] ?? '') === 'creado') ? 'Depósito guardado correctamente.' : null;
        DepositosView::mostrarFormulario($errores, $data, $recientes, $mensaje);
    }

    // Reemplaza a procesadeposito.php, que estaba completamente roto: llamaba
    // a cambiaf_a_mysql() sin que la función existiera (fatal error en
    // cualquier PHP >= 7) y nunca leía $_POST['fechadep'].
    private function validar(array $post): array {
        $errores = [];

        $nodeposito = trim($post['nodeposito'] ?? '');
        if ($nodeposito === '' || strlen($nodeposito) > 11) {
            $errores[] = "El número de depósito es obligatorio (máximo 11 caracteres).";
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['fechadep'] ?? '')) {
            $errores[] = "La fecha de depósito no es válida.";
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['correspondiente'] ?? '')) {
            $errores[] = "La fecha a la que corresponde el depósito no es válida.";
        }

        $cuenta = trim($post['cuenta'] ?? '');
        if (!array_key_exists($cuenta, DepositosModel::cuentas())) {
            $errores[] = "Seleccione una cuenta válida.";
        }

        foreach (['efectivo', 'chpropio', 'chotrobanco'] as $campo) {
            $valor = $post[$campo] ?? '0';
            if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
                $errores[] = "El campo '$campo' debe ser un monto numérico válido.";
            }
        }

        $total = (float)($post['efectivo'] ?? 0) + (float)($post['chpropio'] ?? 0) + (float)($post['chotrobanco'] ?? 0);
        if ($total <= 0) {
            $errores[] = "Debe ingresar al menos un monto en efectivo o cheque.";
        }

        if (trim($post['responsable'] ?? '') === '') {
            $errores[] = "El responsable es obligatorio.";
        }

        return $errores;
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: depositos.php");
            exit;
        }

        $errores = $this->validar($_POST);

        if (!empty($errores)) {
            $this->form($errores, $_POST);
            return;
        }

        $cuenta = trim($_POST['cuenta']);
        $datos = [
            'nodeposito' => trim($_POST['nodeposito']),
            'fechadep' => $_POST['fechadep'],
            'cuenta' => $cuenta,
            'banco' => DepositosModel::bancoDeCuenta($cuenta),
            'correspondiente' => $_POST['correspondiente'],
            'efectivo' => (float)$_POST['efectivo'],
            'chpropio' => (float)$_POST['chpropio'],
            'chotrobanco' => (float)$_POST['chotrobanco'],
            'responsable' => trim($_POST['responsable']),
            'usuario' => Auth::usuarioActual(),
        ];

        $db = Conexion::conectar();
        $resultado = DepositosModel::crear($db, $datos);

        if ($resultado === 'duplicado') {
            $this->form(["Ya existe un depósito registrado con el número {$datos['nodeposito']}."], $_POST);
            return;
        }
        if ($resultado === false) {
            $this->form(["No se pudo guardar el depósito. Intente de nuevo."], $_POST);
            return;
        }

        header("Location: depositos.php?msg=creado");
        exit;
    }
}
?>
