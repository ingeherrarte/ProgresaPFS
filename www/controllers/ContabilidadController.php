<?php
require_once "models/GastosModel.php";
require_once "views/ContabilidadView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class ContabilidadController {

    public function handle(string $action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'gastos':
            default:
                $this->gastos();
                break;
        }
    }

    private function anioValido(?string $valor): int {
        $anio = (int)($valor ?? date('Y'));
        return ($anio >= 2020 && $anio <= (int)date('Y') + 1) ? $anio : (int)date('Y');
    }

    private function mesValido(?string $valor): ?int {
        if ($valor === null || $valor === '') {
            return null;
        }
        $mes = (int)$valor;
        return ($mes >= 1 && $mes <= 12) ? $mes : null;
    }

    // Reporte de "Gastos" (submódulo de Contabilidad): toma los débitos ya
    // importados del estado de cuenta (ver Conciliación) y los agrupa por
    // un tipo estimado a partir de la descripción del banco — no hay
    // todavía un registro manual de gastos con categoría/comprobante
    // propios, esto es solo lo que el banco ya trae. Filtros de mes y de
    // texto libre (referencia/descripción) son opcionales, igual que en
    // "Movimientos del banco".
    private function gastos() {
        $anio = $this->anioValido($_GET['anio'] ?? null);
        $mes = $this->mesValido($_GET['mes'] ?? null);
        $texto = trim($_GET['texto'] ?? '');
        $db = Conexion::conectar();
        $porTipo = GastosModel::reportePorTipo($db, $anio, $mes, $texto);
        ContabilidadView::mostrarReporteGastos($porTipo, $anio, $mes, $texto);
    }
}
?>
