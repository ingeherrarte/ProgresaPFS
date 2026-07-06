<?php
require_once __DIR__ . "/../helpers/Auth.php";

class CierresView {

    private static function estilos(): void {
        ?>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 960px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: #555; }
            .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
            .card {
                background: #fff; max-width: 960px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
            }
            .filtro { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
            .filtro label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
            .filtro input, .filtro select {
                padding: 8px 10px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px;
            }
            .filtro button {
                padding: 9px 20px; font-size: 14px; font-weight: bold;
                background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            .filtro button:hover { background: #283593; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
            th { background: #1a237e; color: #fff; white-space: nowrap; }
            td.num { text-align: right; font-family: 'Courier New', monospace; }
            tbody tr:nth-child(even) { background: #f5f7ff; }
            tfoot tr { background: #1a237e !important; color: #fff; font-weight: bold; }
            tfoot td { border-color: #1a237e; }
            .sin-datos { color: #888; font-style: italic; padding: 20px 0; }
            h2 { font-size: 15px; color: #1a237e; margin: 0 0 12px; }
        </style>
        <?php
    }

    private static function barra(): void {
        ?>
        <div class="barra">
            <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
            <span>
                <a href="inicio.php">Inicio</a>
                <a href="login.php?action=logout">Cerrar sesión</a>
            </span>
        </div>
        <?php
    }

    public static function mostrarCierreDia(string $fecha, array $detalle, array $resumen): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cierre del Día — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Cierre del Día</h1>

            <div class="card">
                <form method="GET" action="cierres.php" class="filtro">
                    <input type="hidden" name="tipo" value="dia">
                    <div>
                        <label for="fecha">Fecha</label>
                        <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
                    </div>
                    <button type="submit">Consultar</button>
                </form>
            </div>

            <div class="card">
                <h2>Detalle de Recibos — <?= date('d/m/Y', strtotime($fecha)) ?></h2>
                <?php if (empty($detalle)): ?>
                    <p class="sin-datos">No hay recibos registrados en esta fecha.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Carné</th>
                                <th>Efectivo</th>
                                <th>Depósito</th>
                                <th>Cheque</th>
                                <th>No. Depósito</th>
                                <th>Fecha Dep.</th>
                                <th>Banco</th>
                                <th>Hora Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalle as $r): ?>
                                <tr>
                                    <td class="num"><?= $r['numero'] ?>-<?= $r['aleatorio'] ?></td>
                                    <td><?= htmlspecialchars($r['carne']) ?></td>
                                    <td class="num">Q <?= number_format($r['efectivo'], 2) ?></td>
                                    <td class="num">Q <?= number_format($r['deposito'], 2) ?></td>
                                    <td class="num">Q <?= number_format($r['cheque'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['nodeposito']) ?></td>
                                    <td><?= htmlspecialchars($r['fechadep']) ?></td>
                                    <td><?= htmlspecialchars($r['banco']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($r['horaregistro'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Resumen del Día</h2>
                <table>
                    <thead>
                        <tr><th>Concepto</th><th>Monto</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Efectivo</td><td class="num">Q <?= number_format($resumen['efectivo'], 2) ?></td></tr>
                        <tr><td>Depósitos</td><td class="num">Q <?= number_format($resumen['deposito'], 2) ?></td></tr>
                        <tr><td>Cheques</td><td class="num">Q <?= number_format($resumen['cheque'], 2) ?></td></tr>
                        <tr><td>Inscripción</td><td class="num">Q <?= number_format($resumen['inscripcion'], 2) ?></td></tr>
                        <tr><td>Mensualidad</td><td class="num">Q <?= number_format($resumen['mensualidad'], 2) ?></td></tr>
                        <tr><td>Otros</td><td class="num">Q <?= number_format($resumen['otro'], 2) ?></td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td>TOTAL</td><td class="num">Q <?= number_format($resumen['total'], 2) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </body>
        </html>
        <?php
    }

    public static function mostrarCierreAnio(int $anio, int $anioActual, array $porMes, array $meses): void {
        $totalEfectivo = array_sum(array_column($porMes, 'efectivo'));
        $totalDeposito = array_sum(array_column($porMes, 'deposito'));
        $totalCheque = array_sum(array_column($porMes, 'cheque'));
        $totalGeneral = $totalEfectivo + $totalDeposito + $totalCheque;
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cierre de Año — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Cierre de Año — Resumen Mensual</h1>

            <div class="card">
                <form method="GET" action="cierres.php" class="filtro">
                    <input type="hidden" name="tipo" value="anio">
                    <div>
                        <label for="anio">Año</label>
                        <select id="anio" name="anio">
                            <?php for ($y = $anioActual; $y >= 2015; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit">Consultar</button>
                </form>
            </div>

            <div class="card">
                <h2>Totales por mes — <?= $anio ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Efectivo</th>
                            <th>Depósitos</th>
                            <th>Cheques</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($porMes as $numMes => $fila): ?>
                            <?php $totalMes = $fila['efectivo'] + $fila['deposito'] + $fila['cheque']; ?>
                            <tr>
                                <td><?= ucfirst($meses[$numMes]) ?></td>
                                <td class="num">Q <?= number_format($fila['efectivo'], 2) ?></td>
                                <td class="num">Q <?= number_format($fila['deposito'], 2) ?></td>
                                <td class="num">Q <?= number_format($fila['cheque'], 2) ?></td>
                                <td class="num">Q <?= number_format($totalMes, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL AÑO</td>
                            <td class="num">Q <?= number_format($totalEfectivo, 2) ?></td>
                            <td class="num">Q <?= number_format($totalDeposito, 2) ?></td>
                            <td class="num">Q <?= number_format($totalCheque, 2) ?></td>
                            <td class="num">Q <?= number_format($totalGeneral, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <p style="margin-top:14px; font-size:12px; color:#777;">
                    Para el detalle día a día de un mes específico, usa
                    <a href="reporte_recibospfs.php">Reporte de Recibos</a>.
                </p>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
