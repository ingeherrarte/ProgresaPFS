<?php
require_once "models/RecibosPfsModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "views/RecibosPfsView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class RecibosPfsController {

    private array $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function handle($action) {
        switch ($action) {
            case 'buscarCarnet':
                Auth::requerirSesionJson();
                $this->buscarCarnet();
                break;

            case 'guardar':
                Auth::requerirSesion();
                $this->guardar();
                break;

            case 'ver':
                Auth::requerirSesion();
                $this->ver();
                break;

            case 'buscar':
                Auth::requerirSesion();
                $this->buscar();
                break;

            case 'form':
            default:
                Auth::requerirSesion();
                $data = [];
                // Permite precargar el carné al llegar desde el buscador de estudiantes.
                if (!empty($_GET['carnet']) && ctype_digit($_GET['carnet'])) {
                    $data['carnet'] = $_GET['carnet'];
                }
                RecibosPfsView::mostrarFormulario([], $data);
                break;
        }
    }

    private function buscarCarnet() {
        header('Content-Type: application/json; charset=utf-8');
        $carnet = $_GET['carnet'] ?? '';

        if (!ctype_digit($carnet)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Carné inválido.']);
            exit;
        }

        $db = Conexion::conectar();
        $estudiante = EstudiantesPfsModel::buscarPorCarnet($db, $carnet);

        if (!$estudiante) {
            echo json_encode(['ok' => false, 'mensaje' => 'No existe ningún estudiante con ese carné.']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'nombre' => trim($estudiante['nombre'] . ' ' . $estudiante['apellidos']),
            'curso' => $estudiante['nombrecurso'] ?? '(sin curso asignado)',
            'activo' => $estudiante['activo'] == 1,
        ]);
        exit;
    }

    private function mesNumero(string $nombre): int|false {
        return array_search(strtolower(trim($nombre)), $this->meses, true);
    }

    // Toda la validación se repite en servidor: la búsqueda de carné en el
    // formulario es solo UX, nunca la única fuente de verdad.
    private function validar(array $post, PDO $db, ?array &$estudiante): array {
        $errores = [];

        $carnet = trim($post['carnet'] ?? '');
        if (!ctype_digit($carnet)) {
            $errores[] = "El carné es obligatorio y debe ser numérico.";
        } else {
            $estudiante = EstudiantesPfsModel::buscarPorCarnet($db, $carnet);
            if (!$estudiante) {
                $errores[] = "No existe ningún estudiante registrado con el carné $carnet.";
            }
        }

        $diapago = (int)($post['diapago'] ?? 0);
        if ($diapago < 1 || $diapago > 31) {
            $errores[] = "El día de pago no es válido.";
        }

        $mespagoNum = $this->mesNumero($post['mespago'] ?? '');
        if ($mespagoNum === false) {
            $errores[] = "El mes de pago no es válido.";
        }

        $mesNum = $this->mesNumero($post['mes'] ?? '');
        if ($mesNum === false) {
            $errores[] = "El mes que paga no es válido.";
        }

        if ($mespagoNum !== false && $diapago >= 1 && $diapago <= 31
            && !checkdate($mespagoNum, $diapago, (int)date('Y'))) {
            $errores[] = "La combinación de día y mes de pago no es una fecha válida.";
        }

        foreach (['mensualidad', 'inscripcion', 'otro', 'efectivo', 'deposito', 'cheque'] as $campo) {
            $valor = $post[$campo] ?? '0';
            if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
                $errores[] = "El campo '$campo' debe ser un monto numérico válido.";
            }
        }

        $mensualidad = (float)($post['mensualidad'] ?? 0);
        $inscripcion = (float)($post['inscripcion'] ?? 0);
        $otro = (float)($post['otro'] ?? 0);
        $efectivo = (float)($post['efectivo'] ?? 0);
        $deposito = (float)($post['deposito'] ?? 0);
        $cheque = (float)($post['cheque'] ?? 0);

        $totalCargos = round($mensualidad + $inscripcion + $otro, 2);
        $totalPagado = round($efectivo + $deposito + $cheque, 2);

        if ($totalCargos <= 0) {
            $errores[] = "Debe ingresar al menos un monto en mensualidad, inscripción u otro.";
        }
        if (abs($totalCargos - $totalPagado) > 0.01) {
            $errores[] = "El total a cobrar (Q " . number_format($totalCargos, 2)
                . ") no coincide con el total pagado (Q " . number_format($totalPagado, 2) . ").";
        }

        if (trim($post['detalle'] ?? '') === '') {
            $errores[] = "El detalle del pago es obligatorio.";
        }

        if ($deposito > 0 && trim($post['nodeposito'] ?? '') === '') {
            $errores[] = "Debe indicar el número de depósito.";
        }
        if ($deposito > 0 && trim($post['fechadep'] ?? '') === '') {
            $errores[] = "Debe indicar la fecha de depósito.";
        }
        if ($cheque > 0 && trim($post['nocheque'] ?? '') === '') {
            $errores[] = "Debe indicar el número de cheque.";
        }
        if (($deposito > 0 || $cheque > 0) && trim($post['banco'] ?? '') === '') {
            $errores[] = "Debe indicar el banco de origen.";
        }

        return $errores;
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: recibospfs.php");
            exit;
        }

        $db = Conexion::conectar();
        $estudiante = null;
        $errores = $this->validar($_POST, $db, $estudiante);

        if (!empty($errores)) {
            RecibosPfsView::mostrarFormulario($errores, $_POST);
            return;
        }

        $mespagoNum = $this->mesNumero($_POST['mespago']);
        $mesNum = $this->mesNumero($_POST['mes']);
        $diapago = (int)$_POST['diapago'];
        $anioActual = (int)date('Y');
        $fechadelpago = sprintf('%04d-%02d-%02d', $anioActual, $mespagoNum, $diapago);

        $deposito = (float)($_POST['deposito'] ?? 0);
        $fechadep = ($deposito > 0 && !empty($_POST['fechadep']))
            ? $_POST['fechadep']
            : $fechadelpago;

        $datos = [
            'carne' => $estudiante['idestudiante'],
            'fechadelpago' => $fechadelpago,
            'mesquepaga' => $mesNum,
            'mensualidad' => (float)$_POST['mensualidad'],
            'inscripcion' => (float)$_POST['inscripcion'],
            'otro' => (float)$_POST['otro'],
            'detalle' => trim($_POST['detalle']),
            'efectivo' => (float)$_POST['efectivo'],
            'deposito' => $deposito,
            'nodeposito' => (int)($_POST['nodeposito'] ?? 0),
            'fechadep' => $fechadep,
            'cheque' => (float)$_POST['cheque'],
            'nocheque' => (int)($_POST['nocheque'] ?? 0),
            'banco' => trim($_POST['banco'] ?? ''),
            'usuario' => Auth::usuarioActual(),
        ];

        try {
            $resultado = RecibosPfsModel::insertar($db, $datos);
        } catch (Exception $e) {
            error_log("Error al guardar recibo PFS: " . $e->getMessage());
            RecibosPfsView::mostrarFormulario(
                ["No se pudo guardar el recibo. Intente de nuevo."],
                $_POST
            );
            return;
        }

        // Post/Redirect/Get: evita duplicar el recibo si el cajero recarga la página.
        header("Location: recibospfs.php?action=ver&numero=" . $resultado['numero']);
        exit;
    }

    private function ver() {
        $numero = (int)($_GET['numero'] ?? 0);
        $db = Conexion::conectar();
        $recibo = RecibosPfsModel::obtenerPorNumero($db, $numero);

        if (!$recibo) {
            header("Location: recibospfs.php");
            exit;
        }

        $estudiante = EstudiantesPfsModel::buscarPorCarnet($db, $recibo['carne']);
        RecibosPfsView::mostrarRecibo($recibo, $estudiante);
    }

    private function buscar() {
        $campo = $_GET['campo'] ?? '';
        $palabra = trim($_GET['palabra'] ?? '');
        $resultados = [];
        $error = null;
        $columnas = RecibosPfsModel::columnasBuscables();

        if ($campo !== '' || $palabra !== '') {
            if (!array_key_exists($campo, $columnas)) {
                $error = "Seleccione un campo de búsqueda válido.";
            } elseif ($palabra === '') {
                $error = "Ingrese un dato a buscar.";
            } else {
                $db = Conexion::conectar();
                $resultados = RecibosPfsModel::buscar($db, $campo, $palabra);
            }
        }

        RecibosPfsView::mostrarBuscar($columnas, $campo, $palabra, $resultados, $error);
    }
}
?>
