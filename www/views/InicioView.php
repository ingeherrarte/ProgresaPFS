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
            .errores {
                background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
                max-width: 900px; margin-left: auto; margin-right: auto;
            }
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
            <a class="acceso" href="estudiantespfs.php?action=form">
                <div class="icono">🧑‍🎓</div>
                <div class="titulo">Nuevo Estudiante</div>
            </a>
            <a class="acceso" href="estudiantespfs.php">
                <div class="icono">🔎</div>
                <div class="titulo">Buscar Estudiante</div>
            </a>
            <a class="acceso" href="recibospfs.php">
                <div class="icono">🧾</div>
                <div class="titulo">Nuevo Recibo</div>
            </a>
            <a class="acceso" href="recibospfs.php?action=buscar">
                <div class="icono">🔎</div>
                <div class="titulo">Buscar Recibos</div>
            </a>
            <a class="acceso" href="cierres.php">
                <div class="icono">📅</div>
                <div class="titulo">Cierre del Día</div>
            </a>
            <a class="acceso" href="reporte_recibospfs.php">
                <div class="icono">📈</div>
                <div class="titulo">Cierre del Mes</div>
            </a>
            <a class="acceso" href="cierres.php?tipo=anio">
                <div class="icono">📊</div>
                <div class="titulo">Cierre de Año</div>
            </a>
            <a class="acceso" href="depositos.php">
                <div class="icono">🏦</div>
                <div class="titulo">Depósitos</div>
            </a>
            <a class="acceso" href="admin.php">
                <div class="icono">⚙️</div>
                <div class="titulo">Administración</div>
            </a>
        </div>
        <?php
    }

    public static function mostrarSimple(bool $sinPermiso = false): void {
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

            <?php if ($sinPermiso): ?>
                <div class="errores">⚠️ No tienes permiso para acceder a esa sección.</div>
            <?php endif; ?>

            <div class="contenedor">
                <?php self::gridAccesos(); ?>

                <div class="cambiar-vista">
                    <a href="inicio.php?vista=dashboard">Ver como resumen con estadísticas →</a>
                    &nbsp;·&nbsp;
                    <a href="inicio.php?vista=moderno">Ver estilo moderno (Laravel/Vue) →</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    // Vista alterna tipo panel admin (estilo Laravel/Vue: sidebar + topbar) para
    // que el usuario compare cómo se vería el sistema con ese look. No
    // reemplaza las vistas existentes (simple/dashboard), es una tercera
    // opción a la que se accede desde el link "Ver estilo moderno".
    private static array $navItems = [
        ['icono' => '🧑‍🎓', 'titulo' => 'Nuevo Estudiante', 'href' => 'estudiantespfs.php?action=form'],
        ['icono' => '🔎', 'titulo' => 'Buscar Estudiante', 'href' => 'estudiantespfs.php'],
        ['icono' => '🧾', 'titulo' => 'Nuevo Recibo', 'href' => 'recibospfs.php'],
        ['icono' => '🔎', 'titulo' => 'Buscar Recibos', 'href' => 'recibospfs.php?action=buscar'],
        ['icono' => '📅', 'titulo' => 'Cierre del Día', 'href' => 'cierres.php'],
        ['icono' => '📈', 'titulo' => 'Cierre del Mes', 'href' => 'reporte_recibospfs.php'],
        ['icono' => '📊', 'titulo' => 'Cierre de Año', 'href' => 'cierres.php?tipo=anio'],
        ['icono' => '🏦', 'titulo' => 'Depósitos', 'href' => 'depositos.php'],
        ['icono' => '⚙️', 'titulo' => 'Administración', 'href' => 'admin.php'],
    ];

    public static function mostrarModerno(array $stats): void {
        $recibosHoy = $stats['recibosHoy'];
        $recibosMes = $stats['recibosMes'];
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Inicio — CETECPRO</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #f1f5f9; color: #1e293b;
                    display: flex; min-height: 100vh;
                }
                a { text-decoration: none; color: inherit; }

                /* Sidebar */
                .sidebar {
                    width: 250px; flex-shrink: 0; background: #1e1b3a;
                    color: #cbd5e1; display: flex; flex-direction: column;
                    position: sticky; top: 0; height: 100vh;
                }
                .marca {
                    display: flex; align-items: center; gap: 10px;
                    padding: 22px 20px; font-size: 18px; font-weight: 800; color: #fff;
                    border-bottom: 1px solid rgba(255,255,255,.08);
                }
                .marca .punto { width: 10px; height: 10px; border-radius: 50%; background: #6366f1; }
                .nav { flex: 1; padding: 14px 10px; overflow-y: auto; }
                .nav-item {
                    display: flex; align-items: center; gap: 12px;
                    padding: 10px 14px; border-radius: 8px; font-size: 13.5px; font-weight: 600;
                    margin-bottom: 2px; transition: background .12s, color .12s;
                }
                .nav-item .icono { font-size: 16px; width: 20px; text-align: center; }
                .nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
                .sidebar-footer { padding: 14px 20px; border-top: 1px solid rgba(255,255,255,.08); font-size: 12px; }
                .sidebar-footer a { color: #a5b4fc; font-weight: 700; }

                /* Main */
                .main { flex: 1; min-width: 0; }
                .topbar {
                    background: #fff; border-bottom: 1px solid #e2e8f0;
                    padding: 14px 28px; display: flex; align-items: center; justify-content: space-between;
                    position: sticky; top: 0; z-index: 5;
                }
                .topbar h1 { font-size: 17px; color: #0f172a; }
                .topbar .breadcrumb { font-size: 12px; color: #94a3b8; margin-top: 2px; }
                .usuario-chip {
                    display: flex; align-items: center; gap: 10px; font-size: 13px; color: #475569;
                }
                .avatar {
                    width: 32px; height: 32px; border-radius: 50%; background: #6366f1; color: #fff;
                    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
                }
                .logout-btn {
                    font-size: 12px; font-weight: 700; color: #6366f1; border: 1px solid #e0e7ff;
                    padding: 6px 12px; border-radius: 8px; background: #eef2ff;
                }
                .logout-btn:hover { background: #e0e7ff; }

                .contenido { padding: 26px 28px 40px; }
                .errores {
                    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                    padding: 10px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 13px;
                }

                .stat-grid {
                    display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                    gap: 18px; margin-bottom: 30px;
                }
                .stat-card {
                    background: #fff; border-radius: 14px; padding: 18px 20px;
                    box-shadow: 0 1px 2px rgba(15,23,42,.06); border: 1px solid #eef1f6;
                }
                .stat-card .top { display: flex; justify-content: space-between; align-items: flex-start; }
                .stat-card .etiqueta { font-size: 12px; color: #64748b; font-weight: 600; }
                .stat-card .badge {
                    width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center;
                    justify-content: center; font-size: 16px;
                }
                .stat-card .valor { font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 10px; }
                .stat-card .detalle { font-size: 12px; color: #94a3b8; margin-top: 2px; }
                .badge.indigo { background: #eef2ff; }
                .badge.green { background: #ecfdf5; }
                .badge.amber { background: #fffbeb; }
                .badge.sky { background: #f0f9ff; }

                .seccion-titulo { font-size: 13px; font-weight: 700; color: #334155; margin: 4px 0 14px; }
                .accesos-grid {
                    display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px;
                }
                .acceso-card {
                    background: #fff; border: 1px solid #eef1f6; border-radius: 14px; padding: 18px;
                    display: flex; align-items: center; gap: 12px;
                    box-shadow: 0 1px 2px rgba(15,23,42,.05); transition: transform .12s, box-shadow .12s;
                }
                .acceso-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(15,23,42,.08); }
                .acceso-card .icono {
                    width: 38px; height: 38px; border-radius: 10px; background: #eef2ff;
                    display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
                }
                .acceso-card .titulo { font-size: 13.5px; font-weight: 700; color: #1e293b; }

                .cambiar-vista { text-align: center; margin-top: 30px; font-size: 12.5px; }
                .cambiar-vista a { color: #6366f1; font-weight: 700; }
                .cambiar-vista a:hover { text-decoration: underline; }

                @media (max-width: 720px) {
                    body { flex-direction: column; }
                    .sidebar { width: 100%; height: auto; position: static; flex-direction: row; overflow-x: auto; }
                    .marca { border-bottom: none; border-right: 1px solid rgba(255,255,255,.08); }
                    .nav { display: flex; padding: 8px; }
                    .nav-item { white-space: nowrap; }
                    .sidebar-footer { display: none; }
                }
            </style>
        </head>
        <body>
            <aside class="sidebar">
                <div class="marca"><span class="punto"></span> CETECPRO</div>
                <nav class="nav">
                    <?php foreach (self::$navItems as $item): ?>
                        <a class="nav-item" href="<?= htmlspecialchars($item['href']) ?>">
                            <span class="icono"><?= $item['icono'] ?></span>
                            <span><?= htmlspecialchars($item['titulo']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-footer">
                    <a href="login.php?action=logout">⎋ Cerrar sesión</a>
                </div>
            </aside>

            <div class="main">
                <div class="topbar">
                    <div>
                        <h1>Panel de inicio</h1>
                        <div class="breadcrumb">CETECPRO / Inicio</div>
                    </div>
                    <div class="usuario-chip">
                        <div class="avatar"><?= htmlspecialchars(mb_substr(Auth::nombreActual(), 0, 1)) ?></div>
                        <span><?= htmlspecialchars(Auth::nombreActual()) ?></span>
                        <a class="logout-btn" href="login.php?action=logout">Salir</a>
                    </div>
                </div>

                <div class="contenido">
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="top">
                                <div class="etiqueta">Recibos hoy</div>
                                <div class="badge indigo">🧾</div>
                            </div>
                            <div class="valor"><?= (int)$recibosHoy['cantidad'] ?></div>
                            <div class="detalle">Q <?= number_format($recibosHoy['total'], 2) ?> recaudados</div>
                        </div>
                        <div class="stat-card">
                            <div class="top">
                                <div class="etiqueta">Recibos este mes</div>
                                <div class="badge green">📈</div>
                            </div>
                            <div class="valor"><?= (int)$recibosMes['cantidad'] ?></div>
                            <div class="detalle">Q <?= number_format($recibosMes['total'], 2) ?> recaudados</div>
                        </div>
                        <div class="stat-card">
                            <div class="top">
                                <div class="etiqueta">Estudiantes activos</div>
                                <div class="badge sky">🧑‍🎓</div>
                            </div>
                            <div class="valor"><?= (int)$stats['estudiantesActivos'] ?></div>
                            <div class="detalle">total inscritos activos</div>
                        </div>
                        <div class="stat-card">
                            <div class="top">
                                <div class="etiqueta">Nuevos este mes</div>
                                <div class="badge amber">✨</div>
                            </div>
                            <div class="valor"><?= (int)$stats['estudiantesNuevosMes'] ?></div>
                            <div class="detalle">estudiantes registrados</div>
                        </div>
                    </div>

                    <div class="seccion-titulo">Accesos rápidos</div>
                    <div class="accesos-grid">
                        <?php foreach (self::$navItems as $item): ?>
                            <a class="acceso-card" href="<?= htmlspecialchars($item['href']) ?>">
                                <span class="icono"><?= $item['icono'] ?></span>
                                <span class="titulo"><?= htmlspecialchars($item['titulo']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="cambiar-vista">
                        <a href="inicio.php?vista=simple">← Volver al menú clásico</a>
                    </div>
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
                    &nbsp;·&nbsp;
                    <a href="inicio.php?vista=moderno">Ver estilo moderno (Laravel/Vue) →</a>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
