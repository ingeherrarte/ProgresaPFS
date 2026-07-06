<?php
class LoginView {

    public static function mostrar(?string $error = null, string $destino = 'inicio.php') {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Iniciar sesión — CETECPRO</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: Arial, sans-serif;
                    background: #f0f2f5;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .caja {
                    background: #fff;
                    width: 100%;
                    max-width: 360px;
                    padding: 32px;
                    border-radius: 8px;
                    box-shadow: 0 1px 6px rgba(0,0,0,.15);
                }
                h1 {
                    font-size: 20px;
                    color: #1a237e;
                    margin-bottom: 4px;
                    text-align: center;
                }
                p.subtitulo {
                    font-size: 13px;
                    color: #777;
                    text-align: center;
                    margin-bottom: 24px;
                }
                label {
                    display: block;
                    font-size: 13px;
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 4px;
                }
                input[type=text], input[type=password] {
                    width: 100%;
                    padding: 10px 12px;
                    font-size: 14px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    margin-bottom: 18px;
                }
                input[type=text]:focus, input[type=password]:focus {
                    outline: none;
                    border-color: #1a237e;
                }
                button {
                    width: 100%;
                    padding: 11px;
                    font-size: 14px;
                    font-weight: bold;
                    background: #1a237e;
                    color: #fff;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                }
                button:hover { background: #283593; }
                .error {
                    background: #ffebee;
                    border: 1px solid #e57373;
                    color: #b71c1c;
                    padding: 10px 12px;
                    border-radius: 4px;
                    font-size: 13px;
                    margin-bottom: 18px;
                }
            </style>
        </head>
        <body>
            <div class="caja">
                <h1>CETECPRO</h1>
                <p class="subtitulo">Ingreso de Recibos de Pago</p>

                <?php if ($error): ?>
                    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php?action=autenticar">
                    <input type="hidden" name="destino" value="<?= htmlspecialchars($destino) ?>">

                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required autofocus>

                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit">Ingresar</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
