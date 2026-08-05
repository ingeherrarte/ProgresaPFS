<?php
require_once "models/EstudiantesPfsModel.php";
require_once "views/EstudiantesPfsView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class EstudiantesPfsController {

    private const POR_PAGINA = 20;

    public function handle($action) {
        switch ($action) {
            case 'buscarJson':
                Auth::requerirSesionJson();
                $this->buscarJson();
                break;

            case 'form':
                Auth::requerirSesion();
                $this->form();
                break;

            case 'guardar':
                Auth::requerirSesion();
                $this->guardar();
                break;

            case 'buscar':
            default:
                Auth::requerirSesion();
                $this->buscar();
                break;
        }
    }

    private function form() {
        $db = Conexion::conectar();
        $mensaje = null;
        if (($_GET['msg'] ?? '') === 'creado' && !empty($_GET['carnet'])) {
            $mensaje = "Estudiante registrado con carné " . (int)$_GET['carnet'] . ".";
        }
        EstudiantesPfsView::mostrarFormularioAlta([], [], EstudiantesPfsModel::obtenerCursos($db), $mensaje);
    }

    // Toda la validación vive en servidor; el <select> de curso viene de la
    // tabla real (ver EstudiantesPfsModel::obtenerCursos), así que el valor
    // recibido solo puede ser un id que ya existe en esa lista.
    private function validarAlta(array $post, array $cursos): array {
        $errores = [];

        if (trim($post['nombre'] ?? '') === '') {
            $errores[] = "El nombre es obligatorio.";
        }
        if (trim($post['apellidos'] ?? '') === '') {
            $errores[] = "Los apellidos son obligatorios.";
        }

        $nacimiento = trim($post['nacimiento'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nacimiento, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            $errores[] = "La fecha de nacimiento no es válida.";
        }

        $codcurso = (int)($post['codcurso'] ?? 0);
        if (!array_key_exists($codcurso, $cursos)) {
            $errores[] = "Seleccione un curso válido.";
        }

        $plan = (int)($post['plan'] ?? 0);
        if (!array_key_exists($plan, EstudiantesPfsModel::planes())) {
            $errores[] = "Seleccione un plan/día válido.";
        }

        $jornada = (int)($post['jornada'] ?? 0);
        if (!array_key_exists($jornada, EstudiantesPfsModel::jornadas())) {
            $errores[] = "Seleccione una jornada válida.";
        }

        $dpi = trim($post['dpi'] ?? '');
        if ($dpi !== '' && !preg_match('/^\d{13}$/', $dpi)) {
            $errores[] = "El DPI debe tener 13 dígitos.";
        }

        $email = trim($post['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El email no es válido.";
        }

        foreach (['telefonomovil', 'telefonocasa', 'telefonotrabajo', 'ptelefono', 'ptelefonotrabajo', 'mtelefono', 'mtelefonotrabajo'] as $campoTel) {
            $valor = trim($post[$campoTel] ?? '');
            if ($valor !== '' && !preg_match('/^\d{8}$/', $valor)) {
                $errores[] = "El campo teléfono ($campoTel) debe tener 8 dígitos.";
            }
        }

        $ultimoanio = trim($post['ultimoanio'] ?? '');
        if ($ultimoanio !== '' && (!preg_match('/^\d{4}$/', $ultimoanio) || (int)$ultimoanio < 1980 || (int)$ultimoanio > (int)date('Y') + 1)) {
            $errores[] = "El último año cursado no es válido.";
        }

        if (trim($post['enteradopor'] ?? '') === '') {
            $errores[] = "Seleccione cómo se enteró del centro.";
        }

        return $errores;
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: estudiantespfs.php?action=form");
            exit;
        }

        $db = Conexion::conectar();
        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        $errores = $this->validarAlta($_POST, $cursos);

        if (!empty($errores)) {
            EstudiantesPfsView::mostrarFormularioAlta($errores, $_POST, $cursos);
            return;
        }

        $ultimoanio = trim($_POST['ultimoanio'] ?? '');

        $datos = [
            'nombre' => trim($_POST['nombre']),
            'apellidos' => trim($_POST['apellidos']),
            'nacimiento' => $_POST['nacimiento'],
            'codcurso' => (int)$_POST['codcurso'],
            'plan' => (int)$_POST['plan'],
            'jornada' => (int)$_POST['jornada'],
            'dpi' => trim($_POST['dpi'] ?? ''),
            'cedula' => trim($_POST['cedula'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefonomovil' => trim($_POST['telefonomovil'] ?? ''),
            'telefonocasa' => trim($_POST['telefonocasa'] ?? ''),
            'telefonotrabajo' => trim($_POST['telefonotrabajo'] ?? ''),
            'ultimoanio' => $ultimoanio !== '' ? $ultimoanio : '0000',
            'establecimiento' => trim($_POST['establecimiento'] ?? ''),
            'pnombre' => trim($_POST['pnombre'] ?? ''),
            'papellidos' => trim($_POST['papellidos'] ?? ''),
            'pcedula' => trim($_POST['pcedula'] ?? ''),
            'ptelefono' => trim($_POST['ptelefono'] ?? ''),
            'ptrabajo' => trim($_POST['ptrabajo'] ?? ''),
            'ptelefonotrabajo' => trim($_POST['ptelefonotrabajo'] ?? ''),
            'pdirecciont' => trim($_POST['pdirecciont'] ?? ''),
            'mnombre' => trim($_POST['mnombre'] ?? ''),
            'mapellidos' => trim($_POST['mapellidos'] ?? ''),
            'mcedula' => trim($_POST['mcedula'] ?? ''),
            'mtelefono' => trim($_POST['mtelefono'] ?? ''),
            'mtrabajo' => trim($_POST['mtrabajo'] ?? ''),
            'mtelefonotrabajo' => trim($_POST['mtelefonotrabajo'] ?? ''),
            'mdirecciont' => trim($_POST['mdirecciont'] ?? ''),
            'usuario' => Auth::usuarioActual(),
            'enteradopor' => trim($_POST['enteradopor']),
            'observacion' => trim($_POST['observacion'] ?? ''),
        ];

        try {
            $id = EstudiantesPfsModel::crear($db, $datos);
        } catch (Exception $e) {
            error_log("Error al registrar estudiante: " . $e->getMessage());
            EstudiantesPfsView::mostrarFormularioAlta(
                ["No se pudo registrar el estudiante. Intente de nuevo."],
                $_POST,
                $cursos
            );
            return;
        }

        // Post/Redirect/Get: evita duplicar el alta si el cajero recarga la página.
        header("Location: estudiantespfs.php?action=form&msg=creado&carnet=$id");
        exit;
    }

    private function buscar() {
        $termino = trim($_GET['q'] ?? '');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $resultado = ['total' => 0, 'filas' => []];
        if ($termino !== '') {
            $db = Conexion::conectar();
            $resultado = EstudiantesPfsModel::buscarPorNombre($db, $termino, $pagina, self::POR_PAGINA);
        }

        $totalPaginas = $resultado['total'] > 0 ? (int)ceil($resultado['total'] / self::POR_PAGINA) : 0;

        EstudiantesPfsView::mostrarBuscar($termino, $resultado['filas'], $pagina, $totalPaginas, $resultado['total']);
    }

    // Usado por la búsqueda en vivo (JS) mientras el usuario escribe: siempre
    // devuelve la primera página, ordenada por carné descendente (los
    // estudiantes inscritos más recientemente primero).
    private function buscarJson() {
        header('Content-Type: application/json; charset=utf-8');
        $termino = trim($_GET['q'] ?? '');

        if (mb_strlen($termino) < 3) {
            echo json_encode(['total' => 0, 'filas' => []]);
            exit;
        }

        $db = Conexion::conectar();
        $resultado = EstudiantesPfsModel::buscarPorNombre($db, $termino, 1, self::POR_PAGINA);

        $filas = array_map(function ($f) {
            return [
                'idestudiante' => $f['idestudiante'],
                'nombreCompleto' => trim($f['nombre'] . ' ' . $f['apellidos']),
                'nombrecurso' => $f['nombrecurso'],
                'planNombre' => EstudiantesPfsModel::nombrePlan($f['plan']),
                'jornadaNombre' => EstudiantesPfsModel::nombreJornada($f['jornada']),
                'telefonomovil' => $f['telefonomovil'],
                'activo' => (int)$f['activo'],
            ];
        }, $resultado['filas']);

        echo json_encode(['total' => $resultado['total'], 'filas' => $filas]);
        exit;
    }
}
?>
