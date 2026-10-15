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
            .pill.ok { background: #e8f5e9; color: #2e7d32; }
            .pill.falta { background: #fff8e1; color: #f57f17; }
            .numeros { font-size: 11.5px; color: var(--sin-datos); margin-top: 2px; }
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

    public static function mostrarPrincipal(array $grupos, ?string $mensaje, ?string $error, string $desde): void {
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

                <h2>Subir estado de cuenta (CSV)</h2>
                <form method="POST" action="conciliacion.php?action=subir" enctype="multipart/form-data">
                    <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                    <div class="fila">
                        <div class="campo">
                            <label for="archivo">Archivo del banco: CSV, Excel .xlsx, o .xls exportado como tabla HTML (máx. 3 MB)</label>
                            <input type="file" id="archivo" name="archivo"
                                   accept=".csv,text/csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                   required>
                        </div>
                    </div>
                    <button type="submit">Subir y mapear columnas →</button>
                </form>
                <p style="font-size:12px;color:#888;margin-top:10px;">
                    El sistema detecta el formato real del archivo (no la extensión) — funciona con CSV, .xlsx real, y con los ".xls" que en realidad son una tabla HTML (común en exports de banca en línea). Solo el .xls binario real de Excel 97-2003 no se puede leer directo.
                </p>
            </div>

            <div class="card">
                <h2>Recibos pendientes de verificar</h2>
                <form method="GET" action="conciliacion.php" style="margin-bottom:16px;">
                    <div class="fila" style="align-items:flex-end;">
                        <div class="campo" style="max-width:220px;">
                            <label for="desde">Revisar desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>" style="margin-bottom:0;">
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
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($g['fecha'])) ?></td>
                                    <td class="num">Q <?= number_format($g['monto'], 2) ?></td>
                                    <td>
                                        <?php if ($g['cuenta_destino']): ?>
                                            <?= htmlspecialchars(DepositosModel::bancoDeCuenta($g['cuenta_destino']) ?? $g['cuenta_destino']) ?>
                                        <?php else: ?>
                                            <span style="color:#999;">(sin dato)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= (int)$g['cantidad'] ?>
                                        <div class="numeros">recibos: <?= htmlspecialchars($g['numeros']) ?></div>
                                    </td>
                                    <td><?= (int)$g['disponibles'] ?></td>
                                    <td>
                                        <?php if ($g['sugerido']): ?>
                                            <span class="pill ok">Sugerido</span>
                                        <?php else: ?>
                                            <span class="pill falta">Falta importar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($g['sugerido'] && $esAdmin): ?>
                                            <form method="POST" action="conciliacion.php?action=verificar" onsubmit="return confirm('¿Confirmas que estas <?= (int)$g['cantidad'] ?> transferencia(s) del <?= date('d/m/Y', strtotime($g['fecha'])) ?> por Q <?= number_format($g['monto'], 2) ?> están en el estado de cuenta?');">
                                                <input type="hidden" name="fecha" value="<?= htmlspecialchars($g['fecha']) ?>">
                                                <input type="hidden" name="monto" value="<?= htmlspecialchars($g['monto']) ?>">
                                                <input type="hidden" name="cuenta" value="<?= htmlspecialchars($g['cuenta_destino'] ?? '') ?>">
                                                <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
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
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarMapeo(array $filas, string $desde): void {
        $primeraFila = $filas[0] ?? [];
        $totalColumnas = count($primeraFila);
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
                <p style="font-size:12px;color:#888;margin-top:10px;">
                    Algunos bancos (ej. BANRURAL) traen varias filas de membrete/metadatos antes de los títulos de columna. Fíjate en qué número de fila empiezan los datos reales (montos y fechas) y anótalo abajo.
                </p>
            </div>

            <div class="card">
                <h2>Indica qué columna es cada dato</h2>
                <form method="POST" action="conciliacion.php?action=confirmarImport">
                    <input type="hidden" name="desde" value="<?= htmlspecialchars($desde) ?>">
                    <div class="fila">
                        <div class="campo">
                            <label for="fila_inicio_datos">¿En qué número de fila empiezan los datos?</label>
                            <input type="number" id="fila_inicio_datos" name="fila_inicio_datos" min="1" max="<?= $totalFilas ?>" value="1" required>
                        </div>
                        <div class="campo">
                            <label for="cuenta">Cuenta bancaria de este estado de cuenta</label>
                            <select id="cuenta" name="cuenta" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach (DepositosModel::cuentas() as $numero => $banco): ?>
                                    <option value="<?= htmlspecialchars($numero) ?>"><?= htmlspecialchars($numero) ?> — <?= htmlspecialchars($banco) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="col_fecha">Columna de fecha</label>
                            <select id="col_fecha" name="col_fecha" required>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>">Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_monto">Columna de monto</label>
                            <select id="col_monto" name="col_monto" required>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>">Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="col_referencia">Columna de referencia (opcional)</label>
                            <select id="col_referencia" name="col_referencia">
                                <option value="">-- Ninguna --</option>
                                <?php for ($c = 0; $c < $totalColumnas; $c++): ?>
                                    <option value="<?= $c ?>">Columna <?= self::columnaLetra($c) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="formato_fecha">Formato de fecha en el archivo</label>
                            <select id="formato_fecha" name="formato_fecha">
                                <option value="dmy">DD/MM/AAAA</option>
                                <option value="mdy">MM/DD/AAAA</option>
                                <option value="iso">AAAA-MM-DD</option>
                                <option value="excel_serial">Número de Excel (ej. 45540, no fecha de texto)</option>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="formato_monto">Formato de número en el archivo</label>
                            <select id="formato_monto" name="formato_monto">
                                <option value="punto_decimal">1,234.56 (punto decimal)</option>
                                <option value="coma_decimal">1.234,56 (coma decimal)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Confirmar e importar</button>
                    &nbsp;
                    <a href="conciliacion.php?desde=<?= urlencode($desde) ?>" style="font-size:13px;color:#1a237e;font-weight:bold;">Cancelar</a>
                </form>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
