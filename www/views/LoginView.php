<?php
class LoginView {

    public static function mostrar(?string $error = null, string $destino = 'inicio.php') {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Aseguramiento de Calidad - PRUEBAS — CETECPRO</title>
            <link rel="manifest" href="manifest.json">
            <meta name="theme-color" content="#6366f1">
            <link rel="apple-touch-icon" href="pwa-icons/apple-touch-icon.png">
            <meta name="apple-mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
            <meta name="apple-mobile-web-app-title" content="CETECPRO">
            <style>
                :root {
                    --brand: #6366f1;
                    --brand-dark: #4f46e5;
                    --bg: #f8fafc;
                    --surface: #ffffff;
                    --border: #e5e7eb;
                    --text: #0f172a;
                    --text-muted: #64748b;
                    --radius-lg: 16px;
                    --radius-md: 10px;
                    --shadow-md: 0 8px 24px rgba(15,23,42,.08);
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background:
                        radial-gradient(560px circle at 15% 10%, rgba(99,102,241,.08), transparent 60%),
                        radial-gradient(520px circle at 85% 90%, rgba(99,102,241,.06), transparent 60%),
                        #dbeefe;
                    color: var(--text);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    -webkit-font-smoothing: antialiased;
                }
                .marca {
                    display: flex; align-items: center; justify-content: center; gap: 9px;
                    margin-bottom: 14px; font-size: 15px; font-weight: 700; color: var(--text);
                    letter-spacing: -.01em;
                }
                .marca .punto {
                    width: 9px; height: 9px; border-radius: 50%; background: var(--brand);
                    box-shadow: 0 0 0 4px rgba(99,102,241,.18);
                }
                .qa-badge {
                    display: flex; align-items: center; justify-content: center; gap: 7px;
                    width: fit-content; margin: 0 auto 22px;
                    padding: 6px 14px; border-radius: 999px;
                    background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412;
                    font-size: 12px; font-weight: 700; letter-spacing: .01em;
                }
                .caja {
                    background: var(--surface);
                    width: 100%;
                    max-width: 372px;
                    padding: 34px 32px;
                    border-radius: var(--radius-lg);
                    border: 1px solid var(--border);
                    box-shadow: var(--shadow-md);
                }
                h1 {
                    font-size: 19px;
                    font-weight: 700;
                    color: var(--text);
                    margin-bottom: 4px;
                    text-align: center;
                    letter-spacing: -.01em;
                }
                p.subtitulo {
                    font-size: 13px;
                    color: var(--text-muted);
                    text-align: center;
                    margin-bottom: 26px;
                }
                label {
                    display: block;
                    font-size: 12.5px;
                    font-weight: 600;
                    color: #334155;
                    margin-bottom: 6px;
                }
                input[type=text], input[type=password] {
                    width: 100%;
                    padding: 10px 13px;
                    font-size: 14px;
                    font-family: inherit;
                    color: var(--text);
                    background: #f9fafb;
                    border: 1px solid var(--border);
                    border-radius: var(--radius-md);
                    margin-bottom: 18px;
                    transition: border-color .12s, box-shadow .12s, background .12s;
                }
                input[type=text]:focus, input[type=password]:focus {
                    outline: none;
                    background: #fff;
                    border-color: var(--brand);
                    box-shadow: 0 0 0 3px rgba(99,102,241,.15);
                }
                button {
                    width: 100%;
                    padding: 11px;
                    font-size: 14px;
                    font-weight: 600;
                    font-family: inherit;
                    background: var(--brand);
                    color: #fff;
                    border: none;
                    border-radius: var(--radius-md);
                    cursor: pointer;
                    transition: background .12s;
                }
                button:hover { background: var(--brand-dark); }
                .error {
                    background: #fef2f2;
                    border: 1px solid #fecaca;
                    color: #b91c1c;
                    padding: 10px 13px;
                    border-radius: var(--radius-md);
                    font-size: 13px;
                    margin-bottom: 18px;
                }
            </style>
        </head>
        <body>
            <div>
                <div class="marca"><span class="punto"></span> CETECPRO</div>
                <div class="qa-badge">🧪 Aseguramiento de Calidad - PRUEBAS</div>
                <div class="caja">
                    <h1>Iniciar sesión</h1>
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
            </div>
            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => navigator.serviceWorker.register('sw.js'));
                }
            </script>
        </body>
        </html>
        <?php
    }
}
?>
