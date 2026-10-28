<?php
require_once "models/NotasModel.php";
require_once "models/GruposModel.php";
require_once "views/NotasView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class NotasController {

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

    private function periodoActual(): array {
        return [(int)($_GET['anio'] ?? date('Y')), (int)($_GET['mes'] ?? date('n'))];
    }

    private function form() {
        $db = Conexion::conectar();
        $grupos = GruposModel::listar($db);
        $grupoId = (int)($_GET['grupo_id'] ?? 0);
        [$anio, $mes] = $this->periodoActual();
        if ($mes < 1 || $mes > 12) {
            $mes = (int)date('n');
        }

        $grupo = $grupoId ? GruposModel::obtenerPorId($db, $grupoId) : null;
        $miembros = $grupo ? NotasModel::paraPeriodo($db, $grupoId, $anio, $mes) : [];

        $mensaje = null;
        $errores = [];
        if ($grupo && ($_GET['msg'] ?? '') === 'guardado') {
            $n = (int)($_GET['n'] ?? 0);
            $mensaje = "Notas guardadas: $n estudiante(s).";
        }

        NotasView::mostrarForm($grupos, $grupo, $anio, $mes, $miembros, $mensaje, $errores);
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: notas.php");
            exit;
        }

        $db = Conexion::conectar();
        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $anio = (int)($_POST['anio'] ?? 0);
        $mes = (int)($_POST['mes'] ?? 0);

        $grupo = GruposModel::obtenerPorId($db, $grupoId);
        if (!$grupo || $mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2999) {
            header("Location: notas.php");
            exit;
        }

        $notasPost = $_POST['nota'] ?? [];
        $resultado = NotasModel::guardar($db, $grupoId, $anio, $mes, $notasPost, Auth::usuarioActual());

        if (!empty($resultado['errores'])) {
            $miembros = NotasModel::paraPeriodo($db, $grupoId, $anio, $mes);
            // Conserva lo que el usuario tecleó (incluyendo lo inválido) para
            // que no tenga que volver a escribir todo el formulario.
            foreach ($miembros as &$m) {
                if (isset($notasPost[$m['idestudiante']])) {
                    $m['nota'] = $notasPost[$m['idestudiante']];
                }
            }
            NotasView::mostrarForm(GruposModel::listar($db), $grupo, $anio, $mes, $miembros, null, $resultado['errores']);
            return;
        }

        header("Location: notas.php?grupo_id=$grupoId&anio=$anio&mes=$mes&msg=guardado&n=" . $resultado['guardadas']);
        exit;
    }

    private function historial() {
        $db = Conexion::conectar();
        $grupoId = (int)($_GET['grupo_id'] ?? 0);
        $grupo = GruposModel::obtenerPorId($db, $grupoId);
        if (!$grupo) {
            header("Location: notas.php");
            exit;
        }

        NotasView::mostrarHistorial($grupo, NotasModel::historialPeriodos($db, $grupoId));
    }
}
?>
