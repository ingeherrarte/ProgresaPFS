<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../views/EstudiantesPfsView.php";
require_once __DIR__ . "/../views/RecibosPfsView.php";
require_once __DIR__ . "/../models/RecibosPfsModel.php";

class AdminView {

    private static function estilos(): void {
        ?>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 700px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: #555; }
            .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
            .card {
                background: #fff; max-width: 700px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
            }
            .accesos {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
            }
            .acceso {
                background: #f5f7ff; border-radius: 8px; padding: 24px 16px; text-align: center;
                text-decoration: none; transition: transform .15s;
            }
            .acceso:hover { transform: translateY(-2px); }
            .acceso .icono { font-size: 28px; margin-bottom: 8px; }
            .acceso .titulo { font-size: 14px; font-weight: bold; color: #1a237e; }
            fieldset { border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
            legend { font-size: 13px; font-weight: bold; color: #1a237e; padding: 0 6px; }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 12px; }
            .campo { flex: 1; min-width: 160px; }
            label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
            input, select {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid #ccc; border-radius: 4px;
            }
            input:focus, select:focus { outline: none; border-color: #1a237e; }
            button {
                padding: 10px 20px; font-size: 14px; font-weight: bold;
                background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            button.peligro { background: #b71c1c; width: 100%; padding: 12px; font-size: 15px; }
            button.peligro:hover { background: #8e1515; }
            .errores {
                background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .errores ul { margin: 6px 0 0 18px; }
            .exito {
                background: #e8f5e9; border: 1px solid #81c784; color: #2e7d32;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .resumen-recibo {
                background: #f5f7ff; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; font-size: 13px;
            }
            .resumen-recibo div { margin-bottom: 4px; }
            .totales {
                display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;
                background: #f5f7ff; border-radius: 6px; padding: 12px 18px; margin-bottom: 18px; font-size: 14px;
            }
            .totales span.no-coincide { color: #b71c1c; font-weight: bold; }
            .totales span.coincide { color: #2e7d32; font-weight: bold; }
            table { border-collapse: collapse; width: 100%; font-size: 12px; }
            th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
            th { background: #1a237e; color: #fff; }
            tbody tr:nth-child(even) { background: #f5f7ff; }
            .valor-anterior { color: #b71c1c; }
            .valor-nuevo { color: #2e7d32; font-weight: bold; }
            .aviso-fecha {
                background: #fff8e1; border: 1px solid #ffca28; color: #8d6e00;
                padding: 4px 8px; border-radius: 4px; margin-bottom: 4px; font-size: 11px;
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
            </span>
        </div>
        <?php
    }

    public static function mostrarMenu(): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Administración — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Administración</h1>

            <div class="card">
                <div class="accesos">
                    <?php if (Auth::puedeAnularRecibos()): ?>
                        <a class="acceso" href="admin.php?action=anular">
                            <div class="icono">🚫</div>
                            <div class="titulo">Anular Recibo</div>
                        </a>
                    <?php endif; ?>
                    <?php if (Auth::puedeEditarEstudiantes()): ?>
                        <a class="acceso" href="admin.php?action=editar_estudiante">
                            <div class="icono">✏️</div>
                            <div class="titulo">Editar Estudiante</div>
                        </a>
                    <?php endif; ?>
                    <a class="acceso" href="usuarios.php?action=password">
                        <div class="icono">🔑</div>
                        <div class="titulo">Cambiar Contraseña</div>
                    </a>
                    <?php if (Auth::esAdministrador()): ?>
                        <a class="acceso" href="admin.php?action=editar_recibo">
                            <div class="icono">🧾</div>
                            <div class="titulo">Editar Recibo</div>
                        </a>
                        <a class="acceso" href="usuarios.php">
                            <div class="icono">👥</div>
                            <div class="titulo">Gestionar Usuarios</div>
                        </a>
                        <a class="acceso" href="accesos.php">
                            <div class="icono">🕓</div>
                            <div class="titulo">Registro de Accesos</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    public static function mostrarAnular(string $numeroBuscado, ?array $recibo, array $errores): void {
        $puedeAnular = $recibo && !$recibo['anulado'];
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Anular Recibo — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Anular Recibo</h1>

            <div class="card">
                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="GET" action="admin.php">
                    <input type="hidden" name="action" value="anular">
                    <div class="fila">
                        <div class="campo">
                            <label for="numero">Número de recibo</label>
                            <input type="text" id="numero" name="numero" inputmode="numeric" value="<?= htmlspecialchars($numeroBuscado) ?>" autofocus>
                        </div>
                        <div>
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>

                <?php if ($puedeAnular): ?>
                    <?php $total = $recibo['mensualidad'] + $recibo['inscripcion'] + $recibo['otro']; ?>
                    <div class="resumen-recibo">
                        <div><b>Recibo No.</b> <?= $recibo['numero'] ?>-<?= $recibo['aleatorio'] ?></div>
                        <div><b>Carné:</b> <?= htmlspecialchars($recibo['carne']) ?></div>
                        <div><b>Detalle:</b> <?= htmlspecialchars($recibo['detalle']) ?></div>
                        <div><b>Total:</b> Q <?= number_format($total, 2) ?></div>
                        <div><b>Fecha registro:</b> <?= date('d/m/Y H:i', strtotime($recibo['horaregistro'])) ?></div>
                        <div><b>Registrado por:</b> <?= htmlspecialchars($recibo['usuario']) ?></div>
                    </div>

                    <form method="POST" action="admin.php?action=anular_confirmar">
                        <input type="hidden" name="numero" value="<?= $recibo['numero'] ?>">
                        <fieldset>
                            <legend>Confirmar anulación</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="password_actual">Tu contraseña</label>
                                    <input type="password" id="password_actual" name="password_actual" required>
                                </div>
                            </div>
                            <div class="fila">
                                <div class="campo">
                                    <label for="motivo">Motivo de la anulación</label>
                                    <input type="text" id="motivo" name="motivo" maxlength="200" required>
                                </div>
                            </div>
                        </fieldset>
                        <button type="submit" class="peligro">Anular este recibo</button>
                    </form>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }

    public static function mostrarEditarEstudiante(string $carnetBuscado, ?array $estudiante, array $cursos, array $errores, ?string $mensaje = null): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Editar Estudiante — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Editar Estudiante</h1>

            <div class="card">
                <?php if ($mensaje): ?>
                    <div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>

                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="GET" action="admin.php">
                    <input type="hidden" name="action" value="editar_estudiante">
                    <div class="fila">
                        <div class="campo">
                            <label for="carnet">Carné del estudiante</label>
                            <input type="text" id="carnet" name="carnet" inputmode="numeric" value="<?= htmlspecialchars($carnetBuscado) ?>" autofocus>
                        </div>
                        <div>
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>

                <?php if ($estudiante): ?>
                    <form method="POST" action="admin.php?action=editar_estudiante_guardar">
                        <input type="hidden" name="carnet" value="<?= (int)$estudiante['idestudiante'] ?>">
                        <?php EstudiantesPfsView::camposFormulario($estudiante, $cursos); ?>
                        <fieldset>
                            <legend>Confirmar cambios</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="password_actual">Tu contraseña</label>
                                    <input type="password" id="password_actual" name="password_actual" required>
                                </div>
                            </div>
                        </fieldset>
                        <button type="submit">Guardar cambios</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php EstudiantesPfsView::scriptToggleMenor(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarEditarRecibo(string $numeroBuscado, ?array $recibo, array $historial, array $errores, ?string $mensaje = null): void {
        $editable = $recibo && !$recibo['anulado'];
        $meses = RecibosPfsView::meses();

        // Las filas legacy traen fechas '0000-00-00' y numeros de
        // deposito/cheque en 0; ambos se muestran vacios para que el campo no
        // arranque con un valor invalido que el administrador tenga que borrar.
        $texto = fn($valor) => htmlspecialchars((string)($valor ?? ''));
        $fecha = fn($valor) => ($valor && $valor !== '0000-00-00') ? htmlspecialchars((string)$valor) : '';
        $opcional = fn($valor) => ((int)$valor !== 0) ? htmlspecialchars((string)$valor) : '';
        // Casi todo el histórico (recibos anteriores a ~2020) nunca tuvo esta
        // fecha registrada: no es que se haya perdido, nunca existió. El
        // campo sigue siendo obligatorio para guardar (ver validarEdicion),
        // pero sin este aviso el campo vacío se ve igual que un bug del
        // formulario en vez de un hueco del dato original.
        $fechaInvalida = fn($valor) => !$valor || $valor === '0000-00-00';

        // Mismos selects de Día/Mes del pago que "Nuevo Recibo" (más un Año
        // explícito: a diferencia de un recibo nuevo, uno editado puede ser
        // de cualquier año anterior, no siempre el actual). Si viene de un
        // reintento con errores, $recibo ya trae 'diapago'/'mespago'/
        // 'anio_pago' del $_POST (ver AdminController::editarReciboGuardar)
        // y se usan tal cual, para no perder lo que el usuario tecleó.
        if (isset($recibo['diapago'])) {
            $diapago = (int)$recibo['diapago'];
            $mespago = $recibo['mespago'] ?? '';
            $anioPago = $recibo['anio_pago'] ?? '';
        } elseif ($recibo && !$fechaInvalida($recibo['fechadelpago'])) {
            [$anioPago, $mesNum, $diapago] = array_map('intval', explode('-', $recibo['fechadelpago']));
            $mespago = $meses[$mesNum - 1];
        } else {
            $diapago = 0;
            $mespago = '';
            $anioPago = '';
        }
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Editar Recibo — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Editar Recibo</h1>

            <div class="card">
                <?php if ($mensaje): ?>
                    <div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div>
                <?php endif; ?>

                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ <ul>
                            <?php foreach ($errores as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="GET" action="admin.php">
                    <input type="hidden" name="action" value="editar_recibo">
                    <div class="fila">
                        <div class="campo">
                            <label for="numero">Número de recibo</label>
                            <input type="text" id="numero" name="numero" inputmode="numeric" value="<?= htmlspecialchars($numeroBuscado) ?>" autofocus>
                        </div>
                        <div>
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>

                <?php if ($recibo): ?>
                    <div class="resumen-recibo">
                        <div><b>Recibo No.</b> <?= (int)$recibo['numero'] ?>-<?= (int)$recibo['aleatorio'] ?></div>
                        <div><b>Registrado por:</b> <?= $texto($recibo['usuario']) ?></div>
                        <div><b>Hora de registro:</b> <?= $recibo['horaregistro'] ? date('d/m/Y H:i', strtotime($recibo['horaregistro'])) : 'sin registro' ?></div>
                        <div style="color:#777">Estos datos identifican al recibo impreso y no son editables.</div>
                    </div>
                <?php endif; ?>

                <?php if ($editable): ?>
                    <form method="POST" action="admin.php?action=editar_recibo_guardar">
                        <input type="hidden" name="numero" value="<?= (int)$recibo['numero'] ?>">

                        <fieldset>
                            <legend>Estudiante</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="carne">Carné</label>
                                    <input type="text" id="carne" name="carne" inputmode="numeric" maxlength="8" value="<?= $texto($recibo['carne']) ?>" required>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Periodo de pago</legend>
                            <?php if ($fechaInvalida($recibo['fechadelpago'])): ?>
                                <div class="aviso-fecha">⚠️ Este recibo no tiene fecha de pago registrada en el original. Indique día, mes y año correctos para poder guardar.</div>
                            <?php endif; ?>
                            <div class="fila">
                                <div class="campo">
                                    <label for="diapago">Día del pago</label>
                                    <select id="diapago" name="diapago" required>
                                        <option value="">--</option>
                                        <?php for ($d = 1; $d <= 31; $d++): ?>
                                            <option value="<?= $d ?>" <?= ($d === $diapago) ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="campo">
                                    <label for="mespago">Mes del pago</label>
                                    <select id="mespago" name="mespago" required>
                                        <option value="">--</option>
                                        <?php foreach ($meses as $m): ?>
                                            <option value="<?= $m ?>" <?= ($m === $mespago) ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="campo">
                                    <label for="anio_pago">Año del pago</label>
                                    <input type="number" id="anio_pago" name="anio_pago" min="2000" max="2999" value="<?= htmlspecialchars((string)$anioPago) ?>" required>
                                </div>
                                <div class="campo">
                                    <label for="mesquepaga">Mes que paga</label>
                                    <select id="mesquepaga" name="mesquepaga" required>
                                        <option value="">--</option>
                                        <?php foreach ($meses as $i => $m): ?>
                                            <option value="<?= $i + 1 ?>" <?= ((int)$recibo['mesquepaga'] === $i + 1) ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
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
                                    <input type="number" id="mensualidad" name="mensualidad" step="0.01" min="0" value="<?= $texto($recibo['mensualidad']) ?>" class="monto-cargo">
                                </div>
                                <div class="campo">
                                    <label for="inscripcion">Inscripción</label>
                                    <input type="number" id="inscripcion" name="inscripcion" step="0.01" min="0" value="<?= $texto($recibo['inscripcion']) ?>" class="monto-cargo">
                                </div>
                                <div class="campo">
                                    <label for="otro">Otro</label>
                                    <input type="number" id="otro" name="otro" step="0.01" min="0" value="<?= $texto($recibo['otro']) ?>" class="monto-cargo">
                                </div>
                            </div>
                            <div class="fila">
                                <div class="campo">
                                    <label for="detalle">Detalle</label>
                                    <input type="text" id="detalle" name="detalle" maxlength="90" value="<?= $texto($recibo['detalle']) ?>" required>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend>Forma de pago</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="efectivo">Efectivo</label>
                                    <input type="number" id="efectivo" name="efectivo" step="0.01" min="0" value="<?= $texto($recibo['efectivo']) ?>" class="monto-pagado">
                                </div>
                                <div class="campo">
                                    <label for="deposito">Depósito</label>
                                    <input type="number" id="deposito" name="deposito" step="0.01" min="0" value="<?= $texto($recibo['deposito']) ?>" class="monto-pagado">
                                </div>
                                <div class="campo">
                                    <label for="cheque">Cheque</label>
                                    <input type="number" id="cheque" name="cheque" step="0.01" min="0" value="<?= $texto($recibo['cheque']) ?>" class="monto-pagado">
                                </div>
                            </div>
                            <div class="fila">
                                <div class="campo">
                                    <label for="nodeposito">No. de depósito</label>
                                    <input type="text" id="nodeposito" name="nodeposito" inputmode="numeric" maxlength="12" value="<?= $opcional($recibo['nodeposito']) ?>">
                                </div>
                                <div class="campo">
                                    <label for="fechadep">Fecha de depósito</label>
                                    <?php if ($fechaInvalida($recibo['fechadep'])): ?>
                                        <div class="aviso-fecha">⚠️ No hay fecha registrada en el recibo original<?= (float)$recibo['deposito'] > 0 ? '. Indique la fecha correcta para poder guardar' : '' ?>.</div>
                                    <?php endif; ?>
                                    <input type="date" id="fechadep" name="fechadep" value="<?= $fecha($recibo['fechadep']) ?>">
                                </div>
                            </div>
                            <div class="fila">
                                <div class="campo">
                                    <label for="nocheque">No. de cheque</label>
                                    <input type="text" id="nocheque" name="nocheque" inputmode="numeric" maxlength="12" value="<?= $opcional($recibo['nocheque']) ?>">
                                </div>
                                <div class="campo">
                                    <label for="banco">Banco de origen</label>
                                    <select id="banco" name="banco">
                                        <option value="">-- Seleccione el banco --</option>
                                        <?php foreach (RecibosPfsView::bancos() as $b): ?>
                                            <option value="<?= htmlspecialchars($b) ?>" <?= ($b === ($recibo['banco'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <div class="totales">
                            <span>Total a cobrar: Q <span id="totalCargos">0.00</span></span>
                            <span>Total pagado: <span id="totalPagado" class="coincide">Q 0.00</span></span>
                        </div>

                        <fieldset>
                            <legend>Confirmar cambios</legend>
                            <div class="fila">
                                <div class="campo">
                                    <label for="motivo">Motivo de la edición</label>
                                    <input type="text" id="motivo" name="motivo" maxlength="200" required>
                                </div>
                            </div>
                            <div class="fila">
                                <div class="campo">
                                    <label for="password_actual">Tu contraseña</label>
                                    <input type="password" id="password_actual" name="password_actual" required>
                                </div>
                            </div>
                        </fieldset>

                        <button type="submit">Guardar cambios</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($historial)): ?>
                    <fieldset style="margin-top:24px">
                        <legend>Ediciones anteriores</legend>
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Campo</th>
                                    <th>Antes</th>
                                    <th>Después</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $h): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td>
                                        <td><?= htmlspecialchars($h['usuario']) ?></td>
                                        <td><?= htmlspecialchars(RecibosPfsModel::etiquetaCampo($h['campo'])) ?></td>
                                        <td class="valor-anterior"><?= htmlspecialchars($h['valor_anterior']) ?></td>
                                        <td class="valor-nuevo"><?= htmlspecialchars($h['valor_nuevo']) ?></td>
                                        <td><?= htmlspecialchars($h['motivo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </fieldset>
                <?php endif; ?>
            </div>

            <?php if ($editable): ?>
                <script>
                    // Mismo control que el formulario de creación: los cargos y
                    // las formas de pago deben cuadrar antes de guardar.
                    function recalcularTotales() {
                        const suma = sel => Array.from(document.querySelectorAll(sel))
                            .reduce((t, el) => t + (parseFloat(el.value) || 0), 0);

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
                </script>
            <?php endif; ?>
        </body>
        </html>
        <?php
    }
}
?>
