<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/EstudiantesPfsModel.php";
require_once __DIR__ . "/../models/ConsultaPagosModel.php";

class ConsultaPagosView {

    // fechadelpago se arma con el año ACTUAL al momento de registrar el
    // recibo (ver RecibosPfsController::guardar), así que un recibo tardío
    // queda con el año equivocado. horaregistro es el datetime real de
    // cuando se guardó el recibo, siempre confiable.
    private static function fechaHora(?string $valor): string {
        if (!$valor || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor)) return '—';
        return (DateTime::createFromFormat('Y-m-d H:i:s', $valor))->format('d/m/Y H:i');
    }

    private static function estilos(): void {
        ?>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 1000px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: #555; }
            .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
            .card {
                background: #fff; max-width: 1000px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
            }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
            .campo { flex: 1; min-width: 200px; }
            label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
            input {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid #ccc; border-radius: 4px;
            }
            input:focus { outline: none; border-color: #1a237e; }
            button {
                padding: 9px 20px; font-size: 14px; font-weight: bold;
                background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            button:hover { background: #283593; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
            th { background: #1a237e; color: #fff; }
            tbody tr:nth-child(even) { background: #f5f7ff; }
            .sin-resultados { color: #777; font-style: italic; padding: 12px 0; }
            .resumen { font-size: 13px; color: #555; margin-bottom: 12px; }
            .estado-inactivo { color: #b71c1c; font-weight: bold; font-size: 11px; }
            .estado-anulado { color: #b71c1c; font-weight: bold; font-size: 11px; }
            .money { text-align: right; font-variant-numeric: tabular-nums; }
            .paginacion { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
            .paginacion a, .paginacion span {
                padding: 6px 12px; border-radius: 4px; font-size: 13px; text-decoration: none;
            }
            .paginacion a { background: #f5f7ff; color: #1a237e; }
            .paginacion a:hover { background: #e0e4ff; }
            .paginacion span.actual { background: #1a237e; color: #fff; font-weight: bold; }
            .ver-btn {
                display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: bold;
                background: #1a237e; color: #fff; text-decoration: none; border-radius: 4px;
            }
            .ver-btn:hover { background: #283593; }
            .usar-btn {
                display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: bold;
                background: #2e7d32; color: #fff; text-decoration: none; border-radius: 4px;
            }
            .usar-btn:hover { background: #256428; }
            .tarjetas {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 14px; margin-bottom: 24px;
            }
            .tarjeta {
                background: #f5f7ff; border-radius: 8px; padding: 16px; text-align: center;
                border: 1px solid #e0e4ff;
            }
            .tarjeta.total { background: #1a237e; color: #fff; border: none; }
            .tarjeta .etiqueta { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #666; font-weight: bold; }
            .tarjeta.total .etiqueta { color: #c5cae9; }
            .tarjeta .valor { font-size: 20px; font-weight: bold; margin-top: 6px; color: #1a237e; }
            .tarjeta.total .valor { color: #fff; }
            .info-grid {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 14px; margin-bottom: 22px; background: #f8f9fb; border-left: 4px solid #1a237e;
                border-radius: 6px; padding: 16px 20px;
            }
            .info-grid .dato b { display: block; color: #666; font-size: 11px; text-transform: uppercase; }
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

    public static function mostrarBuscar(string $termino, array $filas, int $pagina, int $totalPaginas, int $totalRegistros): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Consulta de Pagos — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Consulta de Pagos por Estudiante</h1>

            <div class="card">
                <form method="GET" action="consulta_pagos.php" id="formBuscar">
                    <div class="fila">
                        <div class="campo">
                            <label for="q">Nombre, apellidos o carné</label>
                            <input type="text" id="q" name="q" value="<?= htmlspecialchars($termino) ?>" autofocus autocomplete="off">
                        </div>
                        <div>
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card" id="resultadosWrap" style="<?= $termino === '' ? 'display:none' : '' ?>">
                <p class="sin-resultados" id="sinResultados" style="<?= !empty($filas) ? 'display:none' : '' ?>">
                    No se encontraron estudiantes con pagos registrados<?= $termino !== '' ? ' para "' . htmlspecialchars($termino) . '"' : '' ?>.
                </p>
                <div id="resultadosContenido" style="<?= empty($filas) ? 'display:none' : '' ?>">
                    <p class="resumen" id="resumenTexto"><?= $totalRegistros ?> estudiante(s) con pagos para "<?= htmlspecialchars($termino) ?>"</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Carné</th>
                                <th>Nombre completo</th>
                                <th>Curso</th>
                                <th>Recibos</th>
                                <th>Total pagado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyResultados">
                            <?php foreach ($filas as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['idestudiante']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(trim($f['nombre'] . ' ' . $f['apellidos'])) ?>
                                        <?php if ($f['activo'] != 1): ?>
                                            <br><span class="estado-inactivo">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($f['nombrecurso'] ?? 'No asignado') ?></td>
                                    <td><?= (int)$f['total_recibos'] ?></td>
                                    <td class="money">Q <?= number_format((float)$f['total_pagado'], 2) ?></td>
                                    <td>
                                        <a class="ver-btn" href="consulta_pagos.php?action=detalle&carnet=<?= urlencode($f['idestudiante']) ?>">Ver historial</a>
                                        <a class="usar-btn" href="recibospfs.php?carnet=<?= urlencode($f['idestudiante']) ?>">Usar en recibo</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="paginacion" id="paginacionDiv">
                        <?php if ($totalPaginas > 1): ?>
                            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                <?php if ($p === $pagina): ?>
                                    <span class="actual"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="consulta_pagos.php?q=<?= urlencode($termino) ?>&pagina=<?= $p ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                const qInput = document.getElementById('q');
                const resultadosWrap = document.getElementById('resultadosWrap');
                const sinResultados = document.getElementById('sinResultados');
                const resultadosContenido = document.getElementById('resultadosContenido');
                const resumenTexto = document.getElementById('resumenTexto');
                const tbodyResultados = document.getElementById('tbodyResultados');
                const paginacionDiv = document.getElementById('paginacionDiv');
                let temporizadorBusqueda = null;

                function escapeHtml(texto) {
                    const div = document.createElement('div');
                    div.textContent = texto ?? '';
                    return div.innerHTML;
                }

                function formatoMoneda(valor) {
                    return 'Q ' + Number(valor).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderizarFilas(filas) {
                    tbodyResultados.innerHTML = filas.map(f => {
                        const inactivo = f.activo == 0 ? '<br><span class="estado-inactivo">Inactivo</span>' : '';
                        return `<tr>
                            <td>${escapeHtml(f.idestudiante)}</td>
                            <td>${escapeHtml(f.nombreCompleto)}${inactivo}</td>
                            <td>${escapeHtml(f.nombrecurso || 'No asignado')}</td>
                            <td>${escapeHtml(f.totalRecibos)}</td>
                            <td class="money">${formatoMoneda(f.totalPagado)}</td>
                            <td>
                                <a class="ver-btn" href="consulta_pagos.php?action=detalle&carnet=${encodeURIComponent(f.idestudiante)}">Ver historial</a>
                                <a class="usar-btn" href="recibospfs.php?carnet=${encodeURIComponent(f.idestudiante)}">Usar en recibo</a>
                            </td>
                        </tr>`;
                    }).join('');
                }

                function buscarEnVivo(termino) {
                    fetch('consulta_pagos.php?action=buscarJson&q=' + encodeURIComponent(termino))
                        .then(r => r.json())
                        .then(datos => {
                            resultadosWrap.style.display = '';
                            paginacionDiv.innerHTML = '';

                            if (datos.total === 0) {
                                sinResultados.style.display = '';
                                sinResultados.textContent = 'No se encontraron estudiantes con pagos registrados para "' + termino + '".';
                                resultadosContenido.style.display = 'none';
                                return;
                            }

                            sinResultados.style.display = 'none';
                            resultadosContenido.style.display = '';
                            const nota = datos.total > datos.filas.length
                                ? ' (mostrando los ' + datos.filas.length + ' más recientes; presiona Buscar para ver todos)'
                                : '';
                            resumenTexto.textContent = datos.total + ' estudiante(s) con pagos para "' + termino + '"' + nota;
                            renderizarFilas(datos.filas);
                        })
                        .catch(() => {});
                }

                qInput.addEventListener('input', () => {
                    clearTimeout(temporizadorBusqueda);
                    const termino = qInput.value.trim();
                    if (termino.length < 3) return;
                    temporizadorBusqueda = setTimeout(() => buscarEnVivo(termino), 300);
                });
            </script>
        </body>
        </html>
        <?php
    }

    public static function mostrarDetalle(array $estudiante, array $pagos): void {
        $totalEfectivo = 0.0;
        $totalDeposito = 0.0;
        $totalCheque = 0.0;
        $totalRecibos = 0;

        foreach ($pagos as $p) {
            if ((int)$p['anulado'] === 1) continue;
            $totalEfectivo += (float)$p['efectivo'];
            $totalDeposito += (float)$p['deposito'];
            $totalCheque += (float)$p['cheque'];
            $totalRecibos++;
        }
        $totalGeneral = $totalEfectivo + $totalDeposito + $totalCheque;
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Historial de Pagos — <?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . $estudiante['apellidos'])) ?></title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Historial de Pagos</h1>

            <div class="card">
                <a class="ver-btn" href="consulta_pagos.php" style="margin-bottom:18px; display:inline-block;">← Volver a la búsqueda</a>
                <a class="usar-btn" href="recibospfs.php?carnet=<?= urlencode($estudiante['idestudiante']) ?>" style="margin-bottom:18px; margin-left:8px; display:inline-block;">Usar en recibo</a>

                <div class="info-grid">
                    <div class="dato"><b>Carné</b><?= htmlspecialchars($estudiante['idestudiante']) ?></div>
                    <div class="dato"><b>Nombre</b><?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . $estudiante['apellidos'])) ?></div>
                    <div class="dato"><b>Curso</b><?= htmlspecialchars($estudiante['nombrecurso'] ?? 'No asignado') ?></div>
                    <div class="dato"><b>Plan</b><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan($estudiante['plan'])) ?></div>
                    <div class="dato"><b>Jornada</b><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada($estudiante['jornada'])) ?></div>
                    <div class="dato"><b>Teléfono</b><?= htmlspecialchars($estudiante['telefonomovil'] ?: '—') ?></div>
                    <?php if ((int)$estudiante['activo'] !== 1): ?>
                        <div class="dato"><b>Estado</b><span class="estado-inactivo">Inactivo</span></div>
                    <?php endif; ?>
                </div>

                <div class="tarjetas">
                    <div class="tarjeta">
                        <div class="etiqueta">Recibos</div>
                        <div class="valor"><?= $totalRecibos ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Efectivo</div>
                        <div class="valor">Q <?= number_format($totalEfectivo, 2) ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Depósito</div>
                        <div class="valor">Q <?= number_format($totalDeposito, 2) ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Cheque</div>
                        <div class="valor">Q <?= number_format($totalCheque, 2) ?></div>
                    </div>
                    <div class="tarjeta total">
                        <div class="etiqueta">Total pagado</div>
                        <div class="valor">Q <?= number_format($totalGeneral, 2) ?></div>
                    </div>
                </div>

                <?php if (empty($pagos)): ?>
                    <p class="sin-resultados">Este estudiante no tiene recibos registrados.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Recibo #</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Forma de pago</th>
                                <th>Mes que paga</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $p): ?>
                                <?php
                                $total = (float)$p['efectivo'] + (float)$p['deposito'] + (float)$p['cheque'];
                                $anulado = (int)$p['anulado'] === 1;

                                // Un recibo casi siempre usa una sola forma de pago, pero
                                // puede combinarlas (ej. parte efectivo + parte depósito),
                                // así que se listan todas las que tengan monto > 0.
                                $formas = [];
                                if ((float)$p['efectivo'] > 0) {
                                    $formas[] = 'Efectivo';
                                }
                                if ((float)$p['deposito'] > 0) {
                                    $extra = htmlspecialchars($p['banco'] ?: 'banco no indicado') . ' · No. ' . htmlspecialchars($p['nodeposito']);
                                    $formas[] = 'Depósito/Transferencia<br><span style="font-size:11px;color:#777">' . $extra . '</span>';
                                }
                                if ((float)$p['cheque'] > 0) {
                                    $extra = 'No. ' . htmlspecialchars($p['nocheque']) . ($p['banco'] ? ' · ' . htmlspecialchars($p['banco']) : '');
                                    $formas[] = 'Cheque<br><span style="font-size:11px;color:#777">' . $extra . '</span>';
                                }
                                ?>
                                <tr<?= $anulado ? ' style="opacity:.55"' : '' ?>>
                                    <td>
                                        <?= htmlspecialchars($p['numero']) ?>
                                        <?php if ($anulado): ?>
                                            <br><span class="estado-anulado">Anulado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= self::fechaHora($p['horaregistro']) ?></td>
                                    <td class="money"><b>Q <?= number_format($total, 2) ?></b></td>
                                    <td><?= implode('<br>', $formas) ?: '—' ?></td>
                                    <td><?= htmlspecialchars(ConsultaPagosModel::nombreMes($p['mesquepaga'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($p['detalle']) ?>
                                        <?php if ($anulado && trim((string)$p['motivo_anulacion']) !== ''): ?>
                                            <br><span style="font-size:11px;color:#b71c1c">Motivo: <?= htmlspecialchars($p['motivo_anulacion']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
