<?php
require_once "models/RecibosPfsModel.php";
require_once "views/DashboardView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class DashboardController {

    private array $meses = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    // Disponible para cualquier usuario con sesión, igual que Cierre del
    // Mes/Año (RecibosPfsController): es un reporte agregado de solo
    // lectura, no expone datos de un estudiante en particular.
    public function handle() {
        Auth::requerirSesion();
        $db = Conexion::conectar();

        $anioActual = (int)date('Y');
        $anio = (int)($_GET['anio'] ?? $anioActual);
        if ($anio < 2000 || $anio > $anioActual + 1) {
            $anio = $anioActual;
        }

        $porMes = RecibosPfsModel::cierreAnioPorMes($db, $anio);
        $porDiplomado = RecibosPfsModel::ingresosPorDiplomado($db, $anio);
        $porConceptos = RecibosPfsModel::ingresosPorConceptosEspeciales($db, $anio);

        DashboardView::mostrar($anio, $anioActual, $porMes, $this->meses, $porDiplomado, $porConceptos);
    }
}
?>
