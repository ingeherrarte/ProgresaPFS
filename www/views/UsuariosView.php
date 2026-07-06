<?php
require_once __DIR__ . "/../helpers/Auth.php";

class UsuariosView {

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
                background: #fff; max-width: 720px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
            }
            fieldset { border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
            legend { font-size: 13px; font-weight: bold; color: #1a237e; padding: 0 6px; }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
            .campo { flex: 1; min-width: 160px; }
            label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
            input {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid #ccc; border-radius: 4px;
            }
            input:focus { outline: none; border-color: #1a237e; }
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
            th { background: #1a237e; color: #fff; }
            tbody tr:nth-child(even) { background: #f5f7ff; }
            .estado-activo { color: #2e7d32; font-weight: bold; }
            .estado-inactivo { color: #b71c1c; font-weight: bold; }
        </style>
        <?php
    }

    private static function barra(): void {
        ?>
        <div class="barra">
            <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
            <span>
                <a href="recibospfs.php">Ingresar recibo</a>
                <a href="usuarios.php?action=password">Cambiar contraseña</a>
                <a href="login.php?action=logout">Cerrar sesión</a>
            </span>
        </div>
        <?php
    }

    public static function mostrar(array $errores = [], array $data = [], array $usuarios = [], ?string $mensaje = null): void {
        $usuario = htmlspecialchars($data['usuario'] ?? '');
        $nombreCompleto = htmlspecialchars($data['nombre_completo'] ?? '');
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Usuarios del sistema — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Usuarios del sistema</h1>

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

                <form method="POST" action="usuarios.php?action=crear">
                    <fieldset>
                        <legend>Registrar nuevo usuario</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="usuario">Usuario</label>
                                <input type="text" id="usuario" name="usuario" maxlength="30" value="<?= $usuario ?>" required>
                            </div>
                            <div class="campo">
                                <label for="nombre_completo">Nombre completo</label>
                                <input type="text" id="nombre_completo" name="nombre_completo" maxlength="60" value="<?= $nombreCompleto ?>" required>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="password">Contraseña</label>
                                <input type="password" id="password" name="password" minlength="8" required>
                            </div>
                            <div class="campo">
                                <label for="confirmar_password">Confirmar contraseña</label>
                                <input type="password" id="confirmar_password" name="confirmar_password" minlength="8" required>
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit">Registrar usuario</button>
                </form>
            </div>

            <div class="card">
                <fieldset>
                    <legend>Usuarios existentes</legend>
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Estado</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['usuario']) ?></td>
                                    <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                                    <td class="<?= $u['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($u['creado_en'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </fieldset>
            </div>
        </body>
        </html>
        <?php
    }

    public static function mostrarCambiarPassword(array $errores = [], ?string $mensaje = null): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Cambiar contraseña — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Cambiar contraseña</h1>

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

                <form method="POST" action="usuarios.php?action=cambiarPassword">
                    <fieldset>
                        <legend>Usuario: <?= htmlspecialchars(Auth::usuarioActual()) ?></legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="password_actual">Contraseña actual</label>
                                <input type="password" id="password_actual" name="password_actual" required>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="password_nueva">Nueva contraseña</label>
                                <input type="password" id="password_nueva" name="password_nueva" minlength="8" required>
                            </div>
                            <div class="campo">
                                <label for="confirmar_password_nueva">Confirmar nueva contraseña</label>
                                <input type="password" id="confirmar_password_nueva" name="confirmar_password_nueva" minlength="8" required>
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit">Cambiar contraseña</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
