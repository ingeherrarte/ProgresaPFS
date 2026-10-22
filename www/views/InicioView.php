<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";

class InicioView {

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 900px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 6px; color: var(--acento); text-align: center; }
            .subtitulo { text-align: center; font-size: 13px; color: var(--sin-datos); margin-bottom: 24px; }
            .contenedor { max-width: 900px; margin: 0 auto; }
            .accesos {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px; margin-bottom: 8px;
            }
            .acceso {
                background: var(--bg-tarjeta); border-radius: 8px; padding: 28px 20px; text-align: center;
                text-decoration: none; box-shadow: 0 1px 4px var(--sombra-tarjeta);
                transition: transform .15s, box-shadow .15s;
            }
            .acceso:hover { transform: translateY(-3px); box-shadow: 0 4px 12px var(--sombra-tarjeta); }
            .acceso .icono { font-size: 32px; margin-bottom: 10px; }
            .acceso .titulo { font-size: 15px; font-weight: bold; color: var(--acento); }
            .tarjetas {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px; margin-bottom: 28px;
            }
            .tarjeta {
                background: var(--bg-tarjeta); border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
                border-left: 4px solid var(--acento);
            }
            .tarjeta .etiqueta { font-size: 12px; color: var(--sin-datos); font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
            .tarjeta .valor { font-size: 28px; font-weight: bold; color: var(--acento); margin: 6px 0 2px; }
            .tarjeta .detalle { font-size: 12px; color: var(--sin-datos); }
            .cambiar-vista { text-align: center; margin-top: 24px; }
            .cambiar-vista a {
                font-size: 13px; color: var(--acento); text-decoration: none; font-weight: bold;
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
                <?php Tema::boton(); ?>
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
            <a class="acceso" href="consulta_pagos.php">
                <div class="icono">💵</div>
                <div class="titulo">Consulta de Pagos</div>
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
            <a class="acceso" href="conciliacion.php">
                <div class="icono">🔗</div>
                <div class="titulo">Conciliación de Transferencias</div>
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
            <?php Tema::scriptInicial(); ?>
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
                    <a href="inicio.php">Ver panel principal →</a>
                </div>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Panel principal (sidebar + topbar, estilo Nuxt/Vue) — vista por defecto
    // de inicio.php. "simple" y "dashboard" siguen disponibles vía ?vista=.
    private static array $navItems = [
        ['icono' => '🧑‍🎓', 'titulo' => 'Nuevo Estudiante', 'href' => 'estudiantespfs.php?action=form'],
        ['icono' => '🔎', 'titulo' => 'Buscar Estudiante', 'href' => 'estudiantespfs.php'],
        ['icono' => '🧾', 'titulo' => 'Nuevo Recibo', 'href' => 'recibospfs.php'],
        ['icono' => '🔎', 'titulo' => 'Buscar Recibos', 'href' => 'recibospfs.php?action=buscar'],
        ['icono' => '💵', 'titulo' => 'Consulta de Pagos', 'href' => 'consulta_pagos.php'],
        ['icono' => '📅', 'titulo' => 'Cierre del Día', 'href' => 'cierres.php'],
        ['icono' => '📈', 'titulo' => 'Cierre del Mes', 'href' => 'reporte_recibospfs.php'],
        ['icono' => '📊', 'titulo' => 'Cierre de Año', 'href' => 'cierres.php?tipo=anio'],
        ['icono' => '🏦', 'titulo' => 'Depósitos', 'href' => 'depositos.php'],
        ['icono' => '🔗', 'titulo' => 'Conciliación de Transferencias', 'href' => 'conciliacion.php'],
        ['icono' => '⚙️', 'titulo' => 'Administración', 'href' => 'admin.php'],
    ];

    public static function mostrarModerno(array $stats, bool $sinPermiso = false): void {
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
                :root {
                    --brand: #6366f1;
                    --brand-dark: #4f46e5;
                    --bg: #f8fafc;
                    --surface: #ffffff;
                    --border: #e5e7eb;
                    --text: #0f172a;
                    --text-muted: #64748b;
                    --radius-lg: 16px;
                    --radius-md: 12px;
                    --shadow-sm: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
                    --shadow-md: 0 8px 24px rgba(15,23,42,.08);
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: var(--bg); color: var(--text);
                    display: flex; min-height: 100vh;
                    -webkit-font-smoothing: antialiased;
                }
                a { text-decoration: none; color: inherit; }

                /* Sidebar */
                .sidebar {
                    width: 260px; flex-shrink: 0; background: #16142b;
                    color: #cbd5e1; display: flex; flex-direction: column;
                    position: sticky; top: 0; height: 100vh;
                }
                .marca {
                    display: flex; align-items: center; gap: 10px;
                    padding: 24px 22px; font-size: 16.5px; font-weight: 700; color: #fff;
                    letter-spacing: -.01em;
                    border-bottom: 1px solid rgba(255,255,255,.06);
                }
                .marca .punto {
                    width: 9px; height: 9px; border-radius: 50%; background: var(--brand);
                    box-shadow: 0 0 0 4px rgba(99,102,241,.25);
                }
                .nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
                .nav-item {
                    display: flex; align-items: center; gap: 11px;
                    padding: 9px 12px; border-radius: 10px; font-size: 13.5px; font-weight: 500;
                    margin-bottom: 2px; color: #cbd5e1; transition: background .12s, color .12s;
                }
                .nav-item .icono { font-size: 15px; width: 18px; text-align: center; opacity: .9; }
                .nav-item:hover { background: rgba(255,255,255,.07); color: #fff; }
                .sidebar-footer { padding: 16px 22px; border-top: 1px solid rgba(255,255,255,.06); font-size: 12.5px; }
                .sidebar-footer a { color: #a5b4fc; font-weight: 600; display: flex; align-items: center; gap: 6px; }
                .sidebar-footer a:hover { color: #c7d2fe; }

                /* Main */
                .main { flex: 1; min-width: 0; }
                .topbar {
                    background: var(--surface); border-bottom: 1px solid var(--border);
                    padding: 16px 30px; display: flex; align-items: center; justify-content: space-between;
                    position: sticky; top: 0; z-index: 5;
                }
                .topbar h1 { font-size: 16.5px; font-weight: 700; color: var(--text); letter-spacing: -.01em; }
                .topbar .breadcrumb { font-size: 12px; color: #94a3b8; margin-top: 2px; }
                .usuario-chip {
                    display: flex; align-items: center; gap: 10px; font-size: 13px; color: #475569;
                }
                .avatar {
                    width: 32px; height: 32px; border-radius: 50%; background: var(--brand); color: #fff;
                    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
                }
                .logout-btn {
                    font-size: 12px; font-weight: 600; color: var(--brand); border: 1px solid #e0e7ff;
                    padding: 6px 12px; border-radius: var(--radius-md); background: #eef2ff;
                    transition: background .12s;
                }
                .logout-btn:hover { background: #e0e7ff; }

                .contenido { padding: 28px 30px 40px; max-width: 1180px; }
                .errores {
                    background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                    padding: 10px 14px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 13px;
                }

                .stat-grid {
                    display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
                    gap: 18px; margin-bottom: 32px;
                }
                .stat-card {
                    background: var(--surface); border-radius: var(--radius-lg); padding: 18px 20px;
                    box-shadow: var(--shadow-sm); border: 1px solid var(--border);
                }
                .stat-card .top { display: flex; justify-content: space-between; align-items: flex-start; }
                .stat-card .etiqueta { font-size: 12px; color: var(--text-muted); font-weight: 600; }
                .stat-card .badge {
                    width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center;
                    justify-content: center; font-size: 16px;
                }
                .stat-card .valor { font-size: 26px; font-weight: 700; color: var(--text); margin-top: 10px; letter-spacing: -.02em; }
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
                    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px;
                    display: flex; align-items: center; gap: 12px;
                    box-shadow: var(--shadow-sm); transition: transform .12s, box-shadow .12s, border-color .12s;
                }
                .acceso-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: #c7d2fe; }
                .acceso-card .icono {
                    width: 38px; height: 38px; border-radius: 10px; background: #eef2ff;
                    display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
                }
                .acceso-card .titulo { font-size: 13.5px; font-weight: 600; color: #1e293b; }

                .cambiar-vista { text-align: center; margin-top: 30px; font-size: 12.5px; }
                .cambiar-vista a { color: var(--brand); font-weight: 600; }
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
                    <?php if ($sinPermiso): ?>
                        <div class="errores">⚠️ No tienes permiso para acceder a esa sección.</div>
                    <?php endif; ?>

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
            <?php Tema::scriptInicial(); ?>
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
                    <a href="inicio.php">Ver panel principal →</a>
                </div>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
