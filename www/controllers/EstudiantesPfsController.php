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

            case 'ficha':
                Auth::requerirSesion();
                $this->ficha();
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

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: estudiantespfs.php?action=form");
            exit;
        }

        $db = Conexion::conectar();
        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        $errores = EstudiantesPfsModel::validar($_POST, $cursos);

        if (!empty($errores)) {
            EstudiantesPfsView::mostrarFormularioAlta($errores, $_POST, $cursos);
            return;
        }

        $datos = EstudiantesPfsModel::datosDesdePost($_POST);
        $datos['usuario'] = Auth::usuarioActual();

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

    // Ficha completa de un estudiante, pensada para imprimir (vínculo "Ficha"
    // en Buscar Estudiante, junto a "Usar en recibo").
    private function ficha() {
        $carnet = $_GET['carnet'] ?? '';
        if (!ctype_digit($carnet)) {
            header("Location: estudiantespfs.php");
            exit;
        }

        $db = Conexion::conectar();
        $estudiante = EstudiantesPfsModel::obtenerPorId($db, (int)$carnet);
        if (!$estudiante) {
            header("Location: estudiantespfs.php");
            exit;
        }

        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        $nombreCurso = $cursos[$estudiante['codcurso']] ?? 'No asignado';

        EstudiantesPfsView::mostrarFicha($estudiante, $nombreCurso);
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
