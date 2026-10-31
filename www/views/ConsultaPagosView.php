<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../models/EstudiantesPfsModel.php";
require_once __DIR__ . "/../models/ConsultaPagosModel.php";
require_once __DIR__ . "/../helpers/Tema.php";

class ConsultaPagosView {

    // fechadelpago se arma con el año ACTUAL al momento de registrar el
    // recibo (ver RecibosPfsController::guardar), así que un recibo tardío
    // queda con el año equivocado. horaregistro es el datetime real de
    // cuando se guardó el recibo, siempre confiable.
    private static function fechaHora(?string $valor): string {
        if (!$valor || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valor)) return '—';
        return (DateTime::createFromFormat('Y-m-d H:i:s', $valor))->format('d/m/Y H:i');
    }

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 1000px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            .card {
                background: var(--bg-tarjeta); max-width: 1000px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            .fila { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
            .campo { flex: 1; min-width: 200px; }
            label { display: block; font-size: 12px; font-weight: bold; color: var(--texto); margin-bottom: 4px; }
            input {
                width: 100%; padding: 8px 10px; font-size: 14px;
                border: 1px solid var(--borde-input); border-radius: 4px;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            input:focus { outline: none; border-color: var(--acento); }
            button {
                padding: 9px 20px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer;
            }
            button:hover { background: #283593; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); }
            td { color: var(--texto); }
            tbody tr:nth-child(even) { background: var(--fila-alterna); }
            .sin-resultados { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
            .resumen { font-size: 13px; color: var(--texto-muted); margin-bottom: 12px; }
            .estado-inactivo { color: #b71c1c; font-weight: bold; font-size: 11px; }
            .estado-anulado { color: #b71c1c; font-weight: bold; font-size: 11px; }
            .money { text-align: right; font-variant-numeric: tabular-nums; }
            .paginacion { display: flex; gap: 6px; justify-content: center; margin-top: 16px; flex-wrap: wrap; }
            .paginacion a, .paginacion span {
                padding: 6px 12px; border-radius: 4px; font-size: 13px; text-decoration: none;
            }
            .paginacion a { background: var(--fila-alterna); color: var(--acento); }
            .paginacion a:hover { background: #e0e4ff; }
            .paginacion span.actual { background: var(--acento); color: #fff; font-weight: bold; }
            .ver-btn {
                display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: bold;
                background: var(--acento); color: #fff; text-decoration: none; border-radius: 4px;
            }
            .ver-btn:hover { background: #283593; }
            .usar-btn {
                display: inline-block; padding: 5px 12px; font-size: 12px; font-weight: bold;
                background: #2e7d32; color: #fff; text-decoration: none; border-radius: 4px;
            }
            .usar-btn:hover { background: #256428; }
            .tarjetas {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 14px; margin-bottom: 24px;
            }
            .tarjeta {
                background: var(--fila-alterna); border-radius: 8px; padding: 16px; text-align: center;
                border: 1px solid var(--borde-input);
            }
            .tarjeta.total { background: var(--acento); color: #fff; border: none; }
            .tarjeta .etiqueta { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--texto-muted); font-weight: bold; }
            .tarjeta.total .etiqueta { color: #c5cae9; }
            .tarjeta .valor { font-size: 20px; font-weight: bold; margin-top: 6px; color: var(--acento); }
            .tarjeta.total .valor { color: #fff; }
            .info-grid {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 14px; margin-bottom: 22px; background: var(--fila-alterna); border-left: 4px solid var(--acento);
                border-radius: 6px; padding: 16px 20px;
            }
            .info-grid .dato b { display: block; color: var(--texto-muted); font-size: 11px; text-transform: uppercase; }
            .pill-estado { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; white-space: nowrap; }
            .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
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

    public static function mostrarBuscar(string $termino, array $filas, int $pagina, int $totalPaginas, int $totalRegistros): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Consulta de Pagos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Consulta de Pagos por Estudiante</h1>

            <div class="card">
                <form method="GET" action="consulta_pagos.php" id="formBuscar">
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
                <p style="margin-top:14px;">
                    <a href="consulta_pagos.php?action=reportes" style="font-size:13px;color:var(--acento);font-weight:bold;">📊 Reportes por diplomado (al día/mora, graduación, libro) →</a>
                </p>
            </div>

            <div class="card" id="resultadosWrap" style="<?= $termino === '' ? 'display:none' : '' ?>">
                <p class="sin-resultados" id="sinResultados" style="<?= !empty($filas) ? 'display:none' : '' ?>">
                    No se encontraron estudiantes con pagos registrados<?= $termino !== '' ? ' para "' . htmlspecialchars($termino) . '"' : '' ?>.
                </p>
                <div id="resultadosContenido" style="<?= empty($filas) ? 'display:none' : '' ?>">
                    <p class="resumen" id="resumenTexto"><?= $totalRegistros ?> estudiante(s) con pagos para "<?= htmlspecialchars($termino) ?>"</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Carné</th>
                                <th>Nombre completo</th>
                                <th>Curso</th>
                                <th>Recibos</th>
                                <th>Total pagado</th>
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
                                    <td><?= (int)$f['total_recibos'] ?></td>
                                    <td class="money">Q <?= number_format((float)$f['total_pagado'], 2) ?></td>
                                    <td>
                                        <a class="ver-btn" href="consulta_pagos.php?action=detalle&carnet=<?= urlencode($f['idestudiante']) ?>">Ver historial</a>
                                        <a class="usar-btn" href="recibospfs.php?carnet=<?= urlencode($f['idestudiante']) ?>">Usar en recibo</a>
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
                                    <a href="consulta_pagos.php?q=<?= urlencode($termino) ?>&pagina=<?= $p ?>"><?= $p ?></a>
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

                function formatoMoneda(valor) {
                    return 'Q ' + Number(valor).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function renderizarFilas(filas) {
                    tbodyResultados.innerHTML = filas.map(f => {
                        const inactivo = f.activo == 0 ? '<br><span class="estado-inactivo">Inactivo</span>' : '';
                        return `<tr>
                            <td>${escapeHtml(f.idestudiante)}</td>
                            <td>${escapeHtml(f.nombreCompleto)}${inactivo}</td>
                            <td>${escapeHtml(f.nombrecurso || 'No asignado')}</td>
                            <td>${escapeHtml(f.totalRecibos)}</td>
                            <td class="money">${formatoMoneda(f.totalPagado)}</td>
                            <td>
                                <a class="ver-btn" href="consulta_pagos.php?action=detalle&carnet=${encodeURIComponent(f.idestudiante)}">Ver historial</a>
                                <a class="usar-btn" href="recibospfs.php?carnet=${encodeURIComponent(f.idestudiante)}">Usar en recibo</a>
                            </td>
                        </tr>`;
                    }).join('');
                }

                function buscarEnVivo(termino) {
                    fetch('consulta_pagos.php?action=buscarJson&q=' + encodeURIComponent(termino))
                        .then(r => r.json())
                        .then(datos => {
                            resultadosWrap.style.display = '';
                            paginacionDiv.innerHTML = '';

                            if (datos.total === 0) {
                                sinResultados.style.display = '';
                                sinResultados.textContent = 'No se encontraron estudiantes con pagos registrados para "' + termino + '".';
                                resultadosContenido.style.display = 'none';
                                return;
                            }

                            sinResultados.style.display = 'none';
                            resultadosContenido.style.display = '';
                            const nota = datos.total > datos.filas.length
                                ? ' (mostrando los ' + datos.filas.length + ' más recientes; presiona Buscar para ver todos)'
                                : '';
                            resumenTexto.textContent = datos.total + ' estudiante(s) con pagos para "' + termino + '"' + nota;
                            renderizarFilas(datos.filas);
                        })
                        .catch(() => {});
                }

                qInput.addEventListener('input', () => {
                    clearTimeout(temporizadorBusqueda);
                    const termino = qInput.value.trim();
                    if (termino.length < 3) return;
                    temporizadorBusqueda = setTimeout(() => buscarEnVivo(termino), 300);
                });
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    // Semáforo: el color nunca va solo (accesibilidad — alguien con
    // daltonismo no puede depender del punto), siempre con el texto al
    // lado. Progresión verde→amarillo→rojo según qué tan atrasado está;
    // "Sin pagos" es gris aparte porque no es lo mismo que mora (nunca ha
    // pagado nada, no es que se haya atrasado).
    private static function pillEstado(string $estado): string {
        [$punto, $texto] = match ($estado) {
            'al_dia' => ['#2e7d32', 'Al día'],
            'debe_1_mes' => ['#f9a825', 'Debe 1 mes'],
            'debe_2_3_meses' => ['#ef6c00', 'Debe 2-3 meses'],
            'mora_mayor' => ['#c62828', 'Mora (4+ meses)'],
            default => ['#9e9e9e', 'Sin pagos registrados'],
        };
        return '<span class="pill-estado" style="display:inline-flex;align-items:center;gap:6px;">'
            . '<span style="width:10px;height:10px;border-radius:50%;background:' . $punto . ';flex-shrink:0;"></span>'
            . htmlspecialchars($texto) . '</span>';
    }

    // $filas: ver ConsultaPagosModel::reporteUnificado — una fila por
    // estudiante con estado_pago, monto_graduacion/detalle_graduacion,
    // monto_libro/detalle_libro ya resueltos. Todo el filtrado (texto,
    // diplomado, jornada, graduación, libro) es en el cliente vía
    // data-* por fila, igual patrón que GruposView::bloqueCandidatos —
    // sin esto habría que recargar la página por cada combinación de
    // filtros, y son solo un par de cientos de filas, no hace falta.
    public static function mostrarReportes(array $filas, array $cursos, string $patronGraduacion, string $patronLibro): void {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Reportes — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
            <style>
                .barra-filtros { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 14px; }
                .barra-filtros .campo { flex: 1; min-width: 160px; }
                .barra-filtros label { display: block; font-size: 11px; font-weight: bold; color: var(--texto-muted); margin-bottom: 3px; }
                .conteo-visible { font-size: 12.5px; color: var(--texto-muted); margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1 style="max-width:1400px;margin-left:auto;margin-right:auto;">Reportes</h1>

            <div class="card" style="max-width:1400px;">
                <a href="consulta_pagos.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Consulta de Pagos</a>
                <p style="font-size:12.5px;color:var(--texto-muted);margin:10px 0 0;">
                    Esta lista muestra a los estudiantes que ya están en algún Grupo (<a href="grupos.php">Grupos</a>) —
                    quien todavía no esté agregado a un grupo no aparece aquí. Para buscar a alguien específico sin
                    depender de Grupos, usa "<a href="consulta_pagos.php">Consulta de Pagos</a>".
                </p>
                <p style="font-size:12.5px;color:var(--texto-muted);margin:8px 0 0;">
                    Graduación y Libro son aproximados: buscan la palabra clave en el texto del recibo (no hay un campo
                    dedicado todavía) — filtra con las casillas de abajo.
                </p>
            </div>

            <div class="card" style="max-width:1400px;">
                <div class="barra-filtros">
                    <div class="campo">
                        <label for="fTexto">Nombre, carné o diplomado (o escribe "grad"/"libro")</label>
                        <input type="text" id="fTexto" placeholder="Escribe para filtrar..." autocomplete="off">
                    </div>
                    <div class="campo">
                        <label for="fDiplomado">Diplomado</label>
                        <select id="fDiplomado">
                            <option value="">-- Todos --</option>
                            <?php foreach ($cursos as $id => $nombre): ?>
                                <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="fPlan">Plan (día)</label>
                        <select id="fPlan">
                            <option value="">-- Todos --</option>
                            <?php foreach (EstudiantesPfsModel::planes() as $id => $nombre): ?>
                                <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo">
                        <label for="fJornada">Jornada</label>
                        <select id="fJornada">
                            <option value="">-- Todas --</option>
                            <?php foreach (EstudiantesPfsModel::jornadas() as $id => $nombre): ?>
                                <option value="<?= (int)$id ?>"><?= htmlspecialchars($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="campo" style="flex:0 0 auto;">
                        <label>Graduación</label>
                        <div style="display:flex;gap:10px;">
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fGrad" class="fGrad" value="" checked style="width:auto;"> Todos
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fGrad" class="fGrad" value="si" style="width:auto;"> Pagó
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fGrad" class="fGrad" value="no" style="width:auto;"> No ha pagado
                            </label>
                        </div>
                    </div>
                    <div class="campo" style="flex:0 0 auto;">
                        <label>Libro</label>
                        <div style="display:flex;gap:10px;">
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fLibro" class="fLibro" value="" checked style="width:auto;"> Todos
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fLibro" class="fLibro" value="si" style="width:auto;"> Compró
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fLibro" class="fLibro" value="no" style="width:auto;"> No ha comprado
                            </label>
                        </div>
                    </div>
                    <div class="campo" style="flex:0 0 auto;">
                        <label>Estado</label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="" checked style="width:auto;"> Todos
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="al_dia" style="width:auto;"> Al día
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="debe_1_mes" style="width:auto;"> Debe 1 mes
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="debe_2_3_meses" style="width:auto;"> Debe 2-3 meses
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="mora_mayor" style="width:auto;"> Mora (4+ meses)
                            </label>
                            <label style="display:flex;align-items:center;gap:5px;font-weight:normal;font-size:13px;color:var(--texto);white-space:nowrap;">
                                <input type="radio" name="fEstado" class="fEstado" value="sin_pagos" style="width:auto;"> Sin pagos
                            </label>
                        </div>
                    </div>
                </div>
                <p class="conteo-visible">
                    <span id="conteoVisible"><?= count($filas) ?></span> de <?= count($filas) ?> estudiante(s)
                    &nbsp;·&nbsp; Graduación: <span id="conteoGrad">0</span> (Q <span id="sumaGrad">0.00</span>)
                    &nbsp;·&nbsp; Libro: <span id="conteoLibro">0</span> (Q <span id="sumaLibro">0.00</span>)
                </p>

                <?php if (empty($filas)): ?>
                    <p class="sin-resultados">Todavía no hay estudiantes en ningún grupo — agrégalos desde <a href="grupos.php">Grupos</a>.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table id="tablaReporte">
                        <thead>
                            <tr>
                                <th>#</th><th>Carné</th><th>Nombre</th><th>Diplomado</th><th>Plan</th><th>Jornada</th>
                                <th>Último pago</th><th>Estado</th><th>Graduación</th><th>Libro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $i => $f): ?>
                                <?php
                                    $tieneGrad = $f['monto_graduacion'] !== null ? 'si' : 'no';
                                    $tieneLibro = $f['monto_libro'] !== null ? 'si' : 'no';
                                    // "graduacion"/"libro" solo se agregan cuando SÍ hay registro, para
                                    // que escribir "grad" o "libr" en el buscador funcione como atajo del
                                    // mismo filtro que las casillas de abajo (encuentra a quien pagó, no
                                    // a quien no — buscar "sin libro" no tiene sentido como texto libre).
                                    $buscar = mb_strtolower(
                                        $f['idestudiante'] . ' ' . $f['nombre'] . ' ' . $f['apellidos'] . ' ' . ($f['nombrecurso'] ?? '')
                                        . ($tieneGrad === 'si' ? ' graduacion' : '')
                                        . ($tieneLibro === 'si' ? ' libro' : ''),
                                        'UTF-8'
                                    );
                                ?>
                                <tr class="fila-reporte"
                                    data-buscar="<?= htmlspecialchars($buscar) ?>"
                                    data-diplomado="<?= (int)$f['codcurso'] ?>"
                                    data-plan="<?= (int)$f['plan'] ?>"
                                    data-jornada="<?= (int)$f['jornada'] ?>"
                                    data-estado="<?= htmlspecialchars($f['estado_pago']) ?>"
                                    data-grad="<?= $tieneGrad ?>"
                                    data-monto-grad="<?= $tieneGrad === 'si' ? (float)$f['monto_graduacion'] : 0 ?>"
                                    data-libro="<?= $tieneLibro ?>"
                                    data-monto-libro="<?= $tieneLibro === 'si' ? (float)$f['monto_libro'] : 0 ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td><?= (int)$f['idestudiante'] ?></td>
                                    <td><?= htmlspecialchars($f['nombre'] . ' ' . $f['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($f['nombrecurso'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan((int)$f['plan'])) ?></td>
                                    <td><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada((int)$f['jornada'])) ?></td>
                                    <td><?= $f['ultimo_pago'] ? date('d/m/Y', strtotime($f['ultimo_pago'])) : '—' ?></td>
                                    <td><?= self::pillEstado($f['estado_pago']) ?></td>
                                    <td title="<?= htmlspecialchars((string)$f['detalle_graduacion']) ?>">
                                        <?= $tieneGrad === 'si' ? 'Q ' . number_format((float)$f['monto_graduacion'], 2) : '<span style="color:var(--sin-datos);">—</span>' ?>
                                    </td>
                                    <td title="<?= htmlspecialchars((string)$f['detalle_libro']) ?>">
                                        <?= $tieneLibro === 'si' ? 'Q ' . number_format((float)$f['monto_libro'], 2) : '<span style="color:var(--sin-datos);">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <script>
                const fTexto = document.getElementById('fTexto');
                const fDiplomado = document.getElementById('fDiplomado');
                const fPlan = document.getElementById('fPlan');
                const fJornada = document.getElementById('fJornada');
                const fGrad = document.querySelectorAll('.fGrad');
                const fLibro = document.querySelectorAll('.fLibro');
                const fEstados = document.querySelectorAll('.fEstado');
                const conteoVisible = document.getElementById('conteoVisible');
                const conteoGrad = document.getElementById('conteoGrad');
                const sumaGrad = document.getElementById('sumaGrad');
                const conteoLibro = document.getElementById('conteoLibro');
                const sumaLibro = document.getElementById('sumaLibro');
                const filas = document.querySelectorAll('.fila-reporte');

                function formatoQ(valor) {
                    return valor.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Radio buttons: solo uno seleccionado por grupo, "Todos" (value="")
                // de default — a diferencia de los checkboxes anteriores, aquí no
                // existe el estado ambiguo de "marcar las dos opciones a la vez".
                function valorSeleccionado(nombreGrupo) {
                    const el = document.querySelector('input[name="' + nombreGrupo + '"]:checked');
                    return el ? el.value : '';
                }

                function aplicarFiltros() {
                    const texto = fTexto.value.trim().toLowerCase();
                    const estadoSel = valorSeleccionado('fEstado');
                    const gradSel = valorSeleccionado('fGrad');
                    const libroSel = valorSeleccionado('fLibro');
                    let visibles = 0, conGrad = 0, montoGrad = 0, conLibro = 0, montoLibro = 0;
                    filas.forEach(function (fila) {
                        const coincide =
                            (texto === '' || fila.dataset.buscar.includes(texto)) &&
                            (fDiplomado.value === '' || fila.dataset.diplomado === fDiplomado.value) &&
                            (fPlan.value === '' || fila.dataset.plan === fPlan.value) &&
                            (fJornada.value === '' || fila.dataset.jornada === fJornada.value) &&
                            (gradSel === '' || fila.dataset.grad === gradSel) &&
                            (libroSel === '' || fila.dataset.libro === libroSel) &&
                            (estadoSel === '' || fila.dataset.estado === estadoSel);
                        fila.hidden = !coincide;
                        if (coincide) {
                            visibles++;
                            if (fila.dataset.grad === 'si') { conGrad++; montoGrad += parseFloat(fila.dataset.montoGrad); }
                            if (fila.dataset.libro === 'si') { conLibro++; montoLibro += parseFloat(fila.dataset.montoLibro); }
                        }
                    });
                    if (conteoVisible) conteoVisible.textContent = visibles;
                    if (conteoGrad) conteoGrad.textContent = conGrad;
                    if (sumaGrad) sumaGrad.textContent = formatoQ(montoGrad);
                    if (conteoLibro) conteoLibro.textContent = conLibro;
                    if (sumaLibro) sumaLibro.textContent = formatoQ(montoLibro);
                }

                [fTexto, fDiplomado, fPlan, fJornada].forEach(function (el) {
                    if (el) el.addEventListener('input', aplicarFiltros);
                });
                [...fGrad, ...fLibro, ...fEstados].forEach(function (el) {
                    el.addEventListener('change', aplicarFiltros);
                });
                aplicarFiltros();
            </script>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }

    public static function mostrarDetalle(array $estudiante, array $pagos): void {
        $totalEfectivo = 0.0;
        $totalDeposito = 0.0;
        $totalCheque = 0.0;
        $totalRecibos = 0;

        foreach ($pagos as $p) {
            if ((int)$p['anulado'] === 1) continue;
            $totalEfectivo += (float)$p['efectivo'];
            $totalDeposito += (float)$p['deposito'];
            $totalCheque += (float)$p['cheque'];
            $totalRecibos++;
        }
        $totalGeneral = $totalEfectivo + $totalDeposito + $totalCheque;
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Historial de Pagos — <?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . $estudiante['apellidos'])) ?></title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Historial de Pagos</h1>

            <div class="card">
                <a class="ver-btn" href="consulta_pagos.php" style="margin-bottom:18px; display:inline-block;">← Volver a la búsqueda</a>
                <a class="usar-btn" href="recibospfs.php?carnet=<?= urlencode($estudiante['idestudiante']) ?>" style="margin-bottom:18px; margin-left:8px; display:inline-block;">Usar en recibo</a>

                <div class="info-grid">
                    <div class="dato"><b>Carné</b><?= htmlspecialchars($estudiante['idestudiante']) ?></div>
                    <div class="dato"><b>Nombre</b><?= htmlspecialchars(trim($estudiante['nombre'] . ' ' . $estudiante['apellidos'])) ?></div>
                    <div class="dato"><b>Curso</b><?= htmlspecialchars($estudiante['nombrecurso'] ?? 'No asignado') ?></div>
                    <div class="dato"><b>Plan</b><?= htmlspecialchars(EstudiantesPfsModel::nombrePlan($estudiante['plan'])) ?></div>
                    <div class="dato"><b>Jornada</b><?= htmlspecialchars(EstudiantesPfsModel::nombreJornada($estudiante['jornada'])) ?></div>
                    <div class="dato"><b>Teléfono</b><?= htmlspecialchars($estudiante['telefonomovil'] ?: '—') ?></div>
                    <?php if ((int)$estudiante['activo'] !== 1): ?>
                        <div class="dato"><b>Estado</b><span class="estado-inactivo">Inactivo</span></div>
                    <?php endif; ?>
                </div>

                <div class="tarjetas">
                    <div class="tarjeta">
                        <div class="etiqueta">Recibos</div>
                        <div class="valor"><?= $totalRecibos ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Efectivo</div>
                        <div class="valor">Q <?= number_format($totalEfectivo, 2) ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Depósito</div>
                        <div class="valor">Q <?= number_format($totalDeposito, 2) ?></div>
                    </div>
                    <div class="tarjeta">
                        <div class="etiqueta">Cheque</div>
                        <div class="valor">Q <?= number_format($totalCheque, 2) ?></div>
                    </div>
                    <div class="tarjeta total">
                        <div class="etiqueta">Total pagado</div>
                        <div class="valor">Q <?= number_format($totalGeneral, 2) ?></div>
                    </div>
                </div>

                <?php if (empty($pagos)): ?>
                    <p class="sin-resultados">Este estudiante no tiene recibos registrados.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Recibo #</th>
                                <th>Fecha</th>
                                <th>Efectivo</th>
                                <th>Depósito</th>
                                <th>Cheque</th>
                                <th>Total</th>
                                <th>Mes que paga</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $p): ?>
                                <?php
                                $efectivo = (float)$p['efectivo'];
                                $deposito = (float)$p['deposito'];
                                $cheque = (float)$p['cheque'];
                                $total = $efectivo + $deposito + $cheque;
                                $anulado = (int)$p['anulado'] === 1;

                                $detalleDeposito = $deposito > 0
                                    ? htmlspecialchars($p['banco'] ?: 'banco no indicado') . ' · No. ' . htmlspecialchars($p['nodeposito'])
                                    : null;
                                $detalleCheque = $cheque > 0
                                    ? 'No. ' . htmlspecialchars($p['nocheque']) . ($p['banco'] ? ' · ' . htmlspecialchars($p['banco']) : '')
                                    : null;
                                ?>
                                <tr<?= $anulado ? ' style="opacity:.55"' : '' ?>>
                                    <td>
                                        <?= htmlspecialchars($p['numero']) ?>
                                        <?php if ($anulado): ?>
                                            <br><span class="estado-anulado">Anulado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= self::fechaHora($p['horaregistro']) ?></td>
                                    <td class="money"><?= $efectivo > 0 ? 'Q ' . number_format($efectivo, 2) : '—' ?></td>
                                    <td class="money">
                                        <?php if ($deposito > 0): ?>
                                            Q <?= number_format($deposito, 2) ?>
                                            <br><span style="font-size:11px;color:#777"><?= $detalleDeposito ?></span>
                                            <?php if (!empty($p['foto_deposito'])): ?>
                                                <br><a href="uploads/recibos/<?= urlencode($p['foto_deposito']) ?>" target="_blank" rel="noopener" style="font-size:11px">Ver boleta</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="money">
                                        <?php if ($cheque > 0): ?>
                                            Q <?= number_format($cheque, 2) ?>
                                            <br><span style="font-size:11px;color:#777"><?= $detalleCheque ?></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="money"><b>Q <?= number_format($total, 2) ?></b></td>
                                    <td><?= htmlspecialchars(ConsultaPagosModel::nombreMes($p['mesquepaga'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($p['detalle']) ?>
                                        <?php if ($anulado && trim((string)$p['motivo_anulacion']) !== ''): ?>
                                            <br><span style="font-size:11px;color:#b71c1c">Motivo: <?= htmlspecialchars($p['motivo_anulacion']) ?></span>
                                        <?php endif; ?>
                                    </td>
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
