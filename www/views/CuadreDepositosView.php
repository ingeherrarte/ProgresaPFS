<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";
require_once __DIR__ . "/../models/DepositosModel.php";

class CuadreDepositosView {

    private const ESTADOS = [
        'cuadra' => ['✅ Cuadra', 'est-ok'],
        'compensado' => ['↪ Compensado', 'est-comp'],
        'falta' => ['⏳ Falta depositar', 'est-mal'],
        'sobra' => ['⚠ Sobra en boletas', 'est-mal'],
        'hoy' => ['🕒 Del día', 'est-hoy'],
    ];

    private const BANCO = [
        'ok' => ['✅ En banco', 'est-ok'],
        'monto_distinto' => ['⚠ Monto distinto', 'est-mal'],
        'no_aparece' => ['❌ No aparece', 'est-mal'],
        'sin_importar' => ['🕒 Sin importar', 'est-hoy'],
    ];

    private static function q(float $n): string {
        return 'Q ' . number_format($n, 2);
    }

    private static function f(string $fecha): string {
        return date('d/m/Y', strtotime($fecha));
    }

    public static function mostrar(array $filas, array $resumen, float $pendiente, string $desde, string $hasta, bool $soloProblemas, int $diasEnRango): void {
        $h = fn($s) => htmlspecialchars((string)$s);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cuadre de Depósitos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <style>
                <?php Tema::tokensBase(); ?>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px 16px; color: var(--texto); }
                .barra { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; max-width: 1200px; margin: 0 auto 16px; }
                .barra .usuario { font-size: 13px; color: var(--texto-muted); }
                .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                <?php Tema::estiloBoton(); ?>
                h1 { font-size: 22px; margin-bottom: 6px; color: var(--acento); text-align: center; }
                .sub { text-align: center; font-size: 13px; color: var(--texto-muted); margin-bottom: 20px; }
                .card { background: var(--bg-tarjeta); max-width: 1200px; margin: 0 auto 20px; border-radius: 6px; padding: 20px 24px; box-shadow: 0 1px 4px var(--sombra-tarjeta); }
                .filtro { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
                .filtro label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; }
                .filtro input[type=date] { padding: 8px 10px; font-size: 14px; border: 1px solid var(--borde-input); border-radius: 4px; background: var(--bg-tarjeta); color: var(--texto); }
                .filtro .check { display: flex; align-items: center; gap: 6px; font-size: 13px; padding-bottom: 9px; }
                .filtro button { padding: 9px 20px; font-size: 14px; font-weight: bold; background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
                .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
                .tile { border-radius: 6px; padding: 12px 14px; border: 1px solid var(--borde-input); }
                .tile .valor { font-size: 22px; font-weight: bold; font-variant-numeric: tabular-nums; }
                .tile .etq { font-size: 12px; margin-top: 2px; }
                .est-ok { background: var(--estado-ok-bg); color: var(--estado-ok-texto); border-color: var(--estado-ok-borde); }
                .est-mal { background: var(--estado-falta-bg); color: var(--estado-falta-texto); border-color: var(--estado-falta-borde); }
                .est-comp { background: var(--estado-propio-bg); color: var(--estado-propio-texto); border-color: var(--estado-propio-borde); }
                .est-hoy { background: var(--fila-alterna); color: var(--texto); }
                .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
                table { border-collapse: collapse; width: 100%; font-size: 13px; }
                th, td { border: 1px solid var(--tabla-borde); padding: 7px 9px; text-align: left; }
                th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
                td.num { text-align: right; font-family: 'Courier New', monospace; white-space: nowrap; }
                tr.dia { cursor: pointer; }
                tr.dia:hover td { background: var(--fila-alterna); }
                tr.dia td:first-child::before { content: '▸ '; color: var(--texto-muted); }
                tr.dia.abierto td:first-child::before { content: '▾ '; }
                .chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; border: 1px solid transparent; white-space: nowrap; }
                .pareja { display: block; font-size: 11px; font-weight: normal; margin-top: 2px; }
                tr.detalle > td { background: var(--bg-pagina); padding: 10px 14px 14px; }
                tr.detalle table { font-size: 12px; background: var(--bg-tarjeta); }
                .nota { font-size: 12px; color: var(--texto-muted); margin-top: 10px; line-height: 1.5; }
                .sin-datos { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
                .aviso-banco { font-size: 12px; margin-top: 4px; }
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= $h(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="inicio.php">Inicio</a>
                    <a href="conciliacion.php">Conciliación</a>
                    <a href="depositos.php">Depósitos</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                    <?php Tema::boton(); ?>
                </span>
            </div>
            <h1>Cuadre de Depósitos</h1>
            <p class="sub">Caja del día (efectivo + cheques) → boletas "Correspondiente a" ese día → estado de cuenta del banco</p>

            <div class="card">
                <form method="GET" action="cuadre_depositos.php" class="filtro">
                    <div>
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" min="<?= DepositosModel::INICIO_CUADRE ?>" value="<?= $h($desde) ?>">
                    </div>
                    <div>
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" min="<?= DepositosModel::INICIO_CUADRE ?>" value="<?= $h($hasta) ?>">
                    </div>
                    <label class="check"><input type="checkbox" name="ver" value="problemas" <?= $soloProblemas ? 'checked' : '' ?>> Solo días con algo por revisar</label>
                    <button type="submit">Consultar</button>
                </form>
            </div>

            <div class="card">
                <div class="tiles">
                    <div class="tile <?= abs($pendiente) < 0.005 ? 'est-ok' : 'est-mal' ?>">
                        <div class="valor"><?= self::q($pendiente) ?></div>
                        <div class="etq">Pendiente de depositar acumulado al <?= self::f($hasta) ?> (desde <?= self::f(DepositosModel::INICIO_CUADRE) ?>)</div>
                    </div>
                    <div class="tile est-ok">
                        <div class="valor"><?= $resumen['cuadra'] ?> / <?= $diasEnRango ?></div>
                        <div class="etq">Días que cuadran</div>
                    </div>
                    <div class="tile est-comp">
                        <div class="valor"><?= $resumen['compensado'] ?></div>
                        <div class="etq">Días compensados (fecha de boleta corrida)</div>
                    </div>
                    <div class="tile <?= $resumen['falta'] + $resumen['sobra'] ? 'est-mal' : 'est-ok' ?>">
                        <div class="valor"><?= $resumen['falta'] + $resumen['sobra'] ?></div>
                        <div class="etq">Días por revisar (<?= $resumen['falta'] ?> falta · <?= $resumen['sobra'] ?> sobra)</div>
                    </div>
                    <div class="tile <?= $resumen['monto_distinto'] + $resumen['no_aparece'] ? 'est-mal' : 'est-ok' ?>">
                        <div class="valor"><?= $resumen['banco_ok'] ?> / <?= $resumen['boletas'] ?></div>
                        <div class="etq">Boletas confirmadas en banco · <?= $resumen['monto_distinto'] ?> monto distinto · <?= $resumen['no_aparece'] ?> no aparecen · <?= $resumen['sin_importar'] ?> sin importar</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <?php if (empty($filas)): ?>
                    <p class="sin-datos"><?= $soloProblemas ? 'No hay días con algo por revisar en este rango. 🎉' : 'No hay movimiento de caja ni boletas en este rango.' ?></p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Día</th><th>Efectivo</th><th>Cheques</th><th>Caja</th><th>Depositado</th><th>Boletas</th>
                                <th>Dif. del día</th><th>Pendiente acum.</th><th>Estado</th><th>Banco</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $i => $f):
                                [$txtEstado, $clsEstado] = self::ESTADOS[$f['estado']];
                                $alertasBanco = count(array_filter($f['boletas'], fn($b) => in_array($b['banco_estado'], ['monto_distinto', 'no_aparece'], true)));
                                $sinImportar = count(array_filter($f['boletas'], fn($b) => $b['banco_estado'] === 'sin_importar'));
                            ?>
                                <tr class="dia" data-detalle="det<?= $i ?>" tabindex="0" aria-expanded="false">
                                    <td><?= self::f($f['dia']) ?></td>
                                    <td class="num"><?= self::q($f['efectivo']) ?></td>
                                    <td class="num"><?= self::q($f['cheque']) ?></td>
                                    <td class="num"><b><?= self::q($f['caja']) ?></b></td>
                                    <td class="num"><?= self::q($f['depositado']) ?></td>
                                    <td class="num"><?= count($f['boletas']) ?></td>
                                    <td class="num"><?= abs($f['diferencia']) < 0.005 ? '—' : self::q($f['diferencia']) ?></td>
                                    <td class="num"><?= self::q($f['acumulado']) ?></td>
                                    <td>
                                        <span class="chip <?= $clsEstado ?>"><?= $txtEstado ?></span>
                                        <?php if ($f['pareja']): ?>
                                            <span class="pareja"><?= $f['diferencia'] > 0 ? 'se depositó con el' : 'incluye lo del' ?> <?= self::f($f['pareja']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($f['boletas'])): ?>—
                                        <?php elseif ($alertasBanco): ?><span class="chip est-mal">⚠ <?= $alertasBanco ?> por revisar</span>
                                        <?php elseif ($sinImportar): ?><span class="chip est-hoy">🕒 Sin importar</span>
                                        <?php else: ?><span class="chip est-ok">✅ En banco</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="detalle" id="det<?= $i ?>" hidden>
                                    <td colspan="10">
                                        <?php if (empty($f['boletas'])): ?>
                                            <p class="sin-datos">No hay boletas con "Correspondiente a" <?= self::f($f['dia']) ?>.</p>
                                        <?php else: ?>
                                            <table>
                                                <thead>
                                                    <tr><th>No. Boleta</th><th>Fecha dep.</th><th>Banco</th><th>Efectivo</th><th>Cheques</th><th>Total boleta</th><th>Responsable</th><th>Estado de cuenta</th><th>Foto</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($f['boletas'] as $b): [$txtB, $clsB] = self::BANCO[$b['banco_estado']]; ?>
                                                        <tr>
                                                            <td><?= $h($b['nodeposito']) ?></td>
                                                            <td><?= self::f($b['fechadep']) ?></td>
                                                            <td><?= $h($b['banco']) ?></td>
                                                            <td class="num"><?= self::q((float)$b['efectivo']) ?></td>
                                                            <td class="num"><?= self::q((float)$b['chpropio'] + (float)$b['chotrobanco']) ?></td>
                                                            <td class="num"><b><?= self::q($b['monto']) ?></b></td>
                                                            <td><?= $h($b['responsable']) ?></td>
                                                            <td>
                                                                <span class="chip <?= $clsB ?>"><?= $txtB ?></span>
                                                                <?php if ($b['banco_estado'] === 'ok'): ?>
                                                                    <div class="aviso-banco"><?= self::f($b['banco_fecha']) ?></div>
                                                                <?php elseif ($b['banco_estado'] === 'monto_distinto'): ?>
                                                                    <div class="aviso-banco">El banco registra <?= self::q((float)$b['banco_monto']) ?> el <?= self::f($b['banco_fecha']) ?> — corregir el monto de la boleta</div>
                                                                <?php elseif ($b['banco_estado'] === 'no_aparece'): ?>
                                                                    <div class="aviso-banco">No hay línea con referencia <?= $h($b['nodeposito']) ?> en el estado de cuenta (importado hasta <?= self::f($b['ultima_importada']) ?>). Revisar el número de boleta.</div>
                                                                <?php else: ?>
                                                                    <div class="aviso-banco">Estado de cuenta importado hasta <?= $b['ultima_importada'] ? self::f($b['ultima_importada']) : '—' ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($b['foto_boleta'])): ?>
                                                                    <a href="uploads/depositos/<?= urlencode($b['foto_boleta']) ?>" target="_blank" rel="noopener">Ver</a>
                                                                <?php else: ?>—<?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                        <p class="nota"><a href="cierres.php?tipo=dia&amp;fecha=<?= urlencode($f['dia']) ?>">Ver Cierre del Día <?= self::f($f['dia']) ?> →</a></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
                <p class="nota">
                    <b>Caja</b> = efectivo + cheques de los recibos del día (sin anulados); las transferencias no entran porque ya están en el banco.
                    <b>Depositado</b> = boletas cuyo "Correspondiente a" es ese día.
                    <b>Compensado</b> = el faltante de un día se cubrió con un sobrante igual en los <?= DepositosModel::DIAS_COMPENSACION ?> días siguientes (boleta con la fecha del depósito en vez de la del ingreso).
                    <b>Pendiente acum.</b> = lo que entró en caja y todavía no aparece en ninguna boleta, contado desde el <?= self::f(DepositosModel::INICIO_CUADRE) ?>.
                    Recomendación: en "Correspondiente a" poner siempre el día en que entró el dinero, aunque se deposite después.
                </p>
            </div>

            <script>
                document.querySelectorAll('tr.dia').forEach(function (tr) {
                    function alternar() {
                        var det = document.getElementById(tr.dataset.detalle);
                        det.hidden = !det.hidden;
                        tr.classList.toggle('abierto', !det.hidden);
                        tr.setAttribute('aria-expanded', det.hidden ? 'false' : 'true');
                    }
                    tr.addEventListener('click', function (e) { if (!e.target.closest('a')) alternar(); });
                    tr.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); alternar(); } });
                });
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
