<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/EstudiantesPfsModel.php";
require_once __DIR__ . "/../helpers/Tema.php";

class GruposView {

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
            .card-ancha { max-width: 1200px; }
            .bloque-candidatos { margin-bottom: 20px; }
            .bloque-candidatos h3 { font-size: 13px; color: var(--texto); margin: 0 0 8px; }
            .bloque-candidatos .cabecera-bloque { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
            .bloque-candidatos .cabecera-bloque label { margin: 0; font-size: 12px; font-weight: normal; display: flex; align-items: center; gap: 6px; }
            .bloque-candidatos table input[type=checkbox] { width: auto; }
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
            button {
                padding: 10px 20px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            button.chico { padding: 6px 12px; font-size: 12.5px; }
            button.quitar { background: #b71c1c; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
            td { color: var(--texto); }
            tbody tr:nth-child(even) { background: var(--fila-alterna); }
            .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .sin-datos { color: var(--texto-muted); font-style: italic; padding: 8px 0; }
            .exito {
                background: #e8f5e9; border: 1px solid #81c784; color: #2e7d32;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .errores {
                background: #ffebee; border: 1px solid #e57373; color: #b71c1c;
                padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 13px;
            }
            .pill {
                display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: bold;
                background: var(--fila-alterna); color: var(--acento);
            }
            .acceso-mini {
                display: flex; align-items: center; gap: 8px; text-decoration: none; color: var(--acento);
                font-size: 13px; font-weight: bold;
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
                <?php Tema::boton(); ?>
            </span>
        </div>
        <?php
    }

    // Una tabla con checkbox por fila para agregar en bloque (ver
    // GruposModel::candidatos). $mostrarOrigen agrega la columna de
    // diplomado/plan/jornada actual — no hace falta en el primer bloque
    // porque ya coincide con el grupo que se está viendo.
    private static function bloqueCandidatos(string $titulo, array $filas, bool $mostrarOrigen = false): void {
        $claseBloque = 'bloque-' . substr(md5($titulo), 0, 8);
        ?>
        <div class="bloque-candidatos">
            <div class="cabecera-bloque">
                <input type="checkbox" onclick="marcarTodos(this, '<?= $claseBloque ?>')">
                <h3 style="margin:0;"><?= htmlspecialchars($titulo) ?> (<?= count($filas) ?>)</h3>
            </div>
            <?php if (empty($filas)): ?>
                <p class="sin-datos">Ninguno con pago reciente en esta categoría.</p>
            <?php else: ?>
                <div class="tabla-scroll">
                <table>
                    <thead>
                        <tr>
                            <th></th><th>Carné</th><th>Nombre</th>
                            <?php if ($mostrarOrigen): ?><th>Diplomado actual</th><?php endif; ?>
                            <th>Último pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $c): ?>
                            <?php $buscar = strtolower($c['idestudiante'] . ' ' . $c['nombre'] . ' ' . $c['apellidos']); ?>
                            <tr class="fila-candidato <?= $claseBloque ?>" data-buscar="<?= htmlspecialchars($buscar) ?>">
                                <td><input type="checkbox" name="idestudiantes[]" value="<?= (int)$c['idestudiante'] ?>"></td>
                                <td><?= (int)$c['idestudiante'] ?></td>
                                <td><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellidos']) ?></td>
                                <?php if ($mostrarOrigen): ?>
                                    <td>
                                        <?= htmlspecialchars($c['nombrecurso'] ?? '—') ?>
                                        — <?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$c['plan'])) ?>
                                        / <?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$c['jornada'])) ?>
                                    </td>
                                <?php endif; ?>
                                <td><?= $c['ultimo_pago'] ? date('d/m/Y', strtotime($c['ultimo_pago'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function mostrarForm(array $cursos, array $grupos): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Grupos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Grupos (Asistencia y Notas)</h1>

            <div class="card">
                <p style="font-size:12.5px;color:var(--texto-muted);margin-bottom:16px;">
                    Un grupo es la lista real de estudiantes de un Diplomado + Plan (día) + Jornada —
                    se arma a mano aquí, no se asume a partir del campo "activo" de Estudiantes
                    (ese campo no refleja quién asiste de verdad).
                </p>
                <form method="POST" action="grupos.php?action=crear">
                    <fieldset>
                        <legend>Abrir o crear un grupo</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="codcurso">Diplomado</label>
                                <select id="codcurso" name="codcurso" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach ($cursos as $id => $nombre): ?>
                                        <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="plan">Plan (día)</label>
                                <select id="plan" name="plan" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (EstudiantesPfsModel::planes() as $id => $nombre): ?>
                                        <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="jornada">Jornada</label>
                                <select id="jornada" name="jornada" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (EstudiantesPfsModel::jornadas() as $id => $nombre): ?>
                                        <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit">Abrir grupo →</button>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>

            <div class="card">
                <h2>Grupos existentes</h2>
                <?php if (empty($grupos)): ?>
                    <p class="sin-datos">Todavía no se ha creado ningún grupo.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Diplomado</th>
                                <th>Plan</th>
                                <th>Jornada</th>
                                <th>Miembros</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $g): ?>
                                <tr>
                                    <td><?= htmlspecialchars($g['nombrecurso'] ?? ('#' . $g['codcurso'])) ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$g['plan'])) ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$g['jornada'])) ?></td>
                                    <td><span class="pill"><?= (int)$g['total_miembros'] ?></span></td>
                                    <td><a href="grupos.php?action=ver&id=<?= (int)$g['id'] ?>">Ver / editar →</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <a class="acceso-mini" href="asistencia.php">📋 Ir a Asistencia</a>
                &nbsp;·&nbsp;
                <a class="acceso-mini" href="notas.php">📝 Ir a Notas</a>
            </div>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // $candidatos: ['mismoGrupo' => [...], 'mismoDiplomado' => [...], 'otrosDiplomados' => [...]]
    // (ver GruposModel::candidatos) — null si todavía no se calculó.
    public static function mostrarGrupo(array $grupo, array $miembros, string $termino, array $resultados, ?string $mensaje = null, ?array $candidatos = null): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Grupo — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1><?= htmlspecialchars($grupo['nombrecurso'] ?? ('#' . $grupo['codcurso'])) ?></h1>

            <div class="card">
                <?php if ($mensaje): ?><div class="exito">✅ <?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
                <p>
                    <span class="pill"><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$grupo['plan'])) ?></span>
                    <span class="pill"><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$grupo['jornada'])) ?></span>
                    <span class="pill"><?= count($miembros) ?> estudiante(s)</span>
                </p>
                <p style="margin-top:12px;"><a href="grupos.php">← Todos los grupos</a></p>
            </div>

            <div class="card card-ancha">
                <h2>Agregar estudiante por nombre o carné</h2>
                <p style="font-size:12.5px;color:var(--texto-muted);margin-bottom:12px;">
                    Úsalo para un estudiante que no aparece en los bloques de abajo (por ejemplo, un reingreso
                    sin pago reciente registrado).
                </p>
                <form method="GET" action="grupos.php">
                    <input type="hidden" name="action" value="ver">
                    <input type="hidden" name="id" value="<?= (int)$grupo['id'] ?>">
                    <div class="fila">
                        <div class="campo">
                            <label for="q">Nombre o carné</label>
                            <input type="text" id="q" name="q" value="<?= htmlspecialchars($termino) ?>" autofocus>
                        </div>
                        <div><button type="submit">Buscar</button></div>
                    </div>
                </form>

                <?php if ($termino !== ''): ?>
                    <?php if (empty($resultados)): ?>
                        <p class="sin-datos">Sin resultados para "<?= htmlspecialchars($termino) ?>".</p>
                    <?php else: ?>
                        <div class="tabla-scroll">
                        <table>
                            <thead>
                                <tr><th>Carné</th><th>Nombre</th><th>Diplomado actual</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultados as $r): ?>
                                    <tr>
                                        <td><?= (int)$r['idestudiante'] ?></td>
                                        <td><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellidos']) ?></td>
                                        <td><?= htmlspecialchars($r['nombrecurso'] ?? '—') ?></td>
                                        <td>
                                            <form method="POST" action="grupos.php?action=agregar" style="margin:0;">
                                                <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">
                                                <input type="hidden" name="idestudiante" value="<?= (int)$r['idestudiante'] ?>">
                                                <input type="hidden" name="q" value="<?= htmlspecialchars($termino) ?>">
                                                <button type="submit" class="chico">+ Agregar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($candidatos !== null): ?>
                <div class="card card-ancha">
                    <h2>Agregar en bloque (con pago reciente — máx. 2 meses de atraso)</h2>
                    <p style="font-size:12.5px;color:var(--texto-muted);margin-bottom:12px;">
                        Se excluye a quien ya es miembro. El "último pago" es la fecha del recibo más reciente — júzgalo tú,
                        no se asume nada además de ese dato. Para alguien fuera de este rango, usa el buscador de abajo.
                    </p>
                    <div class="fila" style="margin-bottom:16px;">
                        <div class="campo">
                            <label for="filtroCandidatos">Filtrar esta lista (nombre o carné)</label>
                            <input type="text" id="filtroCandidatos" oninput="filtrarCandidatos(this.value)" placeholder="Escribe para filtrar...">
                        </div>
                    </div>

                    <form method="POST" action="grupos.php?action=agregarVarios">
                        <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">

                        <?php self::bloqueCandidatos(
                            "Inscritos en este mismo Diplomado + Plan + Jornada",
                            $candidatos['mismoGrupo']
                        ); ?>
                        <?php self::bloqueCandidatos(
                            "Mismo diplomado, otro plan/jornada",
                            $candidatos['mismoDiplomado'],
                            true
                        ); ?>
                        <?php self::bloqueCandidatos(
                            "Inscritos en otros diplomados (primeros " . count($candidatos['otrosDiplomados']) . ")",
                            $candidatos['otrosDiplomados'],
                            true
                        ); ?>

                        <button type="submit">Agregar seleccionados</button>
                    </form>
                </div>

                <script>
                    function filtrarCandidatos(texto) {
                        texto = texto.toLowerCase();
                        document.querySelectorAll('.fila-candidato').forEach(function (fila) {
                            fila.hidden = texto !== '' && !fila.dataset.buscar.includes(texto);
                        });
                    }
                    function marcarTodos(casilla, clase) {
                        document.querySelectorAll('.' + clase + ':not([hidden])').forEach(function (fila) {
                            var cb = fila.querySelector('input[type=checkbox]');
                            if (cb) cb.checked = casilla.checked;
                        });
                    }
                </script>
            <?php endif; ?>

            <div class="card">
                <h2>Miembros del grupo</h2>
                <?php if (empty($miembros)): ?>
                    <p class="sin-datos">Todavía no hay estudiantes en este grupo — búscalos arriba.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead>
                            <tr><th>Carné</th><th>Nombre</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($miembros as $m): ?>
                                <tr>
                                    <td><?= (int)$m['idestudiante'] ?></td>
                                    <td><?= htmlspecialchars($m['nombre'] . ' ' . $m['apellidos']) ?></td>
                                    <td>
                                        <form method="POST" action="grupos.php?action=quitar" style="margin:0;" onsubmit="return confirm('¿Quitar a este estudiante del grupo?');">
                                            <input type="hidden" name="grupo_id" value="<?= (int)$grupo['id'] ?>">
                                            <input type="hidden" name="idestudiante" value="<?= (int)$m['idestudiante'] ?>">
                                            <button type="submit" class="chico quitar">Quitar</button>
                                        </form>
                                    </td>
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
