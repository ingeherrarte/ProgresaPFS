<?php
require_once __DIR__ . "/../helpers/Auth.php";

class InicioView {

    private static function estilos(): void {
        ?>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 900px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: #555; }
            .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            h1 { font-size: 22px; margin-bottom: 6px; color: #1a237e; text-align: center; }
            .subtitulo { text-align: center; font-size: 13px; color: #777; margin-bottom: 24px; }
            .contenedor { max-width: 900px; margin: 0 auto; }
            .accesos {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px; margin-bottom: 8px;
            }
            .acceso {
                background: #fff; border-radius: 8px; padding: 28px 20px; text-align: center;
                text-decoration: none; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                transition: transform .15s, box-shadow .15s;
            }
            .acceso:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
            .acceso .icono { font-size: 32px; margin-bottom: 10px; }
            .acceso .titulo { font-size: 15px; font-weight: bold; color: #1a237e; }
            .tarjetas {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px; margin-bottom: 28px;
            }
            .tarjeta {
                background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                border-left: 4px solid #1a237e;
            }
            .tarjeta .etiqueta { font-size: 12px; color: #777; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
            .tarjeta .valor { font-size: 28px; font-weight: bold; color: #1a237e; margin: 6px 0 2px; }
            .tarjeta .detalle { font-size: 12px; color: #999; }
            .cambiar-vista { text-align: center; margin-top: 24px; }
            .cambiar-vista a {
                font-size: 13px; color: #1a237e; text-decoration: none; font-weight: bold;
            }
            .cambiar-vista a:hover { text-decoration: underline; }
        </style>
        <?php
    }

    private static function barra(): void {
        ?>
        <div class="barra">
            <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
            <span>
                <a href="login.php?action=logout">Cerrar sesión</a>
            </span>
        </div>
        <?php
    }

    private static function gridAccesos(): void {
        ?>
        <div class="accesos">
            <a class="acceso" href="recibospfs.php">
                <div class="icono">🧾</div>
                <div class="titulo">Nuevo Recibo</div>
            </a>
            <a class="acceso" href="recibospfs.php?action=buscar">
                <div class="icono">🔎</div>
                <div class="titulo">Buscar Recibos</div>
            </a>
            <a class="acceso" href="estudiantespfs.php?action=form">
                <div class="icono">🧑‍🎓</div>
                <div class="titulo">Nuevo Estudiante</div>
            </a>
            <a class="acceso" href="estudiantespfs.php">
                <div class="icono">🔎</div>
                <div class="titulo">Buscar Estudiante</div>
            </a>
            <a class="acceso" href="cierres.php">
                <div class="icono">📅</div>
                <div class="titulo">Cierre del Día</div>
            </a>
            <a class="acceso" href="cierres.php?tipo=anio">
                <div class="icono">📊</div>
                <div class="titulo">Cierre de Año</div>
            </a>
            <a class="acceso" href="reporte_recibospfs.php">
                <div class="icono">📈</div>
                <div class="titulo">Reporte de Recibos</div>
            </a>
        </div>
        <?php
    }

    public static function mostrarSimple(): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Inicio — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>CETECPRO</h1>
            <p class="subtitulo">¿Qué deseas hacer?</p>

            <div class="contenedor">
                <?php self::gridAccesos(); ?>

                <div class="cambiar-vista">
                    <a href="inicio.php?vista=dashboard">Ver como resumen con estadísticas →</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    public static function mostrarDashboard(array $stats): void {
        $recibosHoy = $stats['recibosHoy'];
        $recibosMes = $stats['recibosMes'];
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Inicio — CETECPRO</title>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>CETECPRO</h1>
            <p class="subtitulo">Resumen de hoy</p>

            <div class="contenedor">
                <div class="tarjetas">
                    <div class="tarjeta">
                        <div class="etiqueta">Recibos hoy</div>
                        <div class="valor"><?= (int)$recibosHoy['cantidad'] ?></div>
                        <div class="detalle">Q <?= number_format($recibosHoy['total'], 2) ?> recaudados</div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Recibos este mes</div>
                        <div class="valor"><?= (int)$recibosMes['cantidad'] ?></div>
                        <div class="detalle">Q <?= number_format($recibosMes['total'], 2) ?> recaudados</div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Estudiantes activos</div>
                        <div class="valor"><?= (int)$stats['estudiantesActivos'] ?></div>
                        <div class="detalle">total inscritos activos</div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Nuevos este mes</div>
                        <div class="valor"><?= (int)$stats['estudiantesNuevosMes'] ?></div>
                        <div class="detalle">estudiantes registrados</div>
                    </div>
                </div>

                <?php self::gridAccesos(); ?>

                <div class="cambiar-vista">
                    <a href="inicio.php?vista=simple">← Ver como accesos simples</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
