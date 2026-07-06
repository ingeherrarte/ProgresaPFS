<?php
require_once "models/RecibosPfsModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "views/InicioView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class InicioController {

    public function handle(string $vista) {
        Auth::requerirSesion();

        if ($vista === 'dashboard') {
            $db = Conexion::conectar();
            $stats = [
                'recibosHoy' => RecibosPfsModel::estadisticasHoy($db),
                'recibosMes' => RecibosPfsModel::estadisticasMes($db),
                'estudiantesActivos' => EstudiantesPfsModel::totalActivos($db),
                'estudiantesNuevosMes' => EstudiantesPfsModel::nuevosEsteMes($db),
            ];
            InicioView::mostrarDashboard($stats);
            return;
        }

        InicioView::mostrarSimple();
    }
}
?>
