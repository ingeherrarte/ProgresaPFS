<?php
require_once __DIR__ . "/../helpers/Auth.php";

class RecibosPfsView {

    private static array $meses = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    private static array $bancosGuatemala = [
        'Banco Industrial',
        'Banco G&T Continental',
        'Banco de Desarrollo Rural (BANRURAL)',
        'Banco Agromercantil (BAM)',
        'Banco de los Trabajadores (BANTRAB)',
        'BAC Credomatic',
        'Banco Promerica',
        'Banco Internacional',
        'Banco Inmobiliario',
        'Vivibanco',
        'Banco Azteca Guatemala',
        'Banco Ficohsa Guatemala',
        'Banco Citibank Guatemala',
        'Otro',
    ];

    private static function estilos(): void {
        ?>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 720px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: #555; }
            .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
            .card {
                background: #fff; max-width: 720px; margin: 0 auto;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
            }
            fieldset { border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
            legend { font-size: 13px; font-weight: bold; color: #1a237e; padding: 0 6px; }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
            .campo { flex: 1; min-width: 160px; }
            label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
            input, select {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid #ccc; border-radius: 4px;
            }
            input:focus, select:focus { outline: none; border-color: #1a237e; }
            .estudiante-info {
                font-size: 13px; margin-top: 6px; padding: 8px 10px; border-radius: 4px;
                min-height: 18px;
            }
            .estudiante-info.ok { background: #e8f5e9; color: #2e7d32; }
            .estudiante-info.error { background: #ffebee; color: #b71c1c; }
            .estudiante-info.alerta { background: #fff8e1; color: #8d6e00; }
            .totales {
                display: flex; justify-content: space-between; font-size: 14px;
                background: #f5f7ff; border-radius: 4px; padding: 10px 14px; margin-bottom: 18px;
            }
            .totales span.no-coincide { color: #b71c1c; font-weight: bold; }
            .totales span.coincide { color: #2e7d32; font-weight: bold; }
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
            .campo.destacado label { font-weight: bold; color: #1a237e; }
            .campo.destacado input, .campo.destacado select { font-weight: bold; }
            .card.ancho { max-width: 960px; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
            th { background: #1a237e; color: #fff; }
            tbody tr:nth-child(even) { background: #f5f7ff; }
            .sin-resultados { color: #777; font-style: italic; padding: 12px 0; }
        </style>
        <?php
    }

    public static function mostrarFormulario(array $errores = [], array $data = []): void {
        // Si no viene un periodo de pago de un intento previo (o de un carné
        // precargado desde el buscador de estudiantes), se predetermina a hoy.
        if (!isset($data['diapago'])) {
            $mesHoy = self::$meses[(int)date('n') - 1];
            $data['diapago'] = date('j');
            $data['mespago'] = $mesHoy;
            $data['mes'] = $mesHoy;
        }

        $carnet = htmlspecialchars($data['carnet'] ?? '');
        $diapago = (int)($data['diapago'] ?? 0);
        $mespago = $data['mespago'] ?? '';
        $mes = $data['mes'] ?? '';
        $mensualidad = htmlspecialchars($data['mensualidad'] ?? '0.00');
        $inscripcion = htmlspecialchars($data['inscripcion'] ?? '0.00');
        $otro = htmlspecialchars($data['otro'] ?? '0.00');
        $detalle = htmlspecialchars($data['detalle'] ?? '');
        $efectivo = htmlspecialchars($data['efectivo'] ?? '0.00');
        $deposito = htmlspecialchars($data['deposito'] ?? '0.00');
        $nodeposito = htmlspecialchars($data['nodeposito'] ?? '');
        $fechadep = htmlspecialchars($data['fechadep'] ?? '');
        $cheque = htmlspecialchars($data['cheque'] ?? '0.00');
        $nocheque = htmlspecialchars($data['nocheque'] ?? '');
        $banco = $data['banco'] ?? '';
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Ingreso de Recibo — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="estudiantespfs.php?action=form">Nuevo estudiante</a>
                    <a href="estudiantespfs.php">Buscar estudiante</a>
                    <a href="recibospfs.php?action=buscar">Buscar recibos</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                </span>
            </div>
            <h1>CETECPRO — Ingreso de Recibo de Pago</h1>

            <div class="card">
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

                <form method="POST" action="recibospfs.php?action=guardar" id="formRecibo">
                    <fieldset>
                        <legend>Estudiante</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="carnet">Carné</label>
                                <input type="text" id="carnet" name="carnet" maxlength="8" inputmode="numeric" value="<?= $carnet ?>" required>
                                <div id="estudianteInfo" class="estudiante-info"></div>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Periodo de pago</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="diapago">Día del pago</label>
                                <select id="diapago" name="diapago" required>
                                    <option value="">--</option>
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= $d === $diapago ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="mespago">Mes del pago</label>
                                <select id="mespago" name="mespago" required>
                                    <option value="">--</option>
                                    <?php foreach (self::$meses as $m): ?>
                                        <option value="<?= $m ?>" <?= $m === $mespago ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="mes">Mes que paga</label>
                                <select id="mes" name="mes" required>
                                    <option value="">--</option>
                                    <?php foreach (self::$meses as $m): ?>
                                        <option value="<?= $m ?>" <?= $m === $mes ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Cargos</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="mensualidad">Mensualidad</label>
                                <input type="number" id="mensualidad" name="mensualidad" step="0.01" min="0" value="<?= $mensualidad ?>" class="monto-cargo">
                            </div>
                            <div class="campo">
                                <label for="inscripcion">Inscripción</label>
                                <input type="number" id="inscripcion" name="inscripcion" step="0.01" min="0" value="<?= $inscripcion ?>" class="monto-cargo">
                            </div>
                            <div class="campo">
                                <label for="otro">Otro</label>
                                <input type="number" id="otro" name="otro" step="0.01" min="0" value="<?= $otro ?>" class="monto-cargo">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="detalle">Detalle</label>
                                <input type="text" id="detalle" name="detalle" maxlength="90" value="<?= $detalle ?>" required>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Forma de pago</legend>
                        <div class="fila">
                            <div class="campo destacado">
                                <label for="efectivo">Efectivo</label>
                                <input type="number" id="efectivo" name="efectivo" step="0.01" min="0" value="<?= $efectivo ?>" class="monto-pagado">
                            </div>
                            <div class="campo destacado">
                                <label for="deposito">Depósito</label>
                                <input type="number" id="deposito" name="deposito" step="0.01" min="0" value="<?= $deposito ?>" class="monto-pagado">
                            </div>
                            <div class="campo">
                                <label for="cheque">Cheque</label>
                                <input type="number" id="cheque" name="cheque" step="0.01" min="0" value="<?= $cheque ?>" class="monto-pagado">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo destacado">
                                <label for="nodeposito">No. de depósito</label>
                                <input type="text" id="nodeposito" name="nodeposito" maxlength="12" value="<?= $nodeposito ?>">
                            </div>
                            <div class="campo destacado">
                                <label for="fechadep">Fecha de depósito</label>
                                <input type="date" id="fechadep" name="fechadep" value="<?= $fechadep ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="nocheque">No. de cheque</label>
                                <input type="text" id="nocheque" name="nocheque" maxlength="12" value="<?= $nocheque ?>">
                            </div>
                            <div class="campo">
                                <label for="banco">Banco de origen</label>
                                <select id="banco" name="banco">
                                    <option value="">-- Seleccione el banco --</option>
                                    <?php foreach (self::$bancosGuatemala as $b): ?>
                                        <option value="<?= htmlspecialchars($b) ?>" <?= $b === $banco ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <div class="totales">
                        <span>Total a cobrar: Q <span id="totalCargos">0.00</span></span>
                        <span>Total pagado: <span id="totalPagado" class="coincide">Q 0.00</span></span>
                    </div>

                    <button type="submit">Guardar recibo</button>
                </form>
            </div>

            <script>
                const carnetInput = document.getElementById('carnet');
                const infoDiv = document.getElementById('estudianteInfo');
                let temporizador = null;

                function validarCarnet(carnet) {
                    infoDiv.className = 'estudiante-info';
                    infoDiv.textContent = '';
                    if (!/^\d+$/.test(carnet)) return;

                    fetch('recibospfs.php?action=buscarCarnet&carnet=' + encodeURIComponent(carnet))
                        .then(r => r.json())
                        .then(datos => {
                            if (datos.ok) {
                                infoDiv.className = 'estudiante-info ' + (datos.activo ? 'ok' : 'alerta');
                                infoDiv.textContent = datos.nombre + ' — ' + datos.curso
                                    + (datos.activo ? '' : ' (estudiante inactivo)');
                            } else {
                                infoDiv.className = 'estudiante-info error';
                                infoDiv.textContent = datos.mensaje;
                            }
                        })
                        .catch(() => {
                            infoDiv.className = 'estudiante-info error';
                            infoDiv.textContent = 'No se pudo validar el carné.';
                        });
                }

                carnetInput.addEventListener('input', () => {
                    clearTimeout(temporizador);
                    const carnet = carnetInput.value.trim();
                    temporizador = setTimeout(() => validarCarnet(carnet), 350);
                });

                // Si el carné ya viene precargado (ej. desde el buscador de
                // estudiantes), se valida de inmediato sin esperar al input.
                if (carnetInput.value.trim() !== '') {
                    validarCarnet(carnetInput.value.trim());
                }

                function recalcularTotales() {
                    const suma = (selector) => Array.from(document.querySelectorAll(selector))
                        .reduce((acc, el) => acc + (parseFloat(el.value) || 0), 0);

                    const totalCargos = suma('.monto-cargo');
                    const totalPagado = suma('.monto-pagado');

                    document.getElementById('totalCargos').textContent = totalCargos.toFixed(2);
                    const spanPagado = document.getElementById('totalPagado');
                    spanPagado.textContent = 'Q ' + totalPagado.toFixed(2);
                    spanPagado.className = Math.abs(totalCargos - totalPagado) < 0.01 ? 'coincide' : 'no-coincide';
                }

                document.querySelectorAll('.monto-cargo, .monto-pagado')
                    .forEach(el => el.addEventListener('input', recalcularTotales));
                recalcularTotales();

                // El banco de origen es obligatorio si hay depósito o cheque;
                // el número y la fecha de depósito solo si hay depósito.
                const depositoInput = document.getElementById('deposito');
                const chequeInput = document.getElementById('cheque');
                const bancoSelect = document.getElementById('banco');
                const nodepositoInput = document.getElementById('nodeposito');
                const fechadepInput = document.getElementById('fechadep');

                function actualizarCamposRequeridos() {
                    const hayDeposito = (parseFloat(depositoInput.value) || 0) > 0;
                    const hayCheque = (parseFloat(chequeInput.value) || 0) > 0;

                    bancoSelect.required = hayDeposito || hayCheque;
                    nodepositoInput.required = hayDeposito;
                    fechadepInput.required = hayDeposito;
                }

                [depositoInput, chequeInput].forEach(el => el.addEventListener('input', actualizarCamposRequeridos));
                actualizarCamposRequeridos();
            </script>
        </body>
        </html>
        <?php
    }

    public static function mostrarRecibo(array $recibo, ?array $estudiante): void {
        $nombreEstudiante = $estudiante ? trim($estudiante['nombre'] . ' ' . $estudiante['apellidos']) : '(estudiante no encontrado)';
        $nombreCurso = $estudiante['nombrecurso'] ?? '';
        $mesNombre = ucfirst(self::$meses[$recibo['mesquepaga'] - 1] ?? '');

        $mora = null;
        // La mora original solo tenía sentido cuando el mes de pago coincidía con
        // el mes que se está pagando (pago del mes en curso), igual que el legacy.
        if ((int)date('n', strtotime($recibo['horaregistro'])) === (int)$recibo['mesquepaga']) {
            $dia = (int)date('j', strtotime($recibo['fechadelpago']));
            $calculo = ($dia - 8) * 3;
            $mora = $calculo > 0 ? $calculo : 0;
        }

        $totalCargos = $recibo['mensualidad'] + $recibo['inscripcion'] + $recibo['otro'];
        $depositar = $recibo['efectivo'] > 0;
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Recibo No. <?= $recibo['numero'] ?> — CETECPRO</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #222; }
                .acciones { max-width: 640px; margin: 0 auto 16px; display: flex; justify-content: space-between; }
                .acciones a, .acciones button {
                    font-size: 13px; font-weight: bold; color: #fff; background: #1a237e;
                    border: none; border-radius: 4px; padding: 8px 16px; text-decoration: none; cursor: pointer;
                }
                .copia {
                    background: #fff; max-width: 640px; margin: 0 auto 24px; border-radius: 6px;
                    padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                }
                .copia h2 { color: #1a237e; font-size: 18px; margin-bottom: 2px; }
                .copia .direccion { font-size: 12px; color: #666; margin-bottom: 14px; }
                .copia .numero { text-align: right; font-size: 13px; color: #555; margin-bottom: 10px; }
                .datos { font-size: 14px; margin-bottom: 4px; }
                .datos b { color: #1a237e; }
                .aviso {
                    background: #fff8e1; color: #8d6e00; padding: 8px 12px; border-radius: 4px;
                    font-size: 13px; margin: 12px 0;
                }
                .total { font-size: 16px; font-weight: bold; text-align: right; margin-top: 12px; color: #1a237e; }
                .pie { font-size: 11px; color: #777; margin-top: 16px; text-align: center; }
                .etiqueta-copia {
                    text-align: center; font-size: 11px; color: #999; margin-top: 16px;
                    text-transform: uppercase; letter-spacing: 1px;
                }
                @media print {
                    .acciones { display: none; }
                    body { background: #fff; padding: 0; }
                    .copia { box-shadow: none; }
                }
            </style>
        </head>
        <body>
            <div class="acciones">
                <a href="recibospfs.php">← Nuevo recibo</a>
                <button onclick="window.print()">Imprimir</button>
            </div>

            <?php
            $etiquetas = ['Copia para el estudiante', 'Copia para archivo'];
            foreach ($etiquetas as $etiqueta):
            ?>
            <div class="copia">
                <h2>CETECPRO</h2>
                <div class="direccion">13 calle 3-52 zona 1 · Tels. 2221-2225, 4545-4396</div>
                <div class="numero">
                    Recibo No. <b><?= $recibo['numero'] ?>-<?= $recibo['aleatorio'] ?></b>
                    &nbsp;|&nbsp; <?= date('d/m/Y H:i', strtotime($recibo['horaregistro'])) ?>
                </div>

                <div class="datos">Carné: <b><?= htmlspecialchars($recibo['carne']) ?></b></div>
                <div class="datos">Estudiante: <b><?= htmlspecialchars($nombreEstudiante) ?></b></div>
                <?php if ($nombreCurso): ?>
                    <div class="datos">Curso: <b><?= htmlspecialchars($nombreCurso) ?></b></div>
                <?php endif; ?>
                <div class="datos">Día de pago: <b><?= date('d', strtotime($recibo['fechadelpago'])) ?></b>
                    &nbsp; Mes que paga: <b><?= $mesNombre ?></b></div>

                <?php if ($mora !== null): ?>
                    <div class="datos"><?= $mora > 0 ? "Mora aplicada: Q " . number_format($mora, 2) : "Sin mora" ?></div>
                <?php endif; ?>

                <hr style="margin:10px 0; border:none; border-top:1px solid #eee;">

                <div class="datos">Mensualidad: Q <?= number_format($recibo['mensualidad'], 2) ?></div>
                <div class="datos">Inscripción: Q <?= number_format($recibo['inscripcion'], 2) ?></div>
                <div class="datos">Otro: Q <?= number_format($recibo['otro'], 2) ?></div>
                <div class="datos">Detalle: <?= htmlspecialchars($recibo['detalle']) ?></div>

                <hr style="margin:10px 0; border:none; border-top:1px solid #eee;">

                <div class="datos"><b>Efectivo: Q <?= number_format($recibo['efectivo'], 2) ?></b></div>
                <?php if ($recibo['deposito'] > 0): ?>
                    <div class="datos"><b>Depósito: Q <?= number_format($recibo['deposito'], 2) ?>
                        — No. <?= htmlspecialchars($recibo['nodeposito']) ?>
                        — Fecha: <?= date('d/m/Y', strtotime($recibo['fechadep'])) ?>
                        — Banco: <?= htmlspecialchars($recibo['banco']) ?></b></div>
                <?php endif; ?>
                <?php if ($recibo['cheque'] > 0): ?>
                    <div class="datos">Cheque: Q <?= number_format($recibo['cheque'], 2) ?>
                        — No. <?= htmlspecialchars($recibo['nocheque']) ?>
                        — Banco: <?= htmlspecialchars($recibo['banco']) ?></div>
                <?php endif; ?>

                <?php if ($depositar): ?>
                    <div class="aviso">Debe depositar en cuenta monetaria BANTRAB 2870 01172 0 — CENTRO TECNICO PROGRESISTA</div>
                <?php endif; ?>

                <div class="total">Total: Q <?= number_format($totalCargos, 2) ?></div>

                <div class="pie">
                    Atendido por: <?= htmlspecialchars($recibo['usuario']) ?><br>
                    Sin excepción alguna no se devolverá el dinero por concepto de este recibo.
                    Guárdelo para cualquier reclamo.
                </div>

                <div class="etiqueta-copia"><?= $etiqueta ?></div>
            </div>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarBuscar(array $columnas, string $campoSeleccionado, string $palabra, array $resultados, ?string $error): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Buscar Recibos — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="recibospfs.php">Nuevo recibo</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                </span>
            </div>
            <h1>Buscar Recibos</h1>

            <div class="card ancho">
                <?php if ($error): ?>
                    <div class="errores">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="GET" action="recibospfs.php">
                    <input type="hidden" name="action" value="buscar">
                    <div class="fila">
                        <div class="campo">
                            <label for="campo">Buscar en</label>
                            <select id="campo" name="campo">
                                <?php foreach ($columnas as $clave => $etiqueta): ?>
                                    <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $campoSeleccionado ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($etiqueta) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="palabra">Dato a buscar</label>
                            <input type="text" id="palabra" name="palabra" value="<?= htmlspecialchars($palabra) ?>">
                        </div>
                    </div>
                    <button type="submit">Buscar</button>
                </form>
            </div>

            <?php if ($campoSeleccionado !== '' || $palabra !== ''): ?>
                <div class="card ancho">
                    <?php if (empty($resultados)): ?>
                        <p class="sin-resultados">No se encontraron recibos.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Carné</th>
                                    <th>Detalle</th>
                                    <th>Total</th>
                                    <th>Registrado por</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $r): ?>
                                    <?php $total = $r['mensualidad'] + $r['inscripcion'] + $r['otro']; ?>
                                    <tr>
                                        <td><?= $r['numero'] ?>-<?= $r['aleatorio'] ?></td>
                                        <td><?= htmlspecialchars($r['carne']) ?></td>
                                        <td><?= htmlspecialchars($r['detalle']) ?></td>
                                        <td>Q <?= number_format($total, 2) ?></td>
                                        <td><?= htmlspecialchars($r['usuario']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($r['horaregistro'])) ?></td>
                                        <td><a href="recibospfs.php?action=ver&numero=<?= $r['numero'] ?>">Ver</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}
?>
