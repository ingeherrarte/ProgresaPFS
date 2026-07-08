<?php
require_once __DIR__ . "/../helpers/Auth.php";

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
            input {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid #ccc; border-radius: 4px;
            }
            input:focus { outline: none; border-color: #1a237e; }
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
            .resumen-recibo {
                background: #f5f7ff; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; font-size: 13px;
            }
            .resumen-recibo div { margin-bottom: 4px; }
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
                    <a class="acceso" href="admin.php?action=anular">
                        <div class="icono">🚫</div>
                        <div class="titulo">Anular Recibo</div>
                    </a>
                    <a class="acceso" href="usuarios.php?action=password">
                        <div class="icono">🔑</div>
                        <div class="titulo">Cambiar Contraseña</div>
                    </a>
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
}
?>
