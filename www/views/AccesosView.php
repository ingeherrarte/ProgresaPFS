<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";

class AccesosView {

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 720px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            .card {
                background: var(--bg-tarjeta); max-width: 720px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); }
            td { color: var(--texto); }
            tbody tr:nth-child(even) { background: var(--fila-alterna); }
            .tipo-ingreso { color: #2e7d32; font-weight: bold; }
            .tipo-salida { color: #b71c1c; font-weight: bold; }
            .vacio { text-align: center; color: var(--sin-datos); font-size: 13px; padding: 12px 0; }
        </style>
        <?php
    }

    private static function barra(): void {
        ?>
        <div class="barra">
            <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
            <span>
                <a href="inicio.php">Inicio</a>
                <a href="admin.php">Administración</a>
                <a href="login.php?action=logout">Cerrar sesión</a>
                <?php Tema::boton(); ?>
            </span>
        </div>
        <?php
    }

    public static function mostrar(array $registros): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Registro de accesos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Registro de accesos</h1>

            <div class="card">
                <?php if (empty($registros)): ?>
                    <div class="vacio">Todavía no hay ingresos o salidas registrados.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Tipo</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $r): ?>
                                <?php $fechaHora = strtotime($r['fecha_hora']); ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['usuario']) ?></td>
                                    <td class="<?= $r['tipo'] === 'ingreso' ? 'tipo-ingreso' : 'tipo-salida' ?>">
                                        <?= $r['tipo'] === 'ingreso' ? 'Ingreso' : 'Salida' ?>
                                    </td>
                                    <td><?= date('d/m/Y', $fechaHora) ?></td>
                                    <td><?= date('H:i:s', $fechaHora) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
