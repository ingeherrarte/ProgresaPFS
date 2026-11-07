<?php
require_once "models/DepositosModel.php";
require_once "views/CuadreDepositosView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

// Caja del día (efectivo + cheques) contra las boletas con ese
// "Correspondiente a", y cada boleta contra el estado de cuenta. Lo usa
// quien hace la conciliación de transferencias, por eso tiene los mismos
// roles que Conciliación.
class CuadreDepositosController {

    public function handle() {
        Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);
        $db = Conexion::conectar();

        $hoy = date('Y-m-d');
        $esFecha = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
        $desde = $esFecha($_GET['desde'] ?? null) ? $_GET['desde'] : DepositosModel::INICIO_CUADRE;
        $hasta = $esFecha($_GET['hasta'] ?? null) ? $_GET['hasta'] : $hoy;
        if ($desde < DepositosModel::INICIO_CUADRE) {
            $desde = DepositosModel::INICIO_CUADRE;
        }
        if ($hasta < $desde) {
            $hasta = $desde;
        }
        $soloProblemas = ($_GET['ver'] ?? '') === 'problemas';

        $todos = DepositosModel::cuadrePorDias($db, $hasta);
        $pendiente = empty($todos) ? 0.0 : end($todos)['acumulado'];

        $enRango = array_values(array_filter($todos, fn($f) => $f['dia'] >= $desde));

        $resumen = ['cuadra' => 0, 'compensado' => 0, 'falta' => 0, 'sobra' => 0, 'hoy' => 0,
                    'boletas' => 0, 'banco_ok' => 0, 'monto_distinto' => 0, 'no_aparece' => 0, 'sin_importar' => 0];
        foreach ($enRango as $f) {
            $resumen[$f['estado']]++;
            foreach ($f['boletas'] as $b) {
                $resumen['boletas']++;
                $resumen[$b['banco_estado'] === 'ok' ? 'banco_ok' : $b['banco_estado']]++;
            }
        }

        $filas = $soloProblemas
            ? array_values(array_filter($enRango, fn($f) => $f['estado'] !== 'cuadra' && $f['estado'] !== 'compensado'
                || array_filter($f['boletas'], fn($b) => $b['banco_estado'] === 'monto_distinto' || $b['banco_estado'] === 'no_aparece')))
            : $enRango;

        CuadreDepositosView::mostrar(array_reverse($filas), $resumen, $pendiente, $desde, $hasta, $soloProblemas, count($enRango),
            DepositosModel::fechasMalIngresadas($db), Auth::rolActual() === Auth::ROL_ADMINISTRADOR);
    }
}
?>
