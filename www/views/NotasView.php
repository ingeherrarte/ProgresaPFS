<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/EstudiantesPfsModel.php";
require_once __DIR__ . "/../models/NotasModel.php";
require_once __DIR__ . "/../helpers/Tema.php";

class NotasView {

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
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            h2 { font-size: 15px; color: var(--acento); margin: 0 0 12px; }
            .card {
                background: var(--bg-tarjeta); max-width: 900px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            fieldset { border: 1px solid var(--borde-input); border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
            legend { font-size: 13px; font-weight: bold; color: var(--acento); padding: 0 6px; }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 12px; }
            .campo { flex: 1; min-width: 160px; }
            label { display: block; font-size: 12px; font-weight: bold; color: var(--texto); margin-bottom: 4px; }
            input, select {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid var(--borde-input); border-radius: 4px;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            input:focus, select:focus { outline: none; border-color: var(--acento); }
            input.nota { max-width: 90px; }
            button {
                padding: 10px 20px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 7px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
            td { color: var(--texto); }
            tbody tr:nth-child(even) { background: var(--fila-alterna); }
            .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; max-height: 70vh; overflow-y: auto; }
            .sin-datos { color: var(--texto-muted); font-style: italic; padding: 8px 0; }
            .exito {
                background: #e8f5e9; border: 1px solid #81c784; color: #2e7d32;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .errores {
                background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .errores ul { margin: 6px 0 0 18px; }
            .pill {
                display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: bold;
                background: var(--fila-alterna); color: var(--acento);
            }
        </style>
        <?php
    }

    private static function barra(): void {
        ?>
        <div class="barra">
            <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
            <span>
                <a href="grupos.php">Grupos</a>
                <a href="inicio.php">Inicio</a>
                <a href="login.php?action=logout">Cerrar sesión</a>
                <?php Tema::boton(); ?>
            </span>
        </div>
        <?php
    }

    private static function selectorGrupo(array $grupos, ?array $grupoActual, int $anio, int $mes): void {
        ?>
        <form method="GET" action="notas.php">
            <div class="fila">
                <div class="campo">
                    <label for="grupo_id">Grupo</label>
                    <select id="grupo_id" name="grupo_id" required onchange="this.form.submit()">
                        <option value="">-- Seleccione un grupo --</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= ($grupoActual && (int)$grupoActual['id'] === (int)$g['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g['nombrecurso'] ?? ('#' . $g['codcurso'])) ?>
                                — <?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$g['plan'])) ?>
                                / <?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$g['jornada'])) ?>
                                (<?= (int)$g['total_miembros'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo" style="max-width:160px;">
                    <label for="mes">Mes</label>
                    <select id="mes" name="mes" onchange="this.form.submit()">
                        <?php foreach (NotasModel::meses() as $num => $nombre): ?>
                            <option value="<?= $num ?>" <?= $num === $mes ? 'selected' : '' ?>><?= ucfirst($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo" style="max-width:120px;">
                    <label for="anio">Año</label>
                    <input type="number" id="anio" name="anio" min="2000" max="2999" value="<?= $anio ?>" onchange="this.form.submit()">
                </div>
                <div><button type="submit">Ver</button></div>
            </div>
        </form>
        <?php if (empty($grupos)): ?>
            <p class="sin-datos">Todavía no hay grupos creados — <a href="grupos.php">crea uno primero</a>.</p>
        <?php endif; ?>
        <?php
    }

    public static function mostrarForm(array $grupos, ?array $grupo, int $anio, int $mes, array $miembros, ?string $mensaje, array $errores = []): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Notas — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Notas</h1>

            <div class="card">
                <?php if ($mensaje): ?><div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
                <?php if (!empty($errores)): ?>
                    <div class="errores">
                        ⚠️ <ul>
                            <?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php self::selectorGrupo($grupos, $grupo, $anio, $mes); ?>
            </div>

            <?php if ($grupo): ?>
                <div class="card">
                    <h2>
                        <?= htmlspecialchars($grupo['nombrecurso'] ?? ('#' . $grupo['codcurso'])) ?>
                        <span class="pill"><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$grupo['plan'])) ?></span>
                        <span class="pill"><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$grupo['jornada'])) ?></span>
                    </h2>
                    <p style="font-size:12.5px;color:var(--texto-muted);margin-bottom:12px;">
                        Evaluación de <?= htmlspecialchars(NotasModel::nombreMes($mes)) ?> <?= $anio ?> — deja vacío el campo de un estudiante para no calificarlo todavía (no es lo mismo que ponerle 0).
                        <a href="notas.php?action=historial&grupo_id=<?= (int)$grupo['id'] ?>" style="margin-left:8px;">Ver historial →</a>
                    </p>

                    <?php if (empty($miembros)): ?>
                        <p class="sin-datos">Este grupo todavía no tiene estudiantes — <a href="grupos.php?action=ver&id=<?= (int)$grupo['id'] ?>">agrégalos aquí</a>.</p>
                    <?php else: ?>
                        <form method="POST" action="notas.php?action=guardar">
                            <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">
                            <input type="hidden" name="anio" value="<?= $anio ?>">
                            <input type="hidden" name="mes" value="<?= $mes ?>">
                            <div class="tabla-scroll">
                            <table>
                                <thead>
                                    <tr><th>#</th><th>Carné</th><th>Nombre</th><th>Nota (0-100)</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($miembros as $i => $m): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= (int)$m['idestudiante'] ?></td>
                                            <td><?= htmlspecialchars($m['nombre'] . ' ' . $m['apellidos']) ?></td>
                                            <td>
                                                <input type="number" class="nota" name="nota[<?= (int)$m['idestudiante'] ?>]"
                                                       min="0" max="100" step="0.01"
                                                       value="<?= $m['nota'] !== null ? htmlspecialchars((string)$m['nota']) : '' ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <div style="margin-top:16px;">
                                <button type="submit">Guardar notas</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarHistorial(array $grupo, array $periodos): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Historial de notas — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Historial de notas</h1>

            <div class="card">
                <h2><?= htmlspecialchars($grupo['nombrecurso'] ?? ('#' . $grupo['codcurso'])) ?></h2>
                <p style="margin-bottom:12px;"><a href="notas.php?grupo_id=<?= (int)$grupo['id'] ?>">← Volver a calificar</a></p>
                <?php if (empty($periodos)): ?>
                    <p class="sin-datos">Todavía no se han registrado notas en este grupo.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr><th>Período</th><th>Calificados</th><th>Promedio</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodos as $p): ?>
                                <tr>
                                    <td><?= ucfirst(NotasModel::nombreMes((int)$p['mes'])) ?> <?= (int)$p['anio'] ?></td>
                                    <td><?= (int)$p['cantidad'] ?></td>
                                    <td><?= number_format((float)$p['promedio'], 2) ?></td>
                                    <td><a href="notas.php?grupo_id=<?= (int)$grupo['id'] ?>&anio=<?= (int)$p['anio'] ?>&mes=<?= (int)$p['mes'] ?>">Ver / corregir →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
