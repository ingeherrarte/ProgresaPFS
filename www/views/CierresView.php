<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";

class CierresView {

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 960px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            .card {
                background: var(--bg-tarjeta); max-width: 960px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            .filtro { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
            .filtro label { display: block; font-size: 12px; font-weight: bold; color: var(--texto); margin-bottom: 4px; }
            .filtro input, .filtro select {
                padding: 8px 10px; font-size: 14px; border: 1px solid var(--borde-input); border-radius: 4px;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            .filtro button {
                padding: 9px 20px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            .filtro button:hover { background: #283593; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
            td { color: var(--texto); }
            td.num { text-align: right; font-family: 'Courier New', monospace; }
            tbody tr:nth-child(even) { background: var(--fila-alterna); }
            tfoot tr { background: var(--tabla-header-bg) !important; color: var(--tabla-header-texto); font-weight: bold; }
            tfoot td { border-color: var(--tabla-header-bg); color: var(--tabla-header-texto); }
            .sin-datos { color: var(--sin-datos); font-style: italic; padding: 20px 0; }
            h2 { font-size: 15px; color: var(--acento); margin: 0 0 12px; }
            .fila-anulada td { color: #999; text-decoration: line-through; }
            .fila-anulada td.estado { text-decoration: none; }
            .estado-anulado { color: #b71c1c; font-weight: bold; text-decoration: none !important; }
            .estado-activo { color: #2e7d32; }
            .nota-anulados {
                background: #fff8e1; color: #8d6e00; padding: 10px 14px; border-radius: 4px;
                font-size: 13px; margin-top: 14px;
            }
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
                <?php Tema::boton(); ?>
            </span>
        </div>
        <?php
    }

    public static function mostrarCierreDia(string $fecha, array $detalle, array $resumen, array $anulados): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cierre del Día — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
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
                                <th>Comprobante</th>
                                <th>Hora Registro</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalle as $r): ?>
                                <tr class="<?= $r['anulado'] ? 'fila-anulada' : '' ?>" <?= $r['anulado'] ? 'title="Anulado: ' . htmlspecialchars($r['motivo_anulacion']) . '"' : '' ?>>
                                    <td class="num"><?= $r['numero'] ?>-<?= $r['aleatorio'] ?></td>
                                    <td><?= htmlspecialchars($r['carne']) ?></td>
                                    <td class="num">Q <?= number_format($r['efectivo'], 2) ?></td>
                                    <td class="num">Q <?= number_format($r['deposito'], 2) ?></td>
                                    <td class="num">Q <?= number_format($r['cheque'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['nodeposito']) ?></td>
                                    <td><?= htmlspecialchars($r['fechadep']) ?></td>
                                    <td><?= htmlspecialchars($r['banco']) ?></td>
                                    <td>
                                        <?php if (!empty($r['foto_deposito'])): ?>
                                            <a href="uploads/recibos/<?= urlencode($r['foto_deposito']) ?>" target="_blank" rel="noopener">Ver</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($r['horaregistro'])) ?></td>
                                    <td class="estado">
                                        <?php if ($r['anulado']): ?>
                                            <span class="estado-anulado">Anulado</span>
                                        <?php else: ?>
                                            <span class="estado-activo">Activo</span>
                                        <?php endif; ?>
                                    </td>
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

                <?php if ($anulados['cantidad'] > 0): ?>
                    <div class="nota-anulados">
                        ⚠ Este resumen no incluye <?= (int)$anulados['cantidad'] ?> recibo(s) anulado(s)
                        por un total de Q <?= number_format($anulados['total'], 2) ?>
                        (marcados como "Anulado" y tachados en el detalle de arriba).
                    </div>
                <?php endif; ?>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarCierreAnio(int $anio, int $anioActual, array $porMes, array $meses, array $anulados): void {
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
            <?php Tema::scriptInicial(); ?>
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

                <?php if ($anulados['cantidad'] > 0): ?>
                    <div class="nota-anulados">
                        ⚠ Estos totales no incluyen <?= (int)$anulados['cantidad'] ?> recibo(s) anulado(s) en <?= $anio ?>
                        por un total de Q <?= number_format($anulados['total'], 2) ?>.
                    </div>
                <?php endif; ?>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
