<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/DepositosModel.php";

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
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 900px; margin: 0 auto 16px;
                }
                .barra .usuario { font-size: 13px; color: #555; }
                .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
                .card {
                    background: #fff; max-width: 900px; margin: 0 auto 24px;
                    border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                }
                fieldset { border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
                legend { font-size: 13px; font-weight: bold; color: #1a237e; padding: 0 6px; }
                .fila { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
                .campo { flex: 1; min-width: 180px; }
                label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
                input, select {
                    width: 100%; padding: 8px 10px; font-size: 14px;
                    border: 1px solid #ccc; border-radius: 4px;
                }
                input:focus, select:focus { outline: none; border-color: #1a237e; }
                button[type=submit] {
                    width: 100%; padding: 12px; font-size: 15px; font-weight: bold;
                    background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer;
                }
                button[type=submit]:hover { background: #283593; }
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
                th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
                th { background: #1a237e; color: #fff; white-space: nowrap; }
                td.num { text-align: right; font-family: 'Courier New', monospace; }
                tbody tr:nth-child(even) { background: #f5f7ff; }
                .sin-datos { color: #888; font-style: italic; padding: 12px 0; }
                h2 { font-size: 15px; color: #1a237e; margin: 0 0 12px; }
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="inicio.php">Inicio</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
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

                <form method="POST" action="depositos.php?action=guardar">
                    <fieldset>
                        <legend>Datos del depósito</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="nodeposito">No. de depósito</label>
                                <input type="text" id="nodeposito" name="nodeposito" maxlength="11" value="<?= $v('nodeposito') ?>" required>
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
                                <input type="number" id="efectivo" name="efectivo" step="0.01" min="0" value="<?= $v('efectivo', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chpropio">Cheque propio banco</label>
                                <input type="number" id="chpropio" name="chpropio" step="0.01" min="0" value="<?= $v('chpropio', '0.00') ?>">
                            </div>
                            <div class="campo">
                                <label for="chotrobanco">Cheque otros bancos</label>
                                <input type="number" id="chotrobanco" name="chotrobanco" step="0.01" min="0" value="<?= $v('chotrobanco', '0.00') ?>">
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

                    <button type="submit">Guardar depósito</button>
                </form>
            </div>

            <div class="card">
                <h2>Últimos depósitos registrados</h2>
                <?php if (empty($recientes)): ?>
                    <p class="sin-datos">No hay depósitos registrados.</p>
                <?php else: ?>
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
