<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/EstudiantesPfsModel.php";

class EstudiantesPfsView {

    public static function mostrarBuscar(string $termino, array $filas, int $pagina, int $totalPaginas, int $totalRegistros): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Buscar Estudiantes — CETECPRO</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 960px; margin: 0 auto 16px;
                }
                .barra .usuario { font-size: 13px; color: #555; }
                .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
                .card {
                    background: #fff; max-width: 960px; margin: 0 auto 24px;
                    border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                }
                .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
                .campo { flex: 1; min-width: 200px; }
                label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
                input {
                    width: 100%; padding: 8px 10px; font-size: 14px;
                    border: 1px solid #ccc; border-radius: 4px;
                }
                input:focus { outline: none; border-color: #1a237e; }
                button {
                    padding: 9px 20px; font-size: 14px; font-weight: bold;
                    background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer;
                }
                button:hover { background: #283593; }
                table { border-collapse: collapse; width: 100%; font-size: 13px; }
                th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
                th { background: #1a237e; color: #fff; }
                tbody tr:nth-child(even) { background: #f5f7ff; }
                .sin-resultados { color: #777; font-style: italic; padding: 12px 0; }
                .resumen { font-size: 13px; color: #555; margin-bottom: 12px; }
                .estado-inactivo { color: #b71c1c; font-weight: bold; font-size: 11px; }
                .paginacion { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
                .paginacion a, .paginacion span {
                    padding: 6px 12px; border-radius: 4px; font-size: 13px; text-decoration: none;
                }
                .paginacion a { background: #f5f7ff; color: #1a237e; }
                .paginacion a:hover { background: #e0e4ff; }
                .paginacion span.actual { background: #1a237e; color: #fff; font-weight: bold; }
                .usar-btn {
                    display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: bold;
                    background: #2e7d32; color: #fff; text-decoration: none; border-radius: 4px;
                }
                .usar-btn:hover { background: #256428; }
                .ficha-btn {
                    display: inline-block; padding: 5px 8px; font-size: 13px;
                    text-decoration: none; border-radius: 4px; margin-left: 4px;
                    background: #eef2ff; line-height: 1;
                }
                .ficha-btn:hover { background: #dde3ff; }
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="inicio.php">Inicio</a>
                    <a href="estudiantespfs.php?action=form">Nuevo estudiante</a>
                    <a href="recibospfs.php">Nuevo recibo</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                </span>
            </div>
            <h1>Buscar Estudiantes</h1>

            <div class="card">
                <form method="GET" action="estudiantespfs.php" id="formBuscar">
                    <div class="fila">
                        <div class="campo">
                            <label for="q">Nombre, apellidos o carné</label>
                            <input type="text" id="q" name="q" value="<?= htmlspecialchars($termino) ?>" autofocus autocomplete="off">
                        </div>
                        <div>
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card" id="resultadosWrap" style="<?= $termino === '' ? 'display:none' : '' ?>">
                <p class="sin-resultados" id="sinResultados" style="<?= !empty($filas) ? 'display:none' : '' ?>">
                    No se encontraron estudiantes<?= $termino !== '' ? ' con "' . htmlspecialchars($termino) . '"' : '' ?>.
                </p>
                <div id="resultadosContenido" style="<?= empty($filas) ? 'display:none' : '' ?>">
                    <p class="resumen" id="resumenTexto"><?= $totalRegistros ?> resultado(s) para "<?= htmlspecialchars($termino) ?>"</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Carné</th>
                                <th>Nombre completo</th>
                                <th>Curso</th>
                                <th>Plan</th>
                                <th>Jornada</th>
                                <th>Teléfono</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyResultados">
                            <?php foreach ($filas as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['idestudiante']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(trim($f['nombre'] . ' ' . $f['apellidos'])) ?>
                                        <?php if ($f['activo'] != 1): ?>
                                            <br><span class="estado-inactivo">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($f['nombrecurso'] ?? 'No asignado') ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan($f['plan'])) ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada($f['jornada'])) ?></td>
                                    <td><?= htmlspecialchars($f['telefonomovil']) ?></td>
                                    <td>
                                        <a class="usar-btn" href="recibospfs.php?carnet=<?= urlencode($f['idestudiante']) ?>">Usar en recibo</a>
                                        <a class="ficha-btn" href="estudiantespfs.php?action=ficha&carnet=<?= urlencode($f['idestudiante']) ?>" target="_blank" rel="noopener" title="Ver ficha completa para imprimir">🖨️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="paginacion" id="paginacionDiv">
                        <?php if ($totalPaginas > 1): ?>
                            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                                <?php if ($p === $pagina): ?>
                                    <span class="actual"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="estudiantespfs.php?q=<?= urlencode($termino) ?>&pagina=<?= $p ?>"><?= $p ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                const qInput = document.getElementById('q');
                const resultadosWrap = document.getElementById('resultadosWrap');
                const sinResultados = document.getElementById('sinResultados');
                const resultadosContenido = document.getElementById('resultadosContenido');
                const resumenTexto = document.getElementById('resumenTexto');
                const tbodyResultados = document.getElementById('tbodyResultados');
                const paginacionDiv = document.getElementById('paginacionDiv');
                let temporizadorBusqueda = null;

                function escapeHtml(texto) {
                    const div = document.createElement('div');
                    div.textContent = texto ?? '';
                    return div.innerHTML;
                }

                function renderizarFilas(filas) {
                    tbodyResultados.innerHTML = filas.map(f => {
                        const inactivo = f.activo == 0 ? '<br><span class="estado-inactivo">Inactivo</span>' : '';
                        return `<tr>
                            <td>${escapeHtml(f.idestudiante)}</td>
                            <td>${escapeHtml(f.nombreCompleto)}${inactivo}</td>
                            <td>${escapeHtml(f.nombrecurso || 'No asignado')}</td>
                            <td>${escapeHtml(f.planNombre)}</td>
                            <td>${escapeHtml(f.jornadaNombre)}</td>
                            <td>${escapeHtml(f.telefonomovil)}</td>
                            <td>
                                <a class="usar-btn" href="recibospfs.php?carnet=${encodeURIComponent(f.idestudiante)}">Usar en recibo</a>
                                <a class="ficha-btn" href="estudiantespfs.php?action=ficha&carnet=${encodeURIComponent(f.idestudiante)}" target="_blank" rel="noopener" title="Ver ficha completa para imprimir">🖨️</a>
                            </td>
                        </tr>`;
                    }).join('');
                }

                function buscarEnVivo(termino) {
                    fetch('estudiantespfs.php?action=buscarJson&q=' + encodeURIComponent(termino))
                        .then(r => r.json())
                        .then(datos => {
                            resultadosWrap.style.display = '';
                            paginacionDiv.innerHTML = '';

                            if (datos.total === 0) {
                                sinResultados.style.display = '';
                                sinResultados.textContent = 'No se encontraron estudiantes con "' + termino + '".';
                                resultadosContenido.style.display = 'none';
                                return;
                            }

                            sinResultados.style.display = 'none';
                            resultadosContenido.style.display = '';
                            const nota = datos.total > datos.filas.length
                                ? ' (mostrando los ' + datos.filas.length + ' más recientes; presiona Buscar para ver todos)'
                                : '';
                            resumenTexto.textContent = datos.total + ' resultado(s) para "' + termino + '"' + nota;
                            renderizarFilas(datos.filas);
                        })
                        .catch(() => {});
                }

                // Vista previa en vivo desde 3 letras; el botón "Buscar" siempre
                // trae la página completa y paginada desde el servidor.
                qInput.addEventListener('input', () => {
                    clearTimeout(temporizadorBusqueda);
                    const termino = qInput.value.trim();
                    if (termino.length < 3) return;
                    temporizadorBusqueda = setTimeout(() => buscarEnVivo(termino), 300);
                });
            </script>
        </body>
        </html>
        <?php
    }

    // Ficha completa del estudiante, pensada para imprimir (botón "Imprimir"
    // + CSS de @media print que oculta la barra y el botón al imprimir).
    public static function mostrarFicha(array $e, string $nombreCurso): void {
        $dato = fn($valor) => $valor !== null && trim((string)$valor) !== '' ? htmlspecialchars($valor) : '—';
        $fecha = fn($valor) => $valor && $valor !== '0000-00-00' ? date('d/m/Y', strtotime($valor)) : '—';
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Ficha de Estudiante — <?= htmlspecialchars(trim($e['nombre'] . ' ' . $e['apellidos'])) ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #222; }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 800px; margin: 0 auto 16px;
                }
                .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-right: 12px; }
                .barra button {
                    padding: 7px 16px; font-size: 13px; font-weight: bold; cursor: pointer;
                    background: #1a237e; color: #fff; border: none; border-radius: 4px;
                }
                .ficha {
                    background: #fff; max-width: 800px; margin: 0 auto;
                    border-radius: 6px; padding: 28px 32px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                }
                .encabezado { text-align: center; margin-bottom: 14px; }
                .encabezado h1 { font-size: 18px; color: #1a237e; }
                .encabezado .subtitulo { font-size: 11px; color: #777; margin-top: 4px; }
                .identificacion {
                    text-align: center; border-top: 2px solid #1a237e; border-bottom: 2px solid #1a237e;
                    padding: 14px 8px; margin-bottom: 20px;
                }
                .identificacion .nombre { font-size: 28px; font-weight: bold; color: #111; line-height: 1.2; }
                .identificacion .renglon {
                    display: flex; justify-content: center; flex-wrap: wrap; gap: 8px 28px; margin-top: 10px;
                }
                .identificacion .campo-grande { font-size: 17px; font-weight: bold; color: #1a237e; }
                .identificacion .campo-grande span {
                    display: block; font-size: 10px; color: #777; font-weight: bold;
                    text-transform: uppercase; letter-spacing: .4px;
                }
                .estado-inactivo { color: #b71c1c; font-weight: bold; }
                .seccion { margin-bottom: 18px; }
                .seccion h2 {
                    font-size: 13px; color: #1a237e; text-transform: uppercase; letter-spacing: .4px;
                    border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 10px;
                }
                .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 24px; }
                .dato { font-size: 13px; padding: 3px 0; }
                .dato b { display: block; font-size: 11px; color: #777; font-weight: bold; text-transform: uppercase; }
                .observacion { font-size: 13px; white-space: pre-wrap; }
                @page { size: letter; margin: 1.5cm; }
                @media print {
                    body { background: #fff; padding: 0; }
                    .barra { display: none; }
                    .ficha { box-shadow: none; max-width: 100%; padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="barra">
                <span>
                    <a href="estudiantespfs.php">← Volver a la búsqueda</a>
                </span>
                <button onclick="window.print()">🖨️ Imprimir</button>
            </div>

            <div class="ficha">
                <div class="encabezado">
                    <h1>Ficha de Datos del Estudiante</h1>
                    <div class="subtitulo">CETECPRO · Impreso el <?= date('d/m/Y H:i') ?></div>
                </div>

                <div class="identificacion">
                    <div class="nombre"><?= htmlspecialchars(trim($e['nombre'] . ' ' . $e['apellidos'])) ?></div>
                    <div class="renglon">
                        <div class="campo-grande"><span>Carné</span><?= htmlspecialchars($e['idestudiante']) ?></div>
                        <div class="campo-grande"><span>Curso</span><?= htmlspecialchars($nombreCurso) ?></div>
                        <div class="campo-grande"><span>Plan</span><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan($e['plan'])) ?></div>
                        <div class="campo-grande"><span>Jornada</span><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada($e['jornada'])) ?></div>
                    </div>
                </div>

                <div class="seccion">
                    <h2>Datos generales</h2>
                    <div class="grid">
                        <div class="dato"><b>Fecha de nacimiento</b><?= $fecha($e['nacimiento']) ?></div>
                        <div class="dato"><b>Estado</b><?= (int)$e['activo'] === 1 ? 'Activo' : '<span class="estado-inactivo">Inactivo</span>' ?></div>
                        <div class="dato"><b>Fecha de inscripción</b><?= $fecha($e['fechainscripcion']) ?></div>
                        <div class="dato"><b>Último año cursado</b><?= $dato($e['ultimoanio']) ?></div>
                        <div class="dato"><b>Establecimiento</b><?= $dato($e['establecimiento']) ?></div>
                    </div>
                </div>

                <div class="seccion">
                    <h2>Contacto</h2>
                    <div class="grid">
                        <div class="dato"><b>DPI</b><?= $dato($e['dpi']) ?></div>
                        <div class="dato"><b>Cédula</b><?= $dato($e['cedula']) ?></div>
                        <div class="dato"><b>Dirección</b><?= $dato($e['direccion']) ?></div>
                        <div class="dato"><b>Email</b><?= $dato($e['email']) ?></div>
                        <div class="dato"><b>Teléfono móvil</b><?= $dato($e['telefonomovil']) ?></div>
                        <div class="dato"><b>Teléfono casa</b><?= $dato($e['telefonocasa']) ?></div>
                        <div class="dato"><b>Teléfono trabajo</b><?= $dato($e['telefonotrabajo']) ?></div>
                    </div>
                </div>

                <div class="seccion">
                    <h2>Datos del padre</h2>
                    <div class="grid">
                        <div class="dato"><b>Nombre</b><?= $dato(trim(($e['pnombre'] ?? '') . ' ' . ($e['papellidos'] ?? ''))) ?></div>
                        <div class="dato"><b>Cédula</b><?= $dato($e['pcedula']) ?></div>
                        <div class="dato"><b>Teléfono</b><?= $dato($e['ptelefono']) ?></div>
                        <div class="dato"><b>Trabajo</b><?= $dato($e['ptrabajo']) ?></div>
                        <div class="dato"><b>Teléfono trabajo</b><?= $dato($e['ptelefonotrabajo']) ?></div>
                        <div class="dato"><b>Dirección trabajo</b><?= $dato($e['pdirecciont']) ?></div>
                    </div>
                </div>

                <div class="seccion">
                    <h2>Datos de la madre</h2>
                    <div class="grid">
                        <div class="dato"><b>Nombre</b><?= $dato(trim(($e['mnombre'] ?? '') . ' ' . ($e['mapellidos'] ?? ''))) ?></div>
                        <div class="dato"><b>Cédula</b><?= $dato($e['mcedula']) ?></div>
                        <div class="dato"><b>Teléfono</b><?= $dato($e['mtelefono']) ?></div>
                        <div class="dato"><b>Trabajo</b><?= $dato($e['mtrabajo']) ?></div>
                        <div class="dato"><b>Teléfono trabajo</b><?= $dato($e['mtelefonotrabajo']) ?></div>
                        <div class="dato"><b>Dirección trabajo</b><?= $dato($e['mdirecciont']) ?></div>
                    </div>
                </div>

                <div class="seccion">
                    <h2>Otros</h2>
                    <div class="grid">
                        <div class="dato"><b>Cómo se enteró</b><?= $dato($e['enteradopor']) ?></div>
                    </div>
                    <?php if (trim((string)($e['observacion'] ?? '')) !== ''): ?>
                        <div class="dato" style="margin-top:8px"><b>Observación</b><span class="observacion"><?= htmlspecialchars($e['observacion']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    private static array $enteradoPor = [
        'alumno', 'amigo', 'exalumno', 'facebook', 'google', 'manta', 'motorista',
        'nuestrodiario', 'olx', 'otro', 'prensalibre', 'profesor', 'tiktok',
        'vendetuschivas', 'volante', 'youtube', 'zea influencer',
    ];

    public static function mostrarFormularioAlta(array $errores, array $data, array $cursos, ?string $mensaje = null): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Nuevo Estudiante — CETECPRO</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
                .barra {
                    display: flex; justify-content: space-between; align-items: center;
                    max-width: 800px; margin: 0 auto 16px;
                }
                .barra .usuario { font-size: 13px; color: #555; }
                .barra a { color: #1a237e; text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
                h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; text-align: center; }
                .card {
                    background: #fff; max-width: 800px; margin: 0 auto;
                    border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px rgba(0,0,0,.1);
                }
                fieldset { border: 1px solid #e0e0e0; border-radius: 6px; padding: 14px 18px; margin-bottom: 18px; }
                legend { font-size: 13px; font-weight: bold; color: #1a237e; padding: 0 6px; }
                .fila { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px; }
                .campo { flex: 1; min-width: 180px; }
                label { display: block; font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
                input, select {
                    width: 100%; padding: 8px 10px; font-size: 14px;
                    border: 1px solid #ccc; border-radius: 4px;
                }
                input:focus, select:focus { outline: none; border-color: #1a237e; }
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
            </style>
        </head>
        <body>
            <div class="barra">
                <span class="usuario">Usuario: <b><?= htmlspecialchars(Auth::nombreActual()) ?></b></span>
                <span>
                    <a href="inicio.php">Inicio</a>
                    <a href="estudiantespfs.php">Buscar estudiante</a>
                    <a href="recibospfs.php">Nuevo recibo</a>
                    <a href="login.php?action=logout">Cerrar sesión</a>
                </span>
            </div>
            <h1>Registrar Nuevo Estudiante</h1>

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

                <form method="POST" action="estudiantespfs.php?action=guardar">
                    <?php self::camposFormulario($data, $cursos); ?>

                    <button type="submit">Registrar estudiante</button>
                </form>
            </div>

            <?php self::scriptToggleMenor(); ?>
        </body>
        </html>
        <?php
    }

    // Los fieldsets del alta y de la edición (Administración) son
    // exactamente los mismos campos, así que se comparten desde aquí.
    public static function camposFormulario(array $data, array $cursos): void {
        $v = fn($campo) => htmlspecialchars($data[$campo] ?? '');
        $codcursoSel = (int)($data['codcurso'] ?? 0);
        $planSel = (int)($data['plan'] ?? 0);
        $jornadaSel = (int)($data['jornada'] ?? 0);

        // Los datos de padre/madre solo se piden para menores de edad. Se
        // calcula aquí para el render inicial (ej. al reabrir el formulario
        // tras un error de validación); el JS lo recalcula al vuelo cuando
        // el usuario cambia la fecha de nacimiento.
        $esMenor = false;
        $nacimiento = $data['nacimiento'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $nacimiento)) {
            $edad = (new DateTime($nacimiento))->diff(new DateTime())->y;
            $esMenor = $edad < 18;
        }
        $enteradoSel = $data['enteradopor'] ?? '';
        ?>
                    <fieldset>
                        <legend>Datos del estudiante</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" maxlength="30" value="<?= $v('nombre') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="apellidos">Apellidos</label>
                                <input type="text" id="apellidos" name="apellidos" maxlength="30" value="<?= $v('apellidos') ?>" required>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="nacimiento">Fecha de nacimiento</label>
                                <input type="date" id="nacimiento" name="nacimiento" value="<?= $v('nacimiento') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="dpi">DPI</label>
                                <input type="text" id="dpi" name="dpi" maxlength="13" value="<?= $v('dpi') ?>">
                            </div>
                            <div class="campo">
                                <label for="cedula">Cédula</label>
                                <input type="text" id="cedula" name="cedula" maxlength="15" value="<?= $v('cedula') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="direccion">Dirección</label>
                                <input type="text" id="direccion" name="direccion" maxlength="70" value="<?= $v('direccion') ?>">
                            </div>
                            <div class="campo">
                                <label for="email">Email</label>
                                <input type="text" id="email" name="email" maxlength="60" value="<?= $v('email') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="telefonomovil">Teléfono móvil</label>
                                <input type="text" id="telefonomovil" name="telefonomovil" maxlength="8" value="<?= $v('telefonomovil') ?>">
                            </div>
                            <div class="campo">
                                <label for="telefonocasa">Teléfono casa</label>
                                <input type="text" id="telefonocasa" name="telefonocasa" maxlength="8" value="<?= $v('telefonocasa') ?>">
                            </div>
                            <div class="campo">
                                <label for="telefonotrabajo">Teléfono trabajo</label>
                                <input type="text" id="telefonotrabajo" name="telefonotrabajo" maxlength="8" value="<?= $v('telefonotrabajo') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="ultimoanio">Último año cursado</label>
                                <input type="text" id="ultimoanio" name="ultimoanio" maxlength="4" value="<?= $v('ultimoanio') ?>">
                            </div>
                            <div class="campo">
                                <label for="establecimiento">Establecimiento</label>
                                <input type="text" id="establecimiento" name="establecimiento" maxlength="50" value="<?= $v('establecimiento') ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Curso</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="codcurso">Curso o diplomado</label>
                                <select id="codcurso" name="codcurso" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach ($cursos as $id => $nombre): ?>
                                        <option value="<?= $id ?>" <?= $id === $codcursoSel ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="plan">Día/Plan</label>
                                <select id="plan" name="plan" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (EstudiantesPfsModel::planes() as $codigo => $nombre): ?>
                                        <option value="<?= $codigo ?>" <?= $codigo === $planSel ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo">
                                <label for="jornada">Jornada</label>
                                <select id="jornada" name="jornada" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (EstudiantesPfsModel::jornadas() as $codigo => $nombre): ?>
                                        <option value="<?= $codigo ?>" <?= $codigo === $jornadaSel ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset id="fieldsetPadre" style="<?= $esMenor ? '' : 'display:none' ?>">
                        <legend>Datos del padre (menores de edad)</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="pnombre">Nombre</label>
                                <input type="text" id="pnombre" name="pnombre" maxlength="40" value="<?= $v('pnombre') ?>">
                            </div>
                            <div class="campo">
                                <label for="papellidos">Apellidos</label>
                                <input type="text" id="papellidos" name="papellidos" maxlength="40" value="<?= $v('papellidos') ?>">
                            </div>
                            <div class="campo">
                                <label for="pcedula">Cédula</label>
                                <input type="text" id="pcedula" name="pcedula" maxlength="15" value="<?= $v('pcedula') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="ptelefono">Teléfono</label>
                                <input type="text" id="ptelefono" name="ptelefono" maxlength="8" value="<?= $v('ptelefono') ?>">
                            </div>
                            <div class="campo">
                                <label for="ptrabajo">Lugar de trabajo</label>
                                <input type="text" id="ptrabajo" name="ptrabajo" maxlength="20" value="<?= $v('ptrabajo') ?>">
                            </div>
                            <div class="campo">
                                <label for="ptelefonotrabajo">Teléfono trabajo</label>
                                <input type="text" id="ptelefonotrabajo" name="ptelefonotrabajo" maxlength="8" value="<?= $v('ptelefonotrabajo') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="pdirecciont">Dirección trabajo</label>
                                <input type="text" id="pdirecciont" name="pdirecciont" maxlength="40" value="<?= $v('pdirecciont') ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset id="fieldsetMadre" style="<?= $esMenor ? '' : 'display:none' ?>">
                        <legend>Datos de la madre (menores de edad)</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="mnombre">Nombre</label>
                                <input type="text" id="mnombre" name="mnombre" maxlength="40" value="<?= $v('mnombre') ?>">
                            </div>
                            <div class="campo">
                                <label for="mapellidos">Apellidos</label>
                                <input type="text" id="mapellidos" name="mapellidos" maxlength="40" value="<?= $v('mapellidos') ?>">
                            </div>
                            <div class="campo">
                                <label for="mcedula">Cédula</label>
                                <input type="text" id="mcedula" name="mcedula" maxlength="15" value="<?= $v('mcedula') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="mtelefono">Teléfono</label>
                                <input type="text" id="mtelefono" name="mtelefono" maxlength="8" value="<?= $v('mtelefono') ?>">
                            </div>
                            <div class="campo">
                                <label for="mtrabajo">Lugar de trabajo</label>
                                <input type="text" id="mtrabajo" name="mtrabajo" maxlength="20" value="<?= $v('mtrabajo') ?>">
                            </div>
                            <div class="campo">
                                <label for="mtelefonotrabajo">Teléfono trabajo</label>
                                <input type="text" id="mtelefonotrabajo" name="mtelefonotrabajo" maxlength="8" value="<?= $v('mtelefonotrabajo') ?>">
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="mdirecciont">Dirección trabajo</label>
                                <input type="text" id="mdirecciont" name="mdirecciont" maxlength="40" value="<?= $v('mdirecciont') ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Otros</legend>
                        <div class="fila">
                            <div class="campo">
                                <label for="enteradopor">¿Cómo se enteró?</label>
                                <select id="enteradopor" name="enteradopor" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach (self::$enteradoPor as $opcion): ?>
                                        <option value="<?= $opcion ?>" <?= $opcion === $enteradoSel ? 'selected' : '' ?>><?= ucfirst($opcion) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="fila">
                            <div class="campo">
                                <label for="observacion">Observaciones</label>
                                <input type="text" id="observacion" name="observacion" maxlength="200" value="<?= $v('observacion') ?>">
                            </div>
                        </div>
                    </fieldset>

        <?php
    }

    // JS que muestra/oculta los fieldsets de padre/madre según la edad
    // calculada a partir de la fecha de nacimiento ingresada.
    public static function scriptToggleMenor(): void {
        ?>
<script>
                const nacimientoInput = document.getElementById('nacimiento');
                const fieldsetPadre = document.getElementById('fieldsetPadre');
                const fieldsetMadre = document.getElementById('fieldsetMadre');

                function actualizarDatosPadres() {
                    const valor = nacimientoInput.value;
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return;

                    const nacimiento = new Date(valor + 'T00:00:00');
                    const hoy = new Date();
                    let edad = hoy.getFullYear() - nacimiento.getFullYear();
                    const aunNoCumple = (hoy.getMonth() < nacimiento.getMonth())
                        || (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate());
                    if (aunNoCumple) edad--;

                    const esMenor = edad < 18;
                    fieldsetPadre.style.display = esMenor ? '' : 'none';
                    fieldsetMadre.style.display = esMenor ? '' : 'none';
                }

                nacimientoInput.addEventListener('change', actualizarDatosPadres);
                nacimientoInput.addEventListener('input', actualizarDatosPadres);
            </script>
        <?php
    }
}
?>
