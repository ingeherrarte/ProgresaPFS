<?php
require_once "models/AsistenciaModel.php";
require_once "models/GruposModel.php";
require_once "views/AsistenciaView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class AsistenciaController {

    public function handle(string $action) {
        Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);

        switch ($action) {
            case 'guardar':
                $this->guardar();
                break;
            case 'historial':
                $this->historial();
                break;
            case 'form':
            default:
                $this->form();
                break;
        }
    }

    private function form() {
        $db = Conexion::conectar();
        $grupos = GruposModel::listar($db);
        $grupoId = (int)($_GET['grupo_id'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $grupo = $grupoId ? GruposModel::obtenerPorId($db, $grupoId) : null;
        $miembros = $grupo ? AsistenciaModel::paraFecha($db, $grupoId, $fecha) : [];

        $mensaje = null;
        if ($grupo && ($_GET['msg'] ?? '') === 'guardado') {
            $n = (int)($_GET['n'] ?? 0);
            $mensaje = "Asistencia guardada: $n estudiante(s).";
        }

        AsistenciaView::mostrarForm($grupos, $grupo, $fecha, $miembros, $mensaje);
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: asistencia.php");
            exit;
        }

        $db = Conexion::conectar();
        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';

        if (!GruposModel::obtenerPorId($db, $grupoId) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            header("Location: asistencia.php");
            exit;
        }

        $estados = $_POST['estado'] ?? [];
        $n = AsistenciaModel::guardar($db, $grupoId, $fecha, $estados, Auth::usuarioActual());

        header("Location: asistencia.php?grupo_id=$grupoId&fecha=$fecha&msg=guardado&n=$n");
        exit;
    }

    private function historial() {
        $db = Conexion::conectar();
        $grupoId = (int)($_GET['grupo_id'] ?? 0);
        $grupo = GruposModel::obtenerPorId($db, $grupoId);
        if (!$grupo) {
            header("Location: asistencia.php");
            exit;
        }

        AsistenciaView::mostrarHistorial($grupo, AsistenciaModel::historialFechas($db, $grupoId));
    }
}
?>
