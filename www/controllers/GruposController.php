<?php
require_once "models/GruposModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "views/GruposView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class GruposController {

    public function handle(string $action) {
        // Mismo nivel que anular recibos / editar estudiante: operación
        // diaria de académico, no exclusiva de administrador.
        Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);

        switch ($action) {
            case 'ver':
                $this->ver();
                break;
            case 'crear':
                $this->crear();
                break;
            case 'agregar':
                $this->agregar();
                break;
            case 'quitar':
                $this->quitar();
                break;
            case 'form':
            default:
                $this->form();
                break;
        }
    }

    private function form() {
        $db = Conexion::conectar();
        GruposView::mostrarForm(EstudiantesPfsModel::obtenerCursos($db), GruposModel::listar($db));
    }

    private function crear() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: grupos.php");
            exit;
        }

        $db = Conexion::conectar();
        $codcurso = (int)($_POST['codcurso'] ?? 0);
        $plan = (int)($_POST['plan'] ?? 0);
        $jornada = (int)($_POST['jornada'] ?? 0);

        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        if (!array_key_exists($codcurso, $cursos)
            || !array_key_exists($plan, EstudiantesPfsModel::planes())
            || !array_key_exists($jornada, EstudiantesPfsModel::jornadas())
        ) {
            header("Location: grupos.php?error=combinacion");
            exit;
        }

        $existente = GruposModel::obtener($db, $codcurso, $plan, $jornada);
        $grupoId = $existente ? (int)$existente['id'] : GruposModel::crear($db, $codcurso, $plan, $jornada, Auth::usuarioActual());

        header("Location: grupos.php?action=ver&id=$grupoId");
        exit;
    }

    private function ver() {
        $db = Conexion::conectar();
        $grupoId = (int)($_GET['id'] ?? 0);
        $grupo = GruposModel::obtenerPorId($db, $grupoId);
        if (!$grupo) {
            header("Location: grupos.php");
            exit;
        }

        $termino = trim($_GET['q'] ?? '');
        $resultados = [];
        if ($termino !== '') {
            $resultados = EstudiantesPfsModel::buscarPorNombre($db, $termino, 1, 20)['filas'];
        }

        $mensaje = null;
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'agregado') {
            $mensaje = "Estudiante agregado al grupo.";
        } elseif ($msg === 'reactivado') {
            $mensaje = "El estudiante ya había estado en este grupo — se reactivó.";
        } elseif ($msg === 'ya_existe') {
            $mensaje = "Ese estudiante ya es miembro de este grupo.";
        } elseif ($msg === 'quitado') {
            $mensaje = "Estudiante quitado del grupo.";
        }

        GruposView::mostrarGrupo($grupo, GruposModel::miembros($db, $grupoId), $termino, $resultados, $mensaje);
    }

    private function agregar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: grupos.php");
            exit;
        }

        $db = Conexion::conectar();
        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $idestudiante = (int)($_POST['idestudiante'] ?? 0);

        if (!GruposModel::obtenerPorId($db, $grupoId) || !EstudiantesPfsModel::obtenerPorId($db, $idestudiante)) {
            header("Location: grupos.php");
            exit;
        }

        $resultado = GruposModel::agregarMiembro($db, $grupoId, $idestudiante, Auth::usuarioActual());
        $q = urlencode(trim($_POST['q'] ?? ''));
        header("Location: grupos.php?action=ver&id=$grupoId&msg=$resultado&q=$q");
        exit;
    }

    private function quitar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: grupos.php");
            exit;
        }

        $db = Conexion::conectar();
        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $idestudiante = (int)($_POST['idestudiante'] ?? 0);

        GruposModel::quitarMiembro($db, $grupoId, $idestudiante, Auth::usuarioActual());
        header("Location: grupos.php?action=ver&id=$grupoId&msg=quitado");
        exit;
    }
}
?>
