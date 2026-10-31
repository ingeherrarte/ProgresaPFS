<?php
require_once "models/RecibosPfsModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "models/GruposModel.php";
require_once "views/InicioView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class InicioController {

    public function handle(string $vista) {
        Auth::requerirSesion();
        $sinPermiso = ($_GET['error'] ?? '') === 'permiso';

        if ($vista === 'simple') {
            InicioView::mostrarSimple($sinPermiso);
            return;
        }

        $db = Conexion::conectar();
        $stats = [
            'recibosHoy' => RecibosPfsModel::estadisticasHoy($db),
            'recibosMes' => RecibosPfsModel::estadisticasMes($db),
            'estudiantesActivos' => GruposModel::totalMiembrosActivos($db),
            'estudiantesNuevosMes' => EstudiantesPfsModel::nuevosEsteMes($db),
        ];

        if ($vista === 'dashboard') {
            InicioView::mostrarDashboard($stats);
            return;
        }

        // Sin vista, o vista=moderno: panel principal (default).
        InicioView::mostrarModerno($stats, $sinPermiso);
    }
}
?>
