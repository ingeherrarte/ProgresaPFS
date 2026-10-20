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
            tr.fila-ok td { background: var(--fila-ok-bg); }
            tr.fila-falta td { background: var(--fila-falta-bg); }
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

    public static function mostrarPrincipal(array $grupos, array $lineas, ?string $mensaje, ?string $error, string $desde, string $hasta, string $desdeSubida, string $hastaSubida): void {
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

            <div class="card">
                <h2>Recibos pendientes de verificar</h2>
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
                <?php if (empty($grupos)): ?>
                    <p class="sin-datos">No hay recibos pagados por depósito/transferencia pendientes de verificar.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha depósito</th>
                                <th>Monto</th>
                                <th>Cuenta destino</th>
                                <th>Recibos pendientes</th>
                                <th>Líneas disponibles en estado de cuenta</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $g): ?>
                                <tr class="<?= $g['sugerido'] ? 'fila-ok' : 'fila-falta' ?>">
                                    <td><?= date('d/m/Y', strtotime($g['fecha'])) ?></td>
                                    <td class="num">Q <?= number_format($g['monto'], 2) ?></td>
                                    <td>
                                        <?php if ($g['cuenta_destino']): ?>
                                            <?= htmlspecialchars(DepositosModel::bancoDeCuenta($g['cuenta_destino']) ?? $g['cuenta_destino']) ?>
                                        <?php else: ?>
                                            <span style="color:var(--sin-datos);">(sin dato)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int)$g['cantidad'] ?>
                                        <div class="numeros">recibos: <?= htmlspecialchars($g['numeros']) ?></div>
                                    </td>
                                    <td><?= (int)$g['disponibles'] ?></td>
                                    <td>
                                        <?php if ($g['sugerido']): ?>
                                            <span class="pill ok">✓ Sugerido</span>
                                        <?php else: ?>
                                            <span class="pill falta">✗ Falta importar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($g['sugerido'] && $esAdmin): ?>
                                            <form method="POST" action="conciliacion.php?action=verificar" onsubmit="return confirm('¿Confirmas que estas <?= (int)$g['cantidad'] ?> transferencia(s) del <?= date('d/m/Y', strtotime($g['fecha'])) ?> por Q <?= number_format($g['monto'], 2) ?> están en el estado de cuenta?');">
                                                <input type="hidden" name="fecha" value="<?= htmlspecialchars($g['fecha']) ?>">
                                                <input type="hidden" name="monto" value="<?= htmlspecialchars($g['monto']) ?>">
                                                <input type="hidden" name="cuenta" value="<?= htmlspecialchars($g['cuenta_destino'] ?? '') ?>">
                                                <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                                                <input type="hidden" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
                                                <button type="submit" class="chico">Verificar</button>
                                            </form>
                                        <?php elseif ($g['sugerido']): ?>
                                            <span style="font-size:12px;color:#888;">Requiere admin</span>
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
                                                        <?php if ($l['carne']): ?>
                                                            <div class="numeros">carné <?= htmlspecialchars($l['carne']) ?></div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span style="color:var(--sin-datos);">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int)$l['conciliado'] === 1): ?>
                                                        <span class="pill ok">✓ Marcada</span>
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
                            <label for="col_monto">Columna de monto: la de CRÉDITOS (dinero que entró)</label>
                            <select id="col_monto" name="col_monto" required>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>" <?= ($preset['colMonto'] ?? null) === $c ? 'selected' : '' ?>>Columna <?= self::columnaLetra($c) ?></option>
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
                    <a href="conciliacion.php?desde=<?= urlencode($desde) ?>&amp;hasta=<?= urlencode($hasta) ?>" style="font-size:13px;color:var(--acento);font-weight:bold;">Cancelar</a>
                </form>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
