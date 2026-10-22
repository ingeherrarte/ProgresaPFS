<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/DepositosModel.php";
require_once __DIR__ . "/../helpers/Tema.php";

class DepositosView {

    public static function mostrarFormulario(array $errores, array $data, array $recientes, ?string $mensaje = null): void {
        $v = fn($campo, $default = '') => htmlspecialchars($data[$campo] ?? $default);
        $cuentaSel = $data['cuenta'] ?? '';
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Depósitos — CETECPRO</title>
            <link rel="manifest" href="manifest.json">
            <meta name="theme-color" content="#1a237e">
            <link rel="apple-touch-icon" href="pwa-icons/apple-touch-icon.png">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
            <meta name="apple-mobile-web-app-title" content="CETECPRO">
            <?php Tema::scriptInicial(); ?>
            <style>
                <?php Tema::tokensBase(); ?>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 900px; margin: 0 auto 16px;
                }
                .barra .usuario { font-size: 13px; color: var(--texto-muted); }
                .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                <?php Tema::estiloBoton(); ?>
                h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
                .card {
                    background: var(--bg-tarjeta); max-width: 900px; margin: 0 auto 24px;
                    border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
                }
                /* El listado tiene 11 columnas y necesita más ancho que el
                   formulario para no forzar scroll horizontal en pantallas grandes. */
                .card-ancha { max-width: 1300px; }
                fieldset { border: 1px solid var(--borde-input); border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
                legend { font-size: 13px; font-weight: bold; color: var(--acento); padding: 0 6px; }
                /* Mobile-first: un campo por renglón, más fácil de llenar con el
                   pulgar; a partir de 640px se acomodan varios por fila. */
                .fila { display: flex; flex-direction: column; gap: 14px; margin-bottom: 14px; }
                .campo { flex: 1; min-width: 0; }
                label { display: block; font-size: 13px; font-weight: bold; color: var(--texto); margin-bottom: 5px; }
                input, select {
                    width: 100%; padding: 12px 10px; font-size: 16px;
                    border: 1px solid var(--borde-input); border-radius: 4px;
                    background: var(--bg-tarjeta); color: var(--texto);
                }
                /* font-size 16px en inputs evita que Safari/Chrome en iPhone
                   hagan zoom automático al enfocar el campo. */
                input:focus, select:focus { outline: none; border-color: var(--acento); }
                input[type=file] { padding: 10px 8px; }
                button[type=submit] {
                    width: 100%; padding: 15px; font-size: 16px; font-weight: bold;
                    background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
                }
                button[type=submit]:hover { background: #283593; }
                .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
                @media (min-width: 640px) {
                    .fila { flex-direction: row; flex-wrap: wrap; }
                }
                .errores {
                    background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                    padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
                }
                .errores ul { margin: 6px 0 0 18px; }
                .exito {
                    background: #e8f5e9; border: 1px solid #81c784; color: #2e7d32;
                    padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
                }
                table { border-collapse: collapse; width: 100%; font-size: 13px; }
                th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
                th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
                td { color: var(--texto); }
                td.num { text-align: right; font-family: 'Courier New', monospace; }
                tbody tr:nth-child(even) { background: var(--fila-alterna); }
                .sin-datos { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
                h2 { font-size: 15px; color: var(--acento); margin: 0 0 12px; }
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="inicio.php">Inicio</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                    <?php Tema::boton(); ?>
                </span>
            </div>
            <h1>Ingreso de Boletas de Depósito</h1>

            <div class="card">
                <?php if ($mensaje): ?>
                    <div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>

                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ Corrige lo siguiente:
                        <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="depositos.php?action=guardar" enctype="multipart/form-data">
                    <fieldset>
                        <legend>Datos del depósito</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="nodeposito">No. de depósito</label>
                                <input type="text" id="nodeposito" name="nodeposito" inputmode="numeric" maxlength="11" value="<?= $v('nodeposito') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="fechadep">Fecha de depósito</label>
                                <input type="date" id="fechadep" name="fechadep" value="<?= $v('fechadep', date('Y-m-d')) ?>" required>
                            </div>
                            <div class="campo">
                                <label for="cuenta">Cuenta</label>
                                <select id="cuenta" name="cuenta" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (DepositosModel::cuentas() as $numero => $banco): ?>
                                        <option value="<?= htmlspecialchars($numero) ?>" <?= $numero === $cuentaSel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($numero) ?> — <?= htmlspecialchars($banco) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="efectivo">Efectivo</label>
                                <input type="number" id="efectivo" name="efectivo" inputmode="decimal" step="0.01" min="0" value="<?= $v('efectivo', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chpropio">Cheque propio banco</label>
                                <input type="number" id="chpropio" name="chpropio" inputmode="decimal" step="0.01" min="0" value="<?= $v('chpropio', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chotrobanco">Cheque otros bancos</label>
                                <input type="number" id="chotrobanco" name="chotrobanco" inputmode="decimal" step="0.01" min="0" value="<?= $v('chotrobanco', '0.00') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="correspondiente">Correspondiente a</label>
                                <input type="date" id="correspondiente" name="correspondiente" value="<?= $v('correspondiente', date('Y-m-d')) ?>" required>
                            </div>
                            <div class="campo">
                                <label for="responsable">Responsable</label>
                                <input type="text" id="responsable" name="responsable" maxlength="30" value="<?= $v('responsable') ?>" required>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="foto_boleta">Foto de la boleta (opcional, máx. 6 MB)</label>
                                <input type="file" id="foto_boleta" name="foto_boleta" accept="image/jpeg,image/png,image/webp" capture="environment">
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit">Guardar depósito</button>
                </form>
            </div>

            <div class="card card-ancha">
                <h2>Últimos depósitos registrados</h2>
                <?php if (empty($recientes)): ?>
                    <p class="sin-datos">No hay depósitos registrados.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Depósito</th>
                                <th>Fecha</th>
                                <th>Cuenta</th>
                                <th>Banco</th>
                                <th>Correspondiente</th>
                                <th>Efectivo</th>
                                <th>Ch. propio</th>
                                <th>Ch. otro banco</th>
                                <th>Responsable</th>
                                <th>Registrado</th>
                                <th>Boleta</th>
                                <?php if (Auth::esAdministrador()): ?>
                                    <th></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recientes as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['nodeposito']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($d['fechadep'])) ?></td>
                                    <td><?= htmlspecialchars($d['cuenta']) ?></td>
                                    <td><?= htmlspecialchars($d['banco']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($d['correspondiente'])) ?></td>
                                    <td class="num">Q <?= number_format($d['efectivo'], 2) ?></td>
                                    <td class="num">Q <?= number_format($d['chpropio'], 2) ?></td>
                                    <td class="num">Q <?= number_format($d['chotrobanco'], 2) ?></td>
                                    <td><?= htmlspecialchars($d['responsable']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($d['horaregistro'])) ?></td>
                                    <td>
                                        <?php if (!empty($d['foto_boleta'])): ?>
                                            <a href="uploads/depositos/<?= urlencode($d['foto_boleta']) ?>" target="_blank" rel="noopener">Ver foto</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <?php if (Auth::esAdministrador()): ?>
                                        <td><a href="depositos.php?action=editar&nodeposito=<?= urlencode($d['nodeposito']) ?>">✏️ Editar</a></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js'));
                }
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Solo accesible a administrador (gateado en el controlador). Reutiliza
    // la misma identificación por nodeposito que el alta: no hay columna
    // `id` en la tabla, así que el número ORIGINAL viaja en un campo oculto
    // para poder localizar la fila incluso si el admin corrige el número.
    public static function mostrarEdicion(array $errores, array $data, array $original): void {
        $v = fn($campo, $default = '') => htmlspecialchars($data[$campo] ?? $default);
        $cuentaSel = $data['cuenta'] ?? $original['cuenta'];
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Editar depósito — CETECPRO</title>
            <meta name="theme-color" content="#1a237e">
            <?php Tema::scriptInicial(); ?>
            <style>
                <?php Tema::tokensBase(); ?>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 700px; margin: 0 auto 16px;
                }
                .barra .usuario { font-size: 13px; color: var(--texto-muted); }
                .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                <?php Tema::estiloBoton(); ?>
                h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
                .card {
                    background: var(--bg-tarjeta); max-width: 700px; margin: 0 auto 24px;
                    border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
                }
                fieldset { border: 1px solid var(--borde-input); border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
                legend { font-size: 13px; font-weight: bold; color: var(--acento); padding: 0 6px; }
                .fila { display: flex; flex-direction: column; gap: 14px; margin-bottom: 14px; }
                .campo { flex: 1; min-width: 0; }
                label { display: block; font-size: 13px; font-weight: bold; color: var(--texto); margin-bottom: 5px; }
                input, select {
                    width: 100%; padding: 12px 10px; font-size: 16px;
                    border: 1px solid var(--borde-input); border-radius: 4px;
                    background: var(--bg-tarjeta); color: var(--texto);
                }
                input:focus, select:focus { outline: none; border-color: var(--acento); }
                button[type=submit] {
                    width: 100%; padding: 15px; font-size: 16px; font-weight: bold;
                    background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
                }
                button[type=submit]:hover { background: #283593; }
                @media (min-width: 640px) {
                    .fila { flex-direction: row; flex-wrap: wrap; }
                }
                .errores {
                    background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                    padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
                }
                .errores ul { margin: 6px 0 0 18px; }
                .aviso {
                    background: #fff3e0; border: 1px solid #ffb74d; color: #e65100;
                    padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
                }
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="depositos.php">Depósitos</a>
                    <a href="inicio.php">Inicio</a>
                    <?php Tema::boton(); ?>
                </span>
            </div>
            <h1>Editar depósito</h1>

            <div class="card">
                <div class="aviso">🔒 Edición de administrador — corrige un depósito ya registrado (ej. error de digitación en el monto).</div>

                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ Corrige lo siguiente:
                        <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="depositos.php?action=actualizar">
                    <input type="hidden" name="nodeposito_original" value="<?= htmlspecialchars($original['nodeposito']) ?>">
                    <fieldset>
                        <legend>Datos del depósito</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="nodeposito">No. de depósito</label>
                                <input type="text" id="nodeposito" name="nodeposito" inputmode="numeric" maxlength="11" value="<?= $v('nodeposito') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="fechadep">Fecha de depósito</label>
                                <input type="date" id="fechadep" name="fechadep" value="<?= $v('fechadep') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="cuenta">Cuenta</label>
                                <select id="cuenta" name="cuenta" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (DepositosModel::cuentas() as $numero => $banco): ?>
                                        <option value="<?= htmlspecialchars($numero) ?>" <?= $numero === $cuentaSel ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($numero) ?> — <?= htmlspecialchars($banco) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="efectivo">Efectivo</label>
                                <input type="number" id="efectivo" name="efectivo" inputmode="decimal" step="0.01" min="0" value="<?= $v('efectivo', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chpropio">Cheque propio banco</label>
                                <input type="number" id="chpropio" name="chpropio" inputmode="decimal" step="0.01" min="0" value="<?= $v('chpropio', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chotrobanco">Cheque otros bancos</label>
                                <input type="number" id="chotrobanco" name="chotrobanco" inputmode="decimal" step="0.01" min="0" value="<?= $v('chotrobanco', '0.00') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="correspondiente">Correspondiente a</label>
                                <input type="date" id="correspondiente" name="correspondiente" value="<?= $v('correspondiente') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="responsable">Responsable</label>
                                <input type="text" id="responsable" name="responsable" maxlength="30" value="<?= $v('responsable') ?>" required>
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit">Guardar cambios</button>
                </form>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
