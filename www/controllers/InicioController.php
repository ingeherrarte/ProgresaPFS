<?php
require_once "models/RecibosPfsModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "models/GruposModel.php";
require_once "models/AsistenciaModel.php";
require_once "models/ConsultaPagosModel.php";
require_once "views/InicioView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class InicioController {

    public function handle(string $vista) {
        Auth::requerirSesion();

        if ($vista === 'dashboard' || $vista === 'moderno') {
            $db = Conexion::conectar();
            $stats = [
                'recibosHoy' => RecibosPfsModel::estadisticasHoy($db),
                'recibosMes' => RecibosPfsModel::estadisticasMes($db),
                'estudiantesActivos' => GruposModel::totalMiembrosActivos($db),
                'estudiantesNuevosMes' => EstudiantesPfsModel::nuevosEsteMes($db),
                'asistenciaHoy' => AsistenciaModel::resumenHoy($db),
                'asistenciaSemana' => AsistenciaModel::resumenSemana($db),
            ];

            // Mismo origen que "Reportes" de Consulta de Pagos (miembros de
            // Grupos, ver ConsultaPagosModel::reporteUnificado) — se reusa
            // el mismo método en vez de duplicar la lógica de estado de
            // pago o de detección de graduación en otra consulta aparte.
            $reportes = ConsultaPagosModel::reporteUnificado($db, 'GRADUA', 'LIBRO');
            $stats['pagosAlDia'] = count(array_filter($reportes, fn($f) => $f['estado_pago'] === 'al_dia'));
            $stats['pagosDebe1Mes'] = count(array_filter($reportes, fn($f) => $f['estado_pago'] === 'debe_1_mes'));
            $stats['pagosDebe2_3Meses'] = count(array_filter($reportes, fn($f) => $f['estado_pago'] === 'debe_2_3_meses'));
            $stats['pagosGraduacion'] = count(array_filter($reportes, fn($f) => $f['monto_graduacion'] !== null));

            if ($vista === 'moderno') {
                InicioView::mostrarModerno($stats);
            } else {
                InicioView::mostrarDashboard($stats);
            }
            return;
        }

        $sinPermiso = ($_GET['error'] ?? '') === 'permiso';
        InicioView::mostrarSimple($sinPermiso);
    }
}
?>
