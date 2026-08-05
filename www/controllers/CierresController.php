<?php
require_once "models/RecibosPfsModel.php";
require_once "views/CierresView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class CierresController {

    private array $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function handle(string $tipo) {
        Auth::requerirSesion();
        $db = Conexion::conectar();

        if ($tipo === 'anio') {
            $this->cierreAnio($db);
            return;
        }

        $this->cierreDia($db);
    }

    private function cierreDia(PDO $db) {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        $resultado = RecibosPfsModel::cierreDia($db, $fecha);
        CierresView::mostrarCierreDia($fecha, $resultado['detalle'], $resultado['resumen'], $resultado['anulados']);
    }

    private function cierreAnio(PDO $db) {
        $anioActual = (int)date('Y');
        $anio = (int)($_GET['anio'] ?? $anioActual);
        if ($anio < 2000 || $anio > $anioActual + 1) {
            $anio = $anioActual;
        }

        $porMes = RecibosPfsModel::cierreAnioPorMes($db, $anio);
        $anulados = RecibosPfsModel::anuladosDelAnio($db, $anio);
        CierresView::mostrarCierreAnio($anio, $anioActual, $porMes, $this->meses, $anulados);
    }
}
?>
