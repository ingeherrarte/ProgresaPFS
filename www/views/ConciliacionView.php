<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/DepositosModel.php";
require_once __DIR__ . "/../helpers/Tema.php";

class ConciliacionView {

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 1100px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            h2 { font-size: 15px; color: var(--acento); margin: 0 0 12px; }
            .card {
                background: var(--bg-tarjeta); max-width: 1100px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            label { display: block; font-size: 13px; font-weight: bold; color: var(--texto); margin-bottom: 5px; }
            input, select {
                width: 100%; padding: 10px; font-size: 14px;
                border: 1px solid var(--borde-input); border-radius: 4px; margin-bottom: 14px;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            input:focus, select:focus { outline: none; border-color: var(--acento); }
            .fila { display: flex; gap: 14px; flex-wrap: wrap; }
            .campo { flex: 1; min-width: 180px; }
            button {
                padding: 11px 18px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            button:hover { background: #283593; }
            button.chico { padding: 6px 12px; font-size: 12.5px; }
            .errores {
                background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .exito {
                background: #e8f5e9; border: 1px solid #81c784; color: #2e7d32;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .aviso-pendiente {
                background: #fff8e1; border: 1px solid #ffca28; color: #8d6e00;
                padding: 14px 20px; font-size: 13.5px;
            }
            .aviso-pendiente a { color: var(--acento); font-weight: bold; text-decoration: none; }
            .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
            td { color: var(--texto); }
            td.num { text-align: right; font-family: 'Courier New', monospace; }
            .sin-datos { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
            .pill {
                display: inline-block; padding: 3px 10px; border-radius: 999px;
                font-size: 11.5px; font-weight: bold;
            }
            .pill.ok { background: var(--estado-ok-bg); color: var(--estado-ok-texto); }
            .pill.falta { background: var(--estado-falta-bg); color: var(--estado-falta-texto); }
            .pill.propio { background: var(--estado-propio-bg); color: var(--estado-propio-texto); }
            .viz-tooltip {
                position: fixed; display: none; pointer-events: none; z-index: 50;
                background: #0b0b0b; color: #fff; font-size: 12px; line-height: 1.5;
                padding: 8px 10px; border-radius: 5px; max-width: 240px;
                box-shadow: 0 2px 10px rgba(0,0,0,.25);
            }
            .tabla-toggle {
                font-size: 12.5px; color: var(--acento); font-weight: bold; cursor: pointer;
                background: none; border: none; padding: 0; margin-top: 10px; text-decoration: underline;
            }
            table.datos { border-collapse: collapse; width: 100%; font-size: 12.5px; }
            table.datos th, table.datos td { border: 1px solid var(--tabla-borde); padding: 6px 9px; text-align: left; }
            table.datos th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); }
            table.datos td.num { text-align: right; font-variant-numeric: tabular-nums; }
            tr.fila-ok td { background: var(--fila-ok-bg); }
            tr.fila-falta td { background: var(--fila-falta-bg); }
            tr.fila-propio td { background: var(--fila-propio-bg); }
            .numeros { font-size: 11.5px; color: var(--sin-datos); margin-top: 2px; }
            .progreso-archivos { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
            .chip-archivo {
                padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: bold;
                border: 1px solid var(--borde-input); color: var(--texto-muted);
            }
            .chip-archivo.hecho { background: var(--estado-ok-bg); color: var(--estado-ok-texto); border-color: var(--estado-ok-borde); }
            .chip-archivo.actual { background: var(--acento); color: #fff; border-color: var(--acento); }
            .tabs-bar { display: flex; gap: 6px; border-bottom: 2px solid var(--tabla-borde); margin-bottom: 14px; }
            .tab-btn {
                background: none; border: none; padding: 9px 16px; font-size: 13px; font-weight: bold;
                color: var(--texto-muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px;
            }
            .tab-btn.activo { color: var(--acento); border-bottom-color: var(--acento); }
            .accesos-conciliacion {
                display: flex; gap: 12px; flex-wrap: wrap; max-width: 1100px; margin: 0 auto 18px;
            }
            .acceso-mini {
                background: var(--fila-alterna); border-radius: 8px; padding: 12px 18px; text-align: center;
                text-decoration: none; transition: transform .15s; min-width: 140px;
            }
            .acceso-mini:hover { transform: translateY(-2px); }
            .acceso-mini .icono { font-size: 20px; margin-bottom: 4px; }
            .acceso-mini .titulo { font-size: 12.5px; font-weight: bold; color: var(--acento); }
        </style>
        <?php
    }

    // Convierte índice 0-based a letra tipo hoja de cálculo (0->A, 25->Z, 26->AA...).
    private static function columnaLetra(int $indice): string {
        $letra = '';
        $n = $indice + 1;
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $n = intdiv($n - 1, 26);
        }
        return $letra;
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

    public static function mostrarPrincipal(array $pendientes, array $lineas, ?string $mensaje, ?string $error, string $desde, string $hasta, string $desdeSubida, string $hastaSubida, ?array $pendienteMapeo = null): void {
        $esAdmin = Auth::esAdministrador();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Conciliación de Transferencias — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Conciliación de Transferencias</h1>

            <?php if ($pendienteMapeo): ?>
                <div class="card aviso-pendiente">
                    📄 Tienes un archivo de <b><?= htmlspecialchars($pendienteMapeo['banco']) ?></b> a medio mapear
                    (<?= $pendienteMapeo['numero'] ?> de <?= $pendienteMapeo['total'] ?>) — puedes seguir revisando y continuar cuando quieras.
                    <a href="conciliacion.php?action=mapeo" style="margin-left:8px;">Continuar mapeo →</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <?php if ($mensaje): ?><div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="errores">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

                <h2>Subir estados de cuenta (BANRURAL y/o BANTRAB)</h2>
                <form method="POST" action="conciliacion.php?action=subir" enctype="multipart/form-data">
                    <div class="fila">
                        <div class="campo">
                            <label for="archivo">Primer archivo (requerido): CSV, Excel .xlsx, o .xls exportado como tabla HTML (máx. 3 MB)</label>
                            <input type="file" id="archivo" name="archivo"
                                   accept=".csv,text/csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                   required>
                        </div>
                        <div class="campo" style="max-width:200px;">
                            <label for="cuenta1">¿De qué banco es?</label>
                            <select id="cuenta1" name="cuenta1" required>
                                <?php foreach (DepositosModel::cuentas() as $numeroCuenta => $banco): ?>
                                    <option value="<?= htmlspecialchars($numeroCuenta) ?>" <?= $banco === 'BANRURAL' ? 'selected' : '' ?>><?= htmlspecialchars($banco) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="archivo2">Segundo archivo (opcional): el otro banco — se asume automáticamente, no hace falta indicarlo</label>
                            <input type="file" id="archivo2" name="archivo2"
                                   accept=".csv,text/csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo" style="max-width:220px;">
                            <label for="desde_subir">Período de los estados de cuenta: desde</label>
                            <input type="date" id="desde_subir" name="desde" value="<?= htmlspecialchars($desdeSubida) ?>">
                        </div>
                        <div class="campo" style="max-width:220px;">
                            <label for="hasta_subir">hasta</label>
                            <input type="date" id="hasta_subir" name="hasta" value="<?= htmlspecialchars($hastaSubida) ?>">
                        </div>
                    </div>
                    <button type="submit">Subir y mapear columnas →</button>
                </form>
                <p style="font-size:12px;color:var(--sin-datos);margin-top:10px;">
                    El sistema detecta el formato real de cada archivo (no la extensión) — funciona con CSV, .xlsx real, y con los ".xls" que en realidad son una tabla HTML (común en exports de banca en línea). Solo el .xls binario real de Excel 97-2003 no se puede leer directo.
                    Las columnas de cada banco ya están precargadas (siempre son las mismas) — la pantalla de mapeo las muestra para que las verifiques, no para elegirlas desde cero.
                </p>
            </div>

            <div class="accesos-conciliacion">
                <a class="acceso-mini" href="conciliacion.php?action=buscarManual">
                    <div class="icono">🔗</div>
                    <div class="titulo">Vincular manualmente</div>
                </a>
                <a class="acceso-mini" href="conciliacion.php?action=movimientos">
                    <div class="icono">🏦</div>
                    <div class="titulo">Movimientos del banco</div>
                </a>
                <a class="acceso-mini" href="conciliacion.php?action=comparativo">
                    <div class="icono">⚖️</div>
                    <div class="titulo">Comparativo Mensual</div>
                </a>
                <a class="acceso-mini" href="conciliacion.php?action=gastos">
                    <div class="icono">💸</div>
                    <div class="titulo">Gastos</div>
                </a>
                <?php if ($esAdmin): ?>
                    <a class="acceso-mini" href="conciliacion.php?action=reporte">
                        <div class="icono">📈</div>
                        <div class="titulo">Reporte de verificados</div>
                    </a>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Recibos pendientes de verificar</h2>
                <p style="font-size:12px;color:var(--sin-datos);margin:-4px 0 12px;">
                    "Vincular manualmente" (arriba) es para cuando el cruce automático no aplica: una transferencia de un período muy anterior, o un depósito que cubre varios recibos a la vez (ej. una empresa paga por 2 estudiantes con la misma transferencia).
                </p>
                <form method="GET" action="conciliacion.php" style="margin-bottom:16px;">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo" style="max-width:200px;">
                            <label for="desde">Revisar desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="max-width:200px;">
                            <label for="hasta">hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Filtrar</button>
                        </div>
                    </div>
                </form>
                <?php if (empty($pendientes)): ?>
                    <p class="sin-datos">No hay recibos pagados por depósito/transferencia pendientes de verificar.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Fecha depósito</th>
                                <th>Monto</th>
                                <th>Cuenta destino</th>
                                <th>Línea del banco a usar</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendientes as $r): ?>
                                <?php $candidatos = $r['candidatos']; ?>
                                <tr class="<?= !empty($candidatos) ? 'fila-ok' : 'fila-falta' ?>">
                                    <td>
                                        <a href="recibospfs.php?action=ver&amp;numero=<?= (int)$r['numero'] ?>" target="_blank" rel="noopener" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$r['numero'] ?></a>
                                        <div class="numeros">carné <?= htmlspecialchars($r['carne']) ?></div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                                    <td class="num">Q <?= number_format($r['monto'], 2) ?></td>
                                    <td>
                                        <?php if ($r['cuenta_destino']): ?>
                                            <?= htmlspecialchars(DepositosModel::bancoDeCuenta($r['cuenta_destino']) ?? $r['cuenta_destino']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--sin-datos);">(sin dato)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($candidatos)): ?>
                                            <span class="pill falta">✗ Falta importar</span>
                                        <?php elseif (count($candidatos) === 1): ?>
                                            <?php $c = $candidatos[0]; ?>
                                            <span class="pill ok">✓</span>
                                            <b><?= htmlspecialchars(DepositosModel::bancoDeCuenta($c['cuenta']) ?? $c['cuenta']) ?></b>
                                            · <?= date('d/m/Y', strtotime($c['fecha'])) ?>
                                            <?php if ($c['referencia']): ?>· ref <?= htmlspecialchars($c['referencia']) ?><?php endif; ?>
                                            <?php if ($c['descripcion']): ?>· <?= htmlspecialchars($c['descripcion']) ?><?php endif; ?>
                                            · Q <?= number_format((float)$c['monto'], 2) ?>
                                        <?php else: ?>
                                            <span class="pill falta">⚠ <?= count($candidatos) ?> candidatas — elige una</span>
                                            <select name="estado_cuenta_id" form="form_verificar_<?= (int)$r['numero'] ?>" style="margin:4px 0 0;" required>
                                                <option value="" selected disabled>-- Elige la línea --</option>
                                                <?php foreach ($candidatos as $c): ?>
                                                    <option value="<?= (int)$c['id'] ?>">
                                                        <?= htmlspecialchars(DepositosModel::bancoDeCuenta($c['cuenta']) ?? $c['cuenta']) ?>
                                                        · <?= date('d/m/Y', strtotime($c['fecha'])) ?>
                                                        <?= $c['referencia'] ? '· ref ' . htmlspecialchars($c['referencia']) : '' ?>
                                                        <?= $c['descripcion'] ? '· ' . htmlspecialchars($c['descripcion']) : '' ?>
                                                        · Q <?= number_format((float)$c['monto'], 2) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($candidatos) && $esAdmin): ?>
                                            <?php
                                            $banco = DepositosModel::bancoDeCuenta($candidatos[0]['cuenta']) ?? $candidatos[0]['cuenta'];
                                            $mensajeConfirm = count($candidatos) === 1
                                                ? "¿Confirmas que el recibo #{$r['numero']} (Q " . number_format($r['monto'], 2) . ") corresponde a la línea de $banco del "
                                                    . date('d/m/Y', strtotime($candidatos[0]['fecha'])) . "?"
                                                : "¿Confirmas vincular el recibo #{$r['numero']} (Q " . number_format($r['monto'], 2) . ") con la línea seleccionada en el menú?";
                                            $onsubmit = 'return confirm(' . json_encode($mensajeConfirm, JSON_UNESCAPED_UNICODE) . ');';
                                            ?>
                                            <form id="form_verificar_<?= (int)$r['numero'] ?>" method="POST" action="conciliacion.php?action=verificar" onsubmit="<?= htmlspecialchars($onsubmit) ?>">
                                                <input type="hidden" name="numero_recibo" value="<?= (int)$r['numero'] ?>">
                                                <?php if (count($candidatos) === 1): ?>
                                                    <input type="hidden" name="estado_cuenta_id" value="<?= (int)$candidatos[0]['id'] ?>">
                                                <?php endif; ?>
                                                <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                                                <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                                                <button type="submit" class="chico">Verificar</button>
                                            </form>
                                        <?php elseif (!empty($candidatos)): ?>
                                            <span class="pill falta">Requiere admin</span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>
                    Líneas del estado de cuenta (<?= count($lineas) ?>)
                    <span class="numeros" style="display:inline;">— <?= date('d/m/Y', strtotime($desdeSubida)) ?> al <?= date('d/m/Y', strtotime($hastaSubida)) ?></span>
                </h2>
                <?php if (empty($lineas)): ?>
                    <p class="sin-datos">No hay líneas importadas en este período. Sube el estado de cuenta de cada banco arriba (BANRURAL y BANTRAB se mapean por separado, pero puedes subir los dos juntos).</p>
                <?php else: ?>
                    <?php
                    // Una pestaña por cuenta conocida (BANRURAL/BANTRAB, en el
                    // mismo orden del catálogo), más una pestaña extra solo si
                    // aparece alguna cuenta fuera de catálogo (no debería pasar).
                    $porCuenta = [];
                    foreach ($lineas as $l) {
                        $porCuenta[$l['cuenta']][] = $l;
                    }
                    $pestanas = [];
                    foreach (DepositosModel::cuentas() as $numeroCuenta => $banco) {
                        $pestanas[] = ['banco' => $banco, 'lineas' => $porCuenta[$numeroCuenta] ?? []];
                        unset($porCuenta[$numeroCuenta]);
                    }
                    foreach ($porCuenta as $numeroCuenta => $lineasSueltas) {
                        $pestanas[] = ['banco' => $numeroCuenta, 'lineas' => $lineasSueltas];
                    }
                    ?>
                    <div class="tabs-bar">
                        <?php foreach ($pestanas as $i => $p): ?>
                            <button type="button" class="tab-btn <?= $i === 0 ? 'activo' : '' ?>" id="tabBtn<?= $i ?>" onclick="mostrarPestanaBanco(<?= $i ?>)">
                                <?= htmlspecialchars($p['banco']) ?> (<?= count($p['lineas']) ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($pestanas as $i => $p): ?>
                        <div class="tab-contenido" id="tabContenido<?= $i ?>" <?= $i === 0 ? '' : 'hidden' ?>>
                            <?php if (empty($p['lineas'])): ?>
                                <p class="sin-datos">No hay líneas de <?= htmlspecialchars($p['banco']) ?> en este período.</p>
                            <?php else: ?>
                                <?php
                                $conciliadas = count(array_filter($p['lineas'], fn($l) => (int)$l['conciliado'] === 1));
                                $totalMonto = array_sum(array_map(fn($l) => (float)$l['monto'], $p['lineas']));
                                ?>
                                <p style="font-size:12.5px;color:var(--texto-muted);margin-bottom:12px;">
                                    <?= $conciliadas ?> de <?= count($p['lineas']) ?> ya marcadas contra recibo ·
                                    total de créditos cargados: Q <?= number_format($totalMonto, 2) ?>
                                </p>
                                <div class="tabla-scroll">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Monto</th>
                                            <th>Referencia del banco</th>
                                            <th>Recibo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($p['lineas'] as $l): ?>
                                            <tr class="<?= (int)$l['conciliado'] === 1 ? 'fila-ok' : 'fila-falta' ?>">
                                                <td><?= date('d/m/Y', strtotime($l['fecha'])) ?></td>
                                                <td class="num">Q <?= number_format((float)$l['monto'], 2) ?></td>
                                                <td><?= $l['referencia'] !== null && $l['referencia'] !== '' ? htmlspecialchars($l['referencia']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                                <td>
                                                    <?php if ($l['recibo_numero']): ?>
                                                        <a href="recibospfs.php?action=ver&amp;numero=<?= (int)$l['recibo_numero'] ?>" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$l['recibo_numero'] ?></a>
                                                        <?php if ($l['carne_automatico']): ?>
                                                            <div class="numeros">carné <?= htmlspecialchars($l['carne_automatico']) ?></div>
                                                        <?php endif; ?>
                                                    <?php elseif ($l['vinculos_manuales']): ?>
                                                        <?php foreach (explode(',', $l['vinculos_manuales']) as $par): [$numRecibo, $montoAsignado] = explode(':', $par); ?>
                                                            <div>
                                                                <a href="recibospfs.php?action=ver&amp;numero=<?= (int)$numRecibo ?>" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$numRecibo ?></a>
                                                                <span class="numeros">Q <?= number_format((float)$montoAsignado, 2) ?> (manual)</span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span style="color:var(--sin-datos);">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int)$l['conciliado'] === 1): ?>
                                                        <span class="pill ok">✓ Marcada</span>
                                                    <?php elseif ($l['vinculos_manuales']): ?>
                                                        <span class="pill falta">⟳ Parcial</span>
                                                    <?php else: ?>
                                                        <span class="pill falta">✗ Sin marcar</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <script>
                function mostrarPestanaBanco(indice) {
                    document.querySelectorAll('.tab-contenido').forEach(el => { el.hidden = true; });
                    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('activo'));
                    document.getElementById('tabContenido' + indice).hidden = false;
                    document.getElementById('tabBtn' + indice).classList.add('activo');
                }
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarMapeo(array $filas, string $desde, string $hasta, int $numero, int $total, string $nombreBanco, ?array $preset): void {
        // El número de columnas se toma del MÁXIMO entre todas las filas, no
        // solo de la primera: varias filas de membrete/título al inicio del
        // archivo (común en BANRURAL) traen menos celdas que las filas de
        // datos reales, y contar solo la fila 0 dejaba columnas reales (ej.
        // la H de créditos) fuera del selector — parecía que el <select> no
        // dejaba elegir nada distinto de A porque esa opción no existía.
        $totalColumnas = !empty($filas) ? max(array_map('count', $filas)) : 0;
        $totalFilas = count($filas);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Mapear columnas — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Mapear columnas del estado de cuenta</h1>

            <?php if ($total > 1): ?>
                <div class="progreso-archivos">
                    <?php for ($i = 1; $i <= $total; $i++): ?>
                        <span class="chip-archivo <?= $i < $numero ? 'hecho' : ($i === $numero ? 'actual' : '') ?>">
                            <?= $i < $numero ? '✓' : $i ?> Archivo <?= $i ?>
                        </span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>Vista previa (<?= $totalFilas ?> fila(s) leídas, primeras 15)</h2>
                <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Fila #</th>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <th><?= self::columnaLetra($c) ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($filas, 0, 15) as $i => $fila): ?>
                                <tr>
                                    <td><b><?= $i + 1 ?></b></td>
                                    <?php foreach ($fila as $celda): ?>
                                        <td><?= htmlspecialchars($celda) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="font-size:12px;color:var(--sin-datos);margin-top:10px;">
                    Verifica contra esta vista previa que las columnas precargadas abajo siguen siendo correctas — sobre todo si el banco cambió su formato de export.
                </p>
            </div>

            <div class="card">
                <h2>
                    Columnas de este archivo
                    <span class="pill ok" style="margin-left:8px;"><?= htmlspecialchars($nombreBanco) ?></span>
                </h2>
                <?php if ($preset === null): ?>
                    <p class="sin-datos">Este banco no tiene un formato conocido — indica manualmente cada columna.</p>
                <?php endif; ?>
                <form method="POST" action="conciliacion.php?action=confirmarImport">
                    <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                    <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                    <div class="fila">
                        <div class="campo">
                            <label for="fila_inicio_datos">¿En qué número de fila empiezan los datos?</label>
                            <input type="number" id="fila_inicio_datos" name="fila_inicio_datos" min="1" max="<?= $totalFilas ?>" value="<?= $preset['filaInicio'] ?? 1 ?>" required>
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="col_fecha">Columna de fecha</label>
                            <select id="col_fecha" name="col_fecha" required>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colFecha'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_credito">Columna de CRÉDITOS (dinero que entró)</label>
                            <select id="col_credito" name="col_credito">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colCredito'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_debito">Columna de DÉBITOS (dinero que salió)</label>
                            <select id="col_debito" name="col_debito">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colDebito'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_saldo">Columna de saldo (opcional)</label>
                            <select id="col_saldo" name="col_saldo">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colSaldo'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_referencia">Columna de referencia del banco (opcional)</label>
                            <select id="col_referencia" name="col_referencia">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colReferencia'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_descripcion">Columna de descripción del banco (opcional)</label>
                            <select id="col_descripcion" name="col_descripcion">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colDescripcion'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="formato_fecha">Formato de fecha en el archivo</label>
                            <select id="formato_fecha" name="formato_fecha">
                                <option value="dmy" <?= ($preset['formatoFecha'] ?? 'dmy') === 'dmy' ? 'selected' : '' ?>>DD/MM/AAAA</option>
                                <option value="mdy" <?= ($preset['formatoFecha'] ?? '') === 'mdy' ? 'selected' : '' ?>>MM/DD/AAAA</option>
                                <option value="iso" <?= ($preset['formatoFecha'] ?? '') === 'iso' ? 'selected' : '' ?>>AAAA-MM-DD</option>
                                <option value="excel_serial" <?= ($preset['formatoFecha'] ?? '') === 'excel_serial' ? 'selected' : '' ?>>Número de Excel (ej. 45540, no fecha de texto)</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="formato_monto">Formato de número en el archivo</label>
                            <select id="formato_monto" name="formato_monto">
                                <option value="punto_decimal" <?= ($preset['formatoMonto'] ?? 'punto_decimal') === 'punto_decimal' ? 'selected' : '' ?>>1,234.56 (punto decimal)</option>
                                <option value="coma_decimal" <?= ($preset['formatoMonto'] ?? '') === 'coma_decimal' ? 'selected' : '' ?>>1.234,56 (coma decimal)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Confirmar e importar</button>
                    &nbsp;
                    <a href="conciliacion.php?desde=<?= urlencode($desde) ?>&amp;hasta=<?= urlencode($hasta) ?>" style="font-size:13px;color:var(--acento);font-weight:bold;">⬅ Volver</a>
                    &nbsp;
                    <a href="conciliacion.php?action=cancelarMapeo&amp;desde=<?= urlencode($desde) ?>&amp;hasta=<?= urlencode($hasta) ?>" style="font-size:13px;color:var(--sin-datos);">Cancelar esta importación</a>
                </form>
                <p style="font-size:12px;color:var(--sin-datos);margin-top:10px;">
                    "⬅ Volver" no pierde este archivo — puedes ir a revisar "Movimientos del banco" o "Recibos pendientes" y retomar el mapeo desde la pantalla principal.
                </p>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Pantalla de vinculación manual: busca recibos pendientes y líneas
    // disponibles por separado (sin límite de fecha, a propósito — es lo
    // que permite encontrar un depósito de un período muy anterior), y
    // los vincula en un solo POST. Admite varios recibos por línea (ej.
    // una empresa que paga por 2 estudiantes con la misma transferencia).
    public static function mostrarBusquedaManual(array $recibos, array $lineas, string $carne, string $montoRecibo, string $cuentaLinea, string $montoLinea, ?string $mensaje, ?string $error): void {
        $esAdmin = Auth::esAdministrador();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Vincular manualmente — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Vincular recibo(s) con una línea del banco</h1>

            <div class="card">
                <?php if ($mensaje): ?><div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="errores">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                <p style="font-size:13px;color:var(--texto-muted);">
                    Para casos que el cruce automático no resuelve: una transferencia de un período muy anterior al que se está conciliando, o un depósito que cubre <b>varios</b> recibos a la vez (ej. una empresa transfiere Q650 para pagar 2 recibos de Q325 cada uno). Busca por separado el/los recibo(s) y la línea del banco, márcalos, y vincula — sin límite de fecha.
                </p>
                <a href="conciliacion.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Conciliación</a>
            </div>

            <div class="card">
                <h2>1. Buscar recibo(s) pendiente(s)</h2>
                <form method="GET" action="conciliacion.php">
                    <input type="hidden" name="action" value="buscarManual">
                    <input type="hidden" name="cuenta_linea" value="<?= htmlspecialchars($cuentaLinea) ?>">
                    <input type="hidden" name="monto_linea" value="<?= htmlspecialchars($montoLinea) ?>">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo">
                            <label for="carne">Carné (parcial o completo)</label>
                            <input type="text" id="carne" name="carne" value="<?= htmlspecialchars($carne) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo">
                            <label for="monto_recibo">Monto exacto del recibo</label>
                            <input type="number" step="0.01" id="monto_recibo" name="monto_recibo" value="<?= htmlspecialchars($montoRecibo) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Buscar recibos</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>2. Buscar línea del banco con saldo disponible</h2>
                <form method="GET" action="conciliacion.php">
                    <input type="hidden" name="action" value="buscarManual">
                    <input type="hidden" name="carne" value="<?= htmlspecialchars($carne) ?>">
                    <input type="hidden" name="monto_recibo" value="<?= htmlspecialchars($montoRecibo) ?>">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo">
                            <label for="cuenta_linea">Banco</label>
                            <select id="cuenta_linea" name="cuenta_linea" style="margin-bottom:0;">
                                <option value="">-- Cualquiera --</option>
                                <?php foreach (DepositosModel::cuentas() as $numeroCuenta => $banco): ?>
                                    <option value="<?= htmlspecialchars($numeroCuenta) ?>" <?= $cuentaLinea === $numeroCuenta ? 'selected' : '' ?>><?= htmlspecialchars($banco) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="monto_linea">Monto exacto de la línea</label>
                            <input type="number" step="0.01" id="monto_linea" name="monto_linea" value="<?= htmlspecialchars($montoLinea) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Buscar líneas</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!empty($recibos) || !empty($lineas)): ?>
            <div class="card">
                <h2>3. Marcar y vincular</h2>
                <?php if (!$esAdmin): ?>
                    <p class="sin-datos">Solo un administrador puede confirmar vínculos manuales — puedes buscar, pero el botón de vincular no aparecerá.</p>
                <?php endif; ?>
                <form method="POST" action="conciliacion.php?action=vincularManual">
                    <input type="hidden" name="carne" value="<?= htmlspecialchars($carne) ?>">
                    <input type="hidden" name="monto_recibo" value="<?= htmlspecialchars($montoRecibo) ?>">
                    <input type="hidden" name="cuenta_linea" value="<?= htmlspecialchars($cuentaLinea) ?>">
                    <input type="hidden" name="monto_linea" value="<?= htmlspecialchars($montoLinea) ?>">

                    <h3 style="font-size:13px;color:var(--texto);margin-bottom:8px;">Recibos pendientes encontrados (<?= count($recibos) ?>) — marca uno o varios</h3>
                    <?php if (empty($recibos)): ?>
                        <p class="sin-datos">Busca por carné o monto arriba.</p>
                    <?php else: ?>
                        <div class="tabla-scroll">
                        <table>
                            <thead><tr><th></th><th>Recibo</th><th>Carné</th><th>Fecha depósito</th><th>Monto</th><th>Cuenta destino</th></tr></thead>
                            <tbody>
                                <?php foreach ($recibos as $r): ?>
                                    <tr>
                                        <td><input type="checkbox" name="recibos[]" value="<?= (int)$r['numero'] ?>"></td>
                                        <td>#<?= (int)$r['numero'] ?></td>
                                        <td><?= htmlspecialchars($r['carne']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($r['fechadep'])) ?></td>
                                        <td class="num">Q <?= number_format((float)$r['monto'], 2) ?></td>
                                        <td><?= $r['cuenta_destino'] ? htmlspecialchars(DepositosModel::bancoDeCuenta($r['cuenta_destino']) ?? $r['cuenta_destino']) : '<span style="color:var(--sin-datos);">(sin dato)</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <h3 style="font-size:13px;color:var(--texto);margin:18px 0 8px;">Líneas del banco con saldo disponible (<?= count($lineas) ?>) — marca exactamente una</h3>
                    <?php if (empty($lineas)): ?>
                        <p class="sin-datos">Busca por banco o monto arriba.</p>
                    <?php else: ?>
                        <div class="tabla-scroll">
                        <table>
                            <thead><tr><th></th><th>Banco</th><th>Fecha</th><th>Monto línea</th><th>Disponible</th><th>Referencia</th></tr></thead>
                            <tbody>
                                <?php foreach ($lineas as $l): ?>
                                    <tr>
                                        <td><input type="radio" name="estado_cuenta_id" value="<?= (int)$l['id'] ?>"></td>
                                        <td><?= htmlspecialchars(DepositosModel::bancoDeCuenta($l['cuenta']) ?? $l['cuenta']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($l['fecha'])) ?></td>
                                        <td class="num">Q <?= number_format((float)$l['monto'], 2) ?></td>
                                        <td class="num">Q <?= number_format((float)$l['disponible'], 2) ?></td>
                                        <td><?= $l['referencia'] ? htmlspecialchars($l['referencia']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($esAdmin && !empty($recibos) && !empty($lineas)): ?>
                        <button type="submit" style="margin-top:16px;" onclick="return confirm('¿Confirmas vincular los recibos marcados con la línea marcada? Esto los marca como verificados.');">Vincular marcados</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Navegador libre de todo lo importado: responde "¿esta transferencia
    // ya está en el sistema?" sin depender de preguntarle a alguien que
    // revise la base de datos a mano.
    public static function mostrarMovimientos(array $movimientos, string $desde, string $hasta, ?string $cuenta, string $texto, ?string $tipo = null): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Movimientos del banco — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Movimientos del banco</h1>

            <div class="card">
                <a href="conciliacion.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Conciliación</a>
                <form method="GET" action="conciliacion.php" style="margin-top:14px;">
                    <input type="hidden" name="action" value="movimientos">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo" style="max-width:180px;">
                            <label for="cuenta">Banco</label>
                            <select id="cuenta" name="cuenta" style="margin-bottom:0;">
                                <option value="">-- Cualquiera --</option>
                                <?php foreach (DepositosModel::cuentas() as $numeroCuenta => $banco): ?>
                                    <option value="<?= htmlspecialchars($numeroCuenta) ?>" <?= $cuenta === $numeroCuenta ? 'selected' : '' ?>><?= htmlspecialchars($banco) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo" style="max-width:180px;">
                            <label for="desde">Desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="max-width:180px;">
                            <label for="hasta">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo">
                            <label for="texto">Buscar en referencia/descripción</label>
                            <input type="text" id="texto" name="texto" value="<?= htmlspecialchars($texto) ?>" style="margin-bottom:0;" placeholder="ej. 1831133185 o ACH">
                        </div>
                        <div class="campo" style="max-width:160px;">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" style="margin-bottom:0;">
                                <option value="">-- Todos --</option>
                                <option value="credito" <?= $tipo === 'credito' ? 'selected' : '' ?>>Solo créditos</option>
                                <option value="debito" <?= $tipo === 'debito' ? 'selected' : '' ?>>Solo débitos</option>
                            </select>
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Resultados (<?= count($movimientos) ?><?= count($movimientos) === 300 ? '+, mostrando los primeros 300' : '' ?>)</h2>
                <p style="font-size:12px;color:var(--sin-datos);margin-top:-6px;margin-bottom:14px;">
                    Consulta general del estado de cuenta importado — créditos y débitos juntos. "Estado" solo aplica a
                    créditos (si ya se explicó contra un recibo); un débito es un gasto, no algo por conciliar.
                </p>
                <?php if (empty($movimientos)): ?>
                    <p class="sin-datos">No hay movimientos con esos filtros. Si buscas una referencia específica y no aparece, esa línea aún no se ha importado al sistema.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Banco</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Monto</th>
                                <th>Saldo</th>
                                <th>Referencia</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th>Recibo(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $m): ?>
                                <?php $esDebito = $m['tipo'] === 'debito'; ?>
                                <?php $explicadaComoDeposito = !$esDebito && !$m['recibo_numero'] && !$m['recibos_manuales'] && $m['deposito_propio']; ?>
                                <tr class="<?= $esDebito ? '' : ($explicadaComoDeposito ? 'fila-propio' : ((int)$m['conciliado'] === 1 ? 'fila-ok' : ((int)$m['recibo_numero'] || $m['recibos_manuales'] ? 'fila-falta' : ''))) ?>">
                                    <td><?= htmlspecialchars(DepositosModel::bancoDeCuenta($m['cuenta']) ?? $m['cuenta']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                                    <td><?= $esDebito ? '🔴 Débito' : '🟢 Crédito' ?></td>
                                    <td class="num">Q <?= number_format((float)$m['monto'], 2) ?></td>
                                    <td class="num"><?= $m['saldo'] !== null ? 'Q ' . number_format((float)$m['saldo'], 2) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                    <td><?= $m['referencia'] ? htmlspecialchars($m['referencia']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                    <td><?= $m['descripcion'] ? htmlspecialchars($m['descripcion']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                    <td>
                                        <?php if ($esDebito): ?>
                                            <span style="color:var(--sin-datos);">— gasto —</span>
                                        <?php elseif ((int)$m['conciliado'] === 1): ?>
                                            <span class="pill ok">✓ Marcada</span>
                                        <?php elseif ($m['recibos_manuales']): ?>
                                            <span class="pill falta">⟳ Parcial</span>
                                        <?php elseif ($explicadaComoDeposito): ?>
                                            <span class="pill propio">🏢 Depósito propio</span>
                                        <?php else: ?>
                                            <span class="pill falta">✗ Sin marcar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['recibo_numero']): ?>
                                            <a href="recibospfs.php?action=ver&amp;numero=<?= (int)$m['recibo_numero'] ?>" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$m['recibo_numero'] ?></a>
                                            <?php if ($m['carne_automatico']): ?><div class="numeros">carné <?= htmlspecialchars($m['carne_automatico']) ?></div><?php endif; ?>
                                        <?php elseif ($m['recibos_manuales']): ?>
                                            <?php foreach (explode(', ', $m['recibos_manuales']) as $numRecibo): ?>
                                                <a href="recibospfs.php?action=ver&amp;numero=<?= (int)$numRecibo ?>" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$numRecibo ?></a>
                                            <?php endforeach; ?>
                                        <?php elseif ($explicadaComoDeposito): ?>
                                            <div class="numeros">
                                                boleta #<?= htmlspecialchars($m['deposito_propio']) ?><br>
                                                responsable: <?= htmlspecialchars($m['deposito_responsable']) ?><br>
                                                <?php if (abs((float)$m['deposito_efectivo'] - (float)$m['monto']) > 0.01): ?>
                                                    <span style="color:var(--estado-falta-texto);">⚠ boleta Q<?= number_format((float)$m['deposito_efectivo'], 2) ?>, banco Q<?= number_format((float)$m['monto'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--sin-datos);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Reporte de avance: qué se ha verificado y cuándo, con totales por
    // banco y un desglose automático/manual — responde "¿cómo va quedando
    // el trabajo de conciliación?" sin tener que contar a mano.
    // Gráfica de barras verticales: monto "sin marcar" por mes, últimos N
    // meses — para ver si el rezago de posibles pagos sin reportar crece,
    // se mantiene o baja en el tiempo, no solo un corte puntual del filtro
    // del reporte. Mismo patrón de marca (barras redondeadas, hover con
    // tooltip, color ámbar = mismo que la pill "Sin marcar") que el resto
    // del proyecto (ver Dashboard).
    private static function graficaTendenciaSinMarcar(array $tendencia): string {
        $ancho = 760; $alto = 220;
        $margenIzq = 58; $margenDer = 16; $margenArriba = 16; $margenAbajo = 30;
        $anchoPlot = $ancho - $margenIzq - $margenDer;
        $altoPlot = $alto - $margenArriba - $margenAbajo;

        if (empty($tendencia)) {
            return '';
        }

        $max = max(array_column($tendencia, 'total'));
        $max = $max > 0 ? $max * 1.15 : 100; // 15% de aire arriba de la barra más alta

        $n = count($tendencia);
        $anchoBanda = $anchoPlot / $n;
        $anchoBarra = min(40, $anchoBanda * 0.5);

        $svg = '<svg viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" aria-label="Monto sin marcar por mes" style="width:100%;height:auto;font-family:inherit;">';
        $svg .= '<rect x="0" y="0" width="' . $ancho . '" height="' . $alto . '" fill="var(--bg-tarjeta)"/>';

        for ($i = 0; $i <= 3; $i++) {
            $y = $margenArriba + $altoPlot - ($altoPlot * $i / 3);
            $valor = $max * $i / 3;
            $svg .= '<line x1="' . $margenIzq . '" y1="' . round($y, 1) . '" x2="' . ($ancho - $margenDer) . '" y2="' . round($y, 1) . '" stroke="var(--tabla-borde)" stroke-width="1"/>';
            $etiqueta = $valor >= 1000 ? 'Q' . number_format($valor / 1000, 1) . 'K' : 'Q' . number_format($valor, 0);
            $svg .= '<text x="' . ($margenIzq - 8) . '" y="' . round($y + 3, 1) . '" text-anchor="end" font-size="10.5" fill="var(--sin-datos)">' . $etiqueta . '</text>';
        }

        foreach ($tendencia as $idx => $fila) {
            $xCentro = $margenIzq + $anchoBanda * $idx + $anchoBanda / 2;
            $xBarra = $xCentro - $anchoBarra / 2;
            $valor = (float)$fila['total'];
            $altoBarra = $max > 0 ? ($valor / $max) * $altoPlot : 0;
            $y = $margenArriba + $altoPlot - $altoBarra;
            $r = min(4, $anchoBarra / 2, $altoBarra / 2);

            $mesTexto = date('M Y', strtotime($fila['mes'] . '-01'));
            $titulo = ucfirst($mesTexto) . ': Q' . number_format($valor, 2) . ' (' . (int)$fila['cantidad'] . ' línea(s))';

            // rx redondea las 4 esquinas (no solo las de arriba) — aceptable
            // aquí porque las barras son cortas y angostas; se evita si la
            // barra es más baja que 2×radio, para no deformarla.
            $rx = $altoBarra >= $r * 2 ? round($r, 1) : 0;
            $svg .= '<rect x="' . round($xBarra, 1) . '" y="' . round($y, 1) . '" width="' . round($anchoBarra, 1) . '" height="' . round(max(1, $altoBarra), 1) . '" rx="' . $rx . '" fill="var(--estado-falta-texto)" class="viz-mark" tabindex="0" data-tooltip="' . htmlspecialchars($titulo) . '"><title>' . htmlspecialchars($titulo) . '</title></rect>';

            $svg .= '<text x="' . round($xCentro, 1) . '" y="' . ($alto - 10) . '" text-anchor="middle" font-size="10.5" fill="var(--sin-datos)">' . htmlspecialchars(ucfirst($mesTexto)) . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    public static function mostrarReporte(array $verificados, string $desde, string $hasta, ?string $cuenta, array $resumenMovimientos, array $tendenciaSinMarcar): void {
        $totalMonto = array_sum(array_column($verificados, 'monto'));
        $porBanco = [];
        foreach ($verificados as $v) {
            $b = $v['cuenta_destino'] ?? '(sin dato)';
            $porBanco[$b] = ($porBanco[$b] ?? 0) + (float)$v['monto'];
        }
        $manuales = count(array_filter($verificados, fn($v) => !empty($v['es_manual'])));
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Reporte de verificados — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Reporte de recibos verificados</h1>

            <div class="card">
                <a href="conciliacion.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Conciliación</a>
                <form method="GET" action="conciliacion.php" style="margin-top:14px;">
                    <input type="hidden" name="action" value="reporte">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo" style="max-width:180px;">
                            <label for="cuenta">Banco</label>
                            <select id="cuenta" name="cuenta" style="margin-bottom:0;">
                                <option value="">-- Cualquiera --</option>
                                <?php foreach (DepositosModel::cuentas() as $numeroCuenta => $banco): ?>
                                    <option value="<?= htmlspecialchars($numeroCuenta) ?>" <?= $cuenta === $numeroCuenta ? 'selected' : '' ?>><?= htmlspecialchars($banco) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo" style="max-width:180px;">
                            <label for="desde">Verificado desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="max-width:180px;">
                            <label for="hasta">hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>" style="margin-bottom:0;">
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;">
                    <div class="kpi" style="background:var(--bg-pagina);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--texto-muted);margin-bottom:6px;">Recibos verificados</div>
                        <div style="font-size:24px;font-weight:600;color:var(--acento);"><?= count($verificados) ?></div>
                    </div>
                    <div class="kpi" style="background:var(--bg-pagina);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--texto-muted);margin-bottom:6px;">Monto total</div>
                        <div style="font-size:24px;font-weight:600;color:var(--acento);">Q <?= number_format($totalMonto, 2) ?></div>
                    </div>
                    <div class="kpi" style="background:var(--bg-pagina);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--texto-muted);margin-bottom:6px;">Vinculados manualmente</div>
                        <div style="font-size:24px;font-weight:600;color:var(--acento);"><?= $manuales ?></div>
                    </div>
                    <?php foreach ($porBanco as $b => $monto): ?>
                        <div class="kpi" style="background:var(--bg-pagina);border-radius:8px;padding:16px 18px;">
                            <div style="font-size:12px;font-weight:bold;color:var(--texto-muted);margin-bottom:6px;"><?= htmlspecialchars(DepositosModel::bancoDeCuenta($b) ?? $b) ?></div>
                            <div style="font-size:24px;font-weight:600;color:var(--acento);">Q <?= number_format($monto, 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2>Estado de las líneas del banco (mismo período del filtro)</h2>
                <p style="font-size:12px;color:var(--sin-datos);margin:-4px 0 14px;">
                    "Sin marcar" son probablemente estudiantes que ya transfirieron pero aún no han venido a pedir su recibo, o depósitos propios cuya boleta todavía no se ha ingresado.
                </p>
                <div class="kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;">
                    <div class="kpi" style="background:var(--estado-ok-bg);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--estado-ok-texto);margin-bottom:6px;">✓ Marcadas</div>
                        <div style="font-size:24px;font-weight:600;color:var(--estado-ok-texto);"><?= $resumenMovimientos['marcada']['cantidad'] ?></div>
                        <div style="font-size:12px;color:var(--estado-ok-texto);">Q <?= number_format($resumenMovimientos['marcada']['total'], 2) ?></div>
                    </div>
                    <div class="kpi" style="background:var(--estado-propio-bg);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--estado-propio-texto);margin-bottom:6px;">🏢 Depósito propio</div>
                        <div style="font-size:24px;font-weight:600;color:var(--estado-propio-texto);"><?= $resumenMovimientos['deposito_propio']['cantidad'] ?></div>
                        <div style="font-size:12px;color:var(--estado-propio-texto);">Q <?= number_format($resumenMovimientos['deposito_propio']['total'], 2) ?></div>
                    </div>
                    <div class="kpi" style="background:var(--estado-falta-bg);border-radius:8px;padding:16px 18px;">
                        <div style="font-size:12px;font-weight:bold;color:var(--estado-falta-texto);margin-bottom:6px;">⟳ Parcial</div>
                        <div style="font-size:24px;font-weight:600;color:var(--estado-falta-texto);"><?= $resumenMovimientos['parcial']['cantidad'] ?></div>
                        <div style="font-size:12px;color:var(--estado-falta-texto);">Q <?= number_format($resumenMovimientos['parcial']['total'], 2) ?></div>
                    </div>
                    <div class="kpi" style="background:var(--estado-falta-bg);border-radius:8px;padding:16px 18px;border:2px solid var(--estado-falta-borde);">
                        <div style="font-size:12px;font-weight:bold;color:var(--estado-falta-texto);margin-bottom:6px;">✗ Sin marcar</div>
                        <div style="font-size:24px;font-weight:600;color:var(--estado-falta-texto);"><?= $resumenMovimientos['sin_marcar']['cantidad'] ?></div>
                        <div style="font-size:12px;color:var(--estado-falta-texto);">Q <?= number_format($resumenMovimientos['sin_marcar']['total'], 2) ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Tendencia de líneas sin marcar (últimos 6 meses)</h2>
                <?php if (empty($tendenciaSinMarcar)): ?>
                    <p class="sin-datos">No hay líneas sin marcar en los últimos 6 meses.</p>
                <?php else: ?>
                    <?= self::graficaTendenciaSinMarcar($tendenciaSinMarcar) ?>
                    <button type="button" class="tabla-toggle" onclick="document.getElementById('tablaTendencia').hidden = !document.getElementById('tablaTendencia').hidden">Ver tabla de datos</button>
                    <table class="datos" id="tablaTendencia" hidden style="margin-top:10px;">
                        <thead><tr><th>Mes</th><th>Líneas sin marcar</th><th>Monto</th></tr></thead>
                        <tbody>
                            <?php foreach ($tendenciaSinMarcar as $t): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucfirst(date('M Y', strtotime($t['mes'] . '-01')))) ?></td>
                                    <td class="num"><?= (int)$t['cantidad'] ?></td>
                                    <td class="num">Q <?= number_format((float)$t['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Detalle</h2>
                <?php if (empty($verificados)): ?>
                    <p class="sin-datos">No hay recibos verificados en este período.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Carné</th>
                                <th>Fecha depósito</th>
                                <th>Monto</th>
                                <th>Banco</th>
                                <th>Verificado por</th>
                                <th>Fecha de verificación</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verificados as $v): ?>
                                <tr>
                                    <td><a href="recibospfs.php?action=ver&amp;numero=<?= (int)$v['numero'] ?>" style="color:var(--acento);font-weight:bold;text-decoration:none;">#<?= (int)$v['numero'] ?></a></td>
                                    <td><?= htmlspecialchars($v['carne']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($v['fechadep'])) ?></td>
                                    <td class="num">Q <?= number_format((float)$v['monto'], 2) ?></td>
                                    <td><?= $v['cuenta_destino'] ? htmlspecialchars(DepositosModel::bancoDeCuenta($v['cuenta_destino']) ?? $v['cuenta_destino']) : '<span style="color:var(--sin-datos);">(sin dato)</span>' ?></td>
                                    <td><?= htmlspecialchars($v['verificado_por'] ?? '') ?></td>
                                    <td><?= $v['fecha_verificacion'] ? date('d/m/Y H:i', strtotime($v['fecha_verificacion'])) : '—' ?></td>
                                    <td>
                                        <?php if (!empty($v['es_manual'])): ?>
                                            <span class="pill falta">Manual</span>
                                        <?php else: ?>
                                            <span class="pill ok">Automático</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="viz-tooltip" id="vizTooltip"></div>
            <script>
                const tooltip = document.getElementById('vizTooltip');
                function mostrarTooltip(ev, texto) {
                    tooltip.textContent = texto;
                    tooltip.style.display = 'block';
                    const x = ev.clientX ?? ev.target.getBoundingClientRect().right;
                    const y = ev.clientY ?? ev.target.getBoundingClientRect().top;
                    tooltip.style.left = Math.min(x + 14, window.innerWidth - 250) + 'px';
                    tooltip.style.top = Math.max(y - 10, 10) + 'px';
                }
                function ocultarTooltip() { tooltip.style.display = 'none'; }
                document.querySelectorAll('.viz-mark').forEach(el => {
                    const texto = el.getAttribute('data-tooltip');
                    el.addEventListener('pointermove', ev => mostrarTooltip(ev, texto));
                    el.addEventListener('pointerleave', ocultarTooltip);
                    el.addEventListener('focus', ev => mostrarTooltip(ev, texto));
                    el.addEventListener('blur', ocultarTooltip);
                });
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    private static array $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private static function selectorAnio(string $action, int $anio): void {
        ?>
        <form method="GET" action="conciliacion.php" style="margin-bottom:16px;">
            <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
            <div class="fila" style="align-items:flex-end;">
                <div class="campo" style="max-width:140px;">
                    <label for="anio">Año</label>
                    <select id="anio" name="anio" onchange="this.form.submit()" style="margin-bottom:0;">
                        <?php for ($a = (int)date('Y'); $a >= 2020; $a--): ?>
                            <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </form>
        <?php
    }

    // $porBanco: [cuenta => ['banco'=>string, 'sistema'=>[mes=>total], 'estadoCuenta'=>[mes=>['credito'=>x,'debito'=>y]]]]
    // (ver ConciliacionController::comparativo).
    public static function mostrarComparativo(array $porBanco, int $anio): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Comparativo Mensual — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Comparativo Mensual</h1>

            <div class="card">
                <a href="conciliacion.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Conciliación</a>
                <p style="font-size:12.5px;color:var(--texto-muted,var(--sin-datos));margin:12px 0 0;">
                    Depósitos registrados en el sistema (mismo dato que "Totales por mes" de Cierre del Año) vs. créditos
                    del estado de cuenta ya importado para esa cuenta. Una diferencia pequeña es normal — el banco puede
                    acreditar 1-2 días después de que se registró el recibo; una diferencia grande probablemente significa
                    que falta importar el estado de cuenta de ese mes, o que hay recibos sin conciliar todavía.
                </p>
                <?php self::selectorAnio('comparativo', $anio); ?>
            </div>

            <?php foreach ($porBanco as $cuenta => $datos): ?>
                <?php
                    $totalSistema = array_sum($datos['sistema']);
                    $totalBanco = 0.0;
                    foreach ($datos['estadoCuenta'] as $m) { $totalBanco += $m['credito']; }
                ?>
                <div class="card">
                    <h2><?= htmlspecialchars($datos['banco']) ?> <span style="font-size:12px;color:var(--sin-datos);font-weight:normal;">(cuenta <?= htmlspecialchars($cuenta) ?>)</span></h2>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr><th>Mes</th><th>Depósitos del sistema</th><th>Créditos del banco</th><th>Diferencia</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php
                                    $sistema = (float)$datos['sistema'][$m];
                                    $banco = (float)$datos['estadoCuenta'][$m]['credito'];
                                    $diferencia = $banco - $sistema;
                                ?>
                                <tr>
                                    <td><?= self::$meses[$m] ?></td>
                                    <td class="num">Q <?= number_format($sistema, 2) ?></td>
                                    <td class="num">Q <?= number_format($banco, 2) ?></td>
                                    <td class="num" style="<?= abs($diferencia) > 0.01 ? 'color:var(--estado-falta-texto);font-weight:bold;' : 'color:var(--sin-datos);' ?>">
                                        <?= abs($diferencia) > 0.01 ? ($diferencia > 0 ? '+' : '') . 'Q ' . number_format($diferencia, 2) : '✓ cuadra' ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:bold;">
                                <td>Total <?= $anio ?></td>
                                <td class="num">Q <?= number_format($totalSistema, 2) ?></td>
                                <td class="num">Q <?= number_format($totalBanco, 2) ?></td>
                                <td class="num">Q <?= number_format($totalBanco - $totalSistema, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // $porBanco: [cuenta => ['banco'=>string, 'totales'=>[mes=>['credito'=>x,'debito'=>y]]]] (ver
    // ConciliacionController::gastos). Solo informativo — no hay nada en el sistema contra qué comparar un gasto.
    public static function mostrarGastos(array $porBanco, int $anio): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Gastos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Gastos</h1>

            <div class="card">
                <a href="conciliacion.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Conciliación</a>
                <p style="font-size:12.5px;color:var(--texto-muted,var(--sin-datos));margin:12px 0 0;">
                    Débitos (dinero que salió) del estado de cuenta ya importado — solo informativo, el sistema no lleva
                    registro de egresos aparte del banco. Para ver el detalle línea por línea de un mes, usa
                    "Movimientos del banco" filtrando por tipo "Solo débitos".
                </p>
                <?php self::selectorAnio('gastos', $anio); ?>
            </div>

            <?php foreach ($porBanco as $cuenta => $datos): ?>
                <?php $totalAnual = 0.0; foreach ($datos['totales'] as $m) { $totalAnual += $m['debito']; } ?>
                <div class="card">
                    <h2><?= htmlspecialchars($datos['banco']) ?> <span style="font-size:12px;color:var(--sin-datos);font-weight:normal;">(cuenta <?= htmlspecialchars($cuenta) ?>)</span></h2>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr><th>Mes</th><th>Débitos (gasto)</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <tr>
                                    <td><?= self::$meses[$m] ?></td>
                                    <td class="num">Q <?= number_format((float)$datos['totales'][$m]['debito'], 2) ?></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:bold;">
                                <td>Total <?= $anio ?></td>
                                <td class="num">Q <?= number_format($totalAnual, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <p style="margin-top:10px;">
                        <a href="conciliacion.php?action=movimientos&amp;cuenta=<?= urlencode($cuenta) ?>&amp;tipo=debito&amp;desde=<?= $anio ?>-01-01&amp;hasta=<?= $anio ?>-12-31" style="font-size:13px;color:var(--acento);font-weight:bold;">Ver detalle de débitos de <?= htmlspecialchars($datos['banco']) ?> →</a>
                    </p>
                </div>
            <?php endforeach; ?>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
