<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";
require_once __DIR__ . "/../models/DepositosModel.php";

class DashboardView {

    // Paleta de referencia validada (ver skill de dataviz del proyecto):
    // orden fijo de matices categóricos, nunca ciclado ni reasignado por
    // valor. slot 1 = efectivo, slot 2 = depósito, slot 3 = cheque — ese
    // orden no cambia aunque cheque casi siempre sea ínfimo.
    private const SERIE_EFECTIVO = '#2a78d6';
    private const SERIE_DEPOSITO = '#eb6834';
    private const SERIE_CHEQUE   = '#1baf7a';

    // "Depósitos: sistema vs. banco" — dos series nuevas que NO pueden
    // reusar azul/naranja/verde (ya significan efectivo/depósito/cheque en
    // esta página). Par validado con validate_palette.js en claro (#fcfcfb)
    // y oscuro (#1b1e24): CVD ΔE 15.2, visión normal ΔE 23.8, contraste ≥3:1.
    private const SERIE_SISTEMA = '#8456ce';
    private const SERIE_BANCO   = '#1b9e9e';

    // Paleta categórica para "Ingresos por diplomado" e "Ingresos por
    // Graduación/Libros/Batas" — cada barra su propio color, por posición
    // (nunca por valor/ranking). Deliberadamente sin azul/naranja/verde:
    // esos ya significan efectivo/depósito/cheque en esta misma página, y
    // reusarlos aquí para una categoría distinta (un diplomado, un
    // concepto) confundiría su significado ya establecido arriba. Cada
    // barra ya trae su etiqueta de texto al lado, así que no hace falta
    // leyenda aparte — el color es apoyo visual, no la única forma de
    // identificar la categoría.
    private const PALETA_CATEGORICA = [
        '#8456ce', '#d6336c', '#c99a2e', '#1b9e9e',
        '#4c6ef5', '#e8590c', '#2f9e44', '#868e96',
    ];

    private static function colorCategorico(int $indice): string {
        return self::PALETA_CATEGORICA[$indice % count(self::PALETA_CATEGORICA)];
    }

    // Estos apuntan a variables CSS (no a hex fijo): las gráficas son SVG
    // generado en servidor, pero el navegador sí resuelve var() dentro de
    // atributos de presentación (fill/stroke), así que cambian en vivo con
    // el toggle de tema sin recargar la página.
    private const SUPERFICIE = 'var(--chart-superficie)';
    private const TINTA_PRIMARIA = 'var(--chart-ink-primaria)';
    private const TINTA_SECUNDARIA = 'var(--chart-ink-secundaria)';
    private const TINTA_MUTED = 'var(--chart-ink-muted)';
    private const LINEA_REJILLA = 'var(--chart-grid)';
    private const LINEA_BASE = 'var(--chart-grid-fuerte)';

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            /* Tokens propios de esta página (KPIs y gráficas) — se agregan
               encima de los tokens genéricos de Tema::tokensBase(). */
            :root {
                --kpi-bg: #f5f7ff; --kpi-etiqueta: #666; --kpi-valor: #1a237e;
                --viz-leyenda-texto: #52514e;
                --chart-superficie: #fcfcfb; --chart-ink-primaria: #0b0b0b;
                --chart-ink-secundaria: #52514e; --chart-ink-muted: #898781;
                --chart-grid: #e1e0d9; --chart-grid-fuerte: #c3c2b7;
            }
            @media (prefers-color-scheme: dark) {
                :root:not([data-theme="light"]) { <?php self::tokensOscurosPropios(); ?> }
            }
            :root[data-theme="dark"] { <?php self::tokensOscurosPropios(); ?> }

            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 1100px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            h2 { font-size: 15px; color: var(--acento); margin: 0 0 14px; }
            .card {
                background: var(--bg-tarjeta); max-width: 1100px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            .filtros {
                display: flex; align-items: center; gap: 10px; max-width: 1100px; margin: 0 auto 18px;
            }
            .filtros label { font-size: 13px; font-weight: bold; color: var(--texto); }
            .filtros select {
                padding: 7px 10px; font-size: 14px; border: 1px solid var(--borde-input); border-radius: 4px;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            .kpis {
                display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
                gap: 16px;
            }
            .kpi {
                background: var(--kpi-bg); border-radius: 8px; padding: 16px 18px;
            }
            .kpi .etiqueta { font-size: 12px; font-weight: bold; color: var(--kpi-etiqueta); margin-bottom: 6px; }
            .kpi .valor { font-size: 24px; font-weight: 600; color: var(--kpi-valor); }
            .viz-leyenda {
                display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px; font-size: 12.5px; color: var(--viz-leyenda-texto);
            }
            .viz-leyenda .item { display: flex; align-items: center; gap: 6px; }
            .viz-leyenda .cuadro { width: 11px; height: 11px; border-radius: 2px; display: inline-block; }
            .viz-tooltip {
                position: fixed; display: none; pointer-events: none; z-index: 50;
                background: #0b0b0b; color: #fff; font-size: 12px; line-height: 1.5;
                padding: 8px 10px; border-radius: 5px; max-width: 240px;
                box-shadow: 0 2px 10px rgba(0,0,0,.25); white-space: pre-line;
            }
            .viz-tooltip b { font-size: 13px; }
            .tabla-toggle {
                font-size: 12.5px; color: var(--acento); font-weight: bold; cursor: pointer;
                background: none; border: none; padding: 0; margin-top: 10px; text-decoration: underline;
            }
            table.datos { border-collapse: collapse; width: 100%; font-size: 12.5px; margin-top: 10px; }
            table.datos th, table.datos td { border: 1px solid var(--tabla-borde); padding: 6px 9px; text-align: left; }
            table.datos th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); }
            table.datos td { color: var(--texto); }
            table.datos td.num { text-align: right; font-variant-numeric: tabular-nums; }
            .sin-datos { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
        </style>
        <?php
    }

    // Tokens oscuros propios de esta página (KPIs y gráficas) — los
    // genéricos (fondo, tarjeta, texto, acento, tabla...) ya los cubre
    // Tema::tokensBase(). En un solo lugar para no repetirlos entre el
    // bloque de preferencia de sistema y el de selección manual (data-theme).
    private static function tokensOscurosPropios(): void {
        ?>
        --kpi-bg: #262b38; --kpi-etiqueta: #aab0ba; --kpi-valor: #8c9eff;
        --viz-leyenda-texto: #c3c5cb;
        --chart-superficie: #1b1e24; --chart-ink-primaria: #e8e9ec;
        --chart-ink-secundaria: #c3c5cb; --chart-ink-muted: #8a8d95;
        --chart-grid: #33363d; --chart-grid-fuerte: #4a4d55;
        <?php
    }

    private static function barraSuperior(): void {
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

    // Formato compacto para los KPI (1,284 / Q12.9K / Q1.2M) — nunca
    // tabular-nums aquí, es un valor grande independiente, no una columna.
    private static function formatoCompacto(float $valor): string {
        if ($valor >= 1000000) {
            return 'Q' . number_format($valor / 1000000, 1) . 'M';
        }
        if ($valor >= 10000) {
            return 'Q' . number_format($valor / 1000, 1) . 'K';
        }
        return 'Q' . number_format($valor, 0);
    }

    private static function formatoQ(float $valor): string {
        return 'Q' . number_format($valor, 2);
    }

    // Techo "bonito" para el eje (1/2/5 × 10^n ≥ el valor real), en vez de
    // usar el máximo crudo como tope — así las líneas de la rejilla caen en
    // números redondos (ver marks-and-anatomy.md: "round to clean numbers").
    private static function escalaMaxima(float $valor): float {
        if ($valor <= 0) {
            return 100;
        }
        $magnitud = pow(10, floor(log10($valor)));
        $normalizado = $valor / $magnitud;
        if ($normalizado <= 1) {
            $paso = 1;
        } elseif ($normalizado <= 2) {
            $paso = 2;
        } elseif ($normalizado <= 5) {
            $paso = 5;
        } else {
            $paso = 10;
        }
        return $paso * $magnitud;
    }

    // Rectángulo con solo el extremo derecho redondeado (barra horizontal:
    // crece desde el eje a la izquierda, que queda cuadrado).
    private static function pathExtremoRedondeado(float $x, float $y, float $w, float $h, float $r): string {
        if ($h < $r * 2 || $w < $r * 2) {
            return "M $x $y h $w v $h h -$w Z";
        }
        $xw = $x + $w;
        $yh = $y + $h;
        $yr = $y + $r;
        $yhr = $yh - $r;
        $xwr = $xw - $r;
        return "M $x $y L $xwr $y Q $xw $y $xw $yr L $xw $yhr Q $xw $yh $xwr $yh L $x $yh Z";
    }

    // Gráfica de barras apiladas: ingresos por mes, 3 series (efectivo,
    // depósito, cheque) en orden fijo de slot — nunca reasignado.
    // Multi-línea: 3 series (efectivo/depósito/cheque), cada una su propio
    // valor mensual (no apiladas — una línea apilada sumaría visualmente
    // lo que ya no es una composición de un total, sino tres magnitudes
    // independientes a comparar). "Trend over time" → line, color
    // categórico en el mismo orden de slot que antes.
    private static function graficaIngresosPorMes(array $porMes, array $meses, int $mesLimite): string {
        $ancho = 760; $alto = 340;
        $margenIzq = 58; $margenDer = 16; $margenArriba = 16; $margenAbajo = 30;
        $anchoPlot = $ancho - $margenIzq - $margenDer;
        $altoPlot = $alto - $margenArriba - $margenAbajo;

        $series = [
            'efectivo' => ['color' => self::SERIE_EFECTIVO, 'etiqueta' => 'Efectivo'],
            'deposito' => ['color' => self::SERIE_DEPOSITO, 'etiqueta' => 'Depósito'],
            'cheque'   => ['color' => self::SERIE_CHEQUE,   'etiqueta' => 'Cheque'],
        ];

        $maxValor = 0;
        foreach ($porMes as $numMes => $m) {
            if ($numMes > $mesLimite) {
                continue;
            }
            foreach (array_keys($series) as $clave) {
                $maxValor = max($maxValor, (float)$m[$clave]);
            }
        }
        $escala = self::escalaMaxima($maxValor);

        $nombresMeses = array_values($meses);
        $numPuntos = count($nombresMeses);
        // Puntos de borde a borde del plot (no bandas centradas como en un
        // bar chart): el primer mes cae en x=margenIzq, el último en el
        // borde derecho del área de trazado.
        $pasoX = $numPuntos > 1 ? $anchoPlot / ($numPuntos - 1) : 0;

        $svg = '<svg viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" aria-label="Ingresos por mes: efectivo, depósito y cheque" style="width:100%; height:auto; font-family:inherit;">';
        $svg .= '<rect x="0" y="0" width="' . $ancho . '" height="' . $alto . '" fill="' . self::SUPERFICIE . '"/>';

        // Rejilla horizontal (4 líneas + base), hairline, sólida, recesiva.
        for ($i = 0; $i <= 4; $i++) {
            $y = $margenArriba + $altoPlot - ($altoPlot * $i / 4);
            $valor = $escala * $i / 4;
            $color = $i === 0 ? self::LINEA_BASE : self::LINEA_REJILLA;
            $svg .= '<line x1="' . $margenIzq . '" y1="' . round($y, 1) . '" x2="' . ($ancho - $margenDer) . '" y2="' . round($y, 1) . '" stroke="' . $color . '" stroke-width="1"/>';
            $svg .= '<text x="' . ($margenIzq - 8) . '" y="' . round($y + 3, 1) . '" text-anchor="end" font-size="10.5" fill="' . self::TINTA_MUTED . '">' . self::formatoCompacto($valor) . '</text>';
        }

        $puntosX = [];
        foreach ($nombresMeses as $idx => $nombreMes) {
            $x = $margenIzq + $idx * $pasoX;
            $puntosX[] = $x;
            $svg .= '<text x="' . round($x, 1) . '" y="' . ($alto - 10) . '" text-anchor="middle" font-size="11" fill="' . self::TINTA_MUTED . '">' . htmlspecialchars($nombreMes) . '</text>';
        }

        // Una polilínea de 2px por serie, trazada ANTES que los puntos de
        // cruce invisibles (el hit target de hover va encima, no estorba
        // el trazo). Solo hasta $mesLimite: en el año en curso los meses
        // futuros no tienen ingresos todavía, y graficarlos en 0 dibuja una
        // caída en picada que no refleja nada real — la línea se detiene en
        // el último mes con datos, el eje sigue mostrando el año completo.
        foreach ($series as $clave => $def) {
            $puntos = [];
            foreach (array_keys($nombresMeses) as $idx) {
                $mesNum = $idx + 1;
                if ($mesNum > $mesLimite) {
                    break;
                }
                $valor = (float)($porMes[$mesNum][$clave] ?? 0);
                $y = $margenArriba + $altoPlot - ($valor / $escala) * $altoPlot;
                $puntos[] = round($puntosX[$idx], 1) . ',' . round($y, 1);
            }
            $svg .= '<polyline points="' . implode(' ', $puntos) . '" fill="none" stroke="' . $def['color'] . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';

            foreach (array_keys($nombresMeses) as $idx) {
                $mesNum = $idx + 1;
                if ($mesNum > $mesLimite) {
                    break;
                }
                $valor = (float)($porMes[$mesNum][$clave] ?? 0);
                $y = $margenArriba + $altoPlot - ($valor / $escala) * $altoPlot;
                // Marcador ≥8px con anillo de 2px en el color de superficie,
                // para que no se pierda donde cruza otra línea.
                $svg .= '<circle cx="' . round($puntosX[$idx], 1) . '" cy="' . round($y, 1) . '" r="5" fill="' . $def['color'] . '" stroke="' . self::SUPERFICIE . '" stroke-width="2"/>';
            }
        }

        // Una columna invisible por mes = el hit target del crosshair
        // (más ancha que las líneas: "el área de hover es más grande que
        // la marca"). Un solo tooltip por mes lista las 3 series — el
        // lector no tiene que acertarle a una línea de 2px. Solo hasta
        // $mesLimite: los meses futuros no tienen datos que mostrar.
        foreach (array_keys($nombresMeses) as $idx) {
            $mesNum = $idx + 1;
            if ($mesNum > $mesLimite) {
                break;
            }
            $xCol = $margenIzq + $idx * $pasoX - $pasoX / 2;
            $filas = [];
            foreach ($series as $clave => $def) {
                $valor = (float)($porMes[$mesNum][$clave] ?? 0);
                $filas[] = ['etiqueta' => $def['etiqueta'], 'valor' => self::formatoQ($valor), 'color' => $def['color']];
            }
            $payload = htmlspecialchars(json_encode(['titulo' => ucfirst($nombresMeses[$idx]), 'filas' => $filas]), ENT_QUOTES);
            $svg .= '<rect x="' . round($xCol, 1) . '" y="' . $margenArriba . '" width="' . round($pasoX, 1) . '" height="' . $altoPlot . '" fill="transparent" class="viz-col" tabindex="0" data-x="' . round($puntosX[$idx], 1) . '" data-tooltip-json="' . $payload . '"/>';
        }

        $svg .= '<line class="viz-crosshair" x1="0" y1="' . $margenArriba . '" x2="0" y2="' . ($margenArriba + $altoPlot) . '" stroke="' . self::LINEA_BASE . '" stroke-width="1" style="display:none"/>';

        $svg .= '</svg>';
        return $svg;
    }

    // Gráfica de barras horizontales: ingresos por diplomado. Categorías
    // nominales (el orden es solo por magnitud, no cambia el significado) —
    // un único color para todas las barras, nunca un degradado por valor
    // (eso reasignaría la longitud de la barra al matiz, ver anti-patterns).
    private static function graficaIngresosPorDiplomado(array $porDiplomado): string {
        $filas = array_values(array_filter($porDiplomado, fn($f) => (float)$f['total'] > 0));
        if (empty($filas)) {
            return '';
        }

        // margenIzq 270: el nombre de diplomado más largo del catálogo
        // ("REPARACION DE COMPUTADORAS DE VEHICULO", 38 caracteres) no
        // cabe en menos sin que el texto se salga del lienzo del SVG (que
        // sí recorta en su propio borde, aunque no haya un clip-path
        // explícito) — probado con el catálogo completo, no solo los
        // diplomados con ingresos en el año actual.
        $margenIzq = 270; $margenDer = 70; $margenArriba = 10; $margenAbajo = 10;
        $altoFila = 30; $altoBarra = 20;
        $ancho = 760;
        $altoPlot = count($filas) * $altoFila;
        $alto = $altoPlot + $margenArriba + $margenAbajo;
        $anchoPlot = $ancho - $margenIzq - $margenDer;

        $max = self::escalaMaxima(max(array_column($filas, 'total')));

        $svg = '<svg viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" aria-label="Ingresos por diplomado" style="width:100%; height:auto; font-family:inherit;">';
        $svg .= '<rect x="0" y="0" width="' . $ancho . '" height="' . $alto . '" fill="' . self::SUPERFICIE . '"/>';
        $svg .= '<line x1="' . $margenIzq . '" y1="' . $margenArriba . '" x2="' . $margenIzq . '" y2="' . ($alto - $margenAbajo) . '" stroke="' . self::LINEA_BASE . '" stroke-width="1"/>';

        foreach ($filas as $i => $fila) {
            $y = $margenArriba + $i * $altoFila;
            $yBarra = $y + ($altoFila - $altoBarra) / 2;
            $anchoBarra = max(2, ($fila['total'] / $max) * $anchoPlot);

            $nombre = mb_strtoupper($fila['diplomado']) === $fila['diplomado'] && $fila['diplomado'] !== 'Sin curso asignado'
                ? mb_convert_case($fila['diplomado'], MB_CASE_TITLE)
                : $fila['diplomado'];

            $path = self::pathExtremoRedondeado($margenIzq, round($yBarra, 1), round($anchoBarra, 1), $altoBarra, 4);
            $titulo = $nombre . ': ' . self::formatoQ((float)$fila['total']) . ' (' . (int)$fila['cantidad'] . ' recibo(s))';
            $svg .= '<path d="' . $path . '" fill="' . self::colorCategorico($i) . '" class="viz-mark" tabindex="0" data-tooltip="' . htmlspecialchars($titulo) . '"><title>' . htmlspecialchars($titulo) . '</title></path>';

            $svg .= '<text x="' . ($margenIzq - 10) . '" y="' . round($y + $altoFila / 2 + 4, 1) . '" text-anchor="end" font-size="11.5" fill="' . self::TINTA_SECUNDARIA . '">' . htmlspecialchars($nombre) . '</text>';

            $etiquetaValor = self::formatoCompacto((float)$fila['total']);
            $svg .= '<text x="' . round($margenIzq + $anchoBarra + 8, 1) . '" y="' . round($y + $altoFila / 2 + 4, 1) . '" font-size="11.5" fill="' . self::TINTA_PRIMARIA . '">' . $etiquetaValor . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    // Gráfica de barras horizontales: Graduación/Libros/Batas, mismo patrón
    // que graficaIngresosPorDiplomado (3 categorías nominales, un solo
    // color, orden solo por magnitud) — margen izquierdo más angosto
    // porque las 3 etiquetas son cortas, a diferencia de los diplomados.
    private static function graficaIngresosPorConcepto(array $porConceptos): string {
        $margenIzq = 110; $margenDer = 70; $margenArriba = 10; $margenAbajo = 10;
        $altoFila = 34; $altoBarra = 22;
        $ancho = 760;
        $altoPlot = count($porConceptos) * $altoFila;
        $alto = $altoPlot + $margenArriba + $margenAbajo;
        $anchoPlot = $ancho - $margenIzq - $margenDer;

        $max = self::escalaMaxima(max(array_column($porConceptos, 'total')));

        $svg = '<svg viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" aria-label="Ingresos por Graduación, Libros y Batas" style="width:100%; height:auto; font-family:inherit;">';
        $svg .= '<rect x="0" y="0" width="' . $ancho . '" height="' . $alto . '" fill="' . self::SUPERFICIE . '"/>';
        $svg .= '<line x1="' . $margenIzq . '" y1="' . $margenArriba . '" x2="' . $margenIzq . '" y2="' . ($alto - $margenAbajo) . '" stroke="' . self::LINEA_BASE . '" stroke-width="1"/>';

        foreach ($porConceptos as $i => $fila) {
            $y = $margenArriba + $i * $altoFila;
            $yBarra = $y + ($altoFila - $altoBarra) / 2;
            $anchoBarra = $max > 0 ? max(2, ($fila['total'] / $max) * $anchoPlot) : 2;

            $path = self::pathExtremoRedondeado($margenIzq, round($yBarra, 1), round($anchoBarra, 1), $altoBarra, 4);
            $titulo = $fila['concepto'] . ': ' . self::formatoQ($fila['total']) . ' (' . $fila['estudiantes'] . ' estudiante(s), ' . $fila['cantidad'] . ' recibo(s))';
            $svg .= '<path d="' . $path . '" fill="' . self::colorCategorico($i) . '" class="viz-mark" tabindex="0" data-tooltip="' . htmlspecialchars($titulo) . '"><title>' . htmlspecialchars($titulo) . '</title></path>';

            $svg .= '<text x="' . ($margenIzq - 10) . '" y="' . round($y + $altoFila / 2 + 4, 1) . '" text-anchor="end" font-size="12" fill="' . self::TINTA_SECUNDARIA . '">' . htmlspecialchars($fila['concepto']) . '</text>';

            $etiquetaValor = self::formatoCompacto($fila['total']);
            $svg .= '<text x="' . round($margenIzq + $anchoBarra + 8, 1) . '" y="' . round($y + $altoFila / 2 + 4, 1) . '" font-size="12" fill="' . self::TINTA_PRIMARIA . '">' . $etiquetaValor . '</text>';
        }

        $svg .= '</svg>';
        return $svg;
    }

    // Barra vertical con solo el extremo superior redondeado (anclada a la
    // base, que queda cuadrada).
    private static function pathBarraVertical(float $x, float $y, float $w, float $h, float $r): string {
        if ($h < $r * 2 || $w < $r * 2) {
            return "M $x $y h $w v $h h -$w Z";
        }
        $xw = $x + $w; $yh = $y + $h; $yr = $y + $r; $xr = $x + $r; $xwr = $xw - $r;
        return "M $x $yh L $x $yr Q $x $y $xr $y L $xwr $y Q $xw $y $xw $yr L $xw $yh Z";
    }

    // Barras agrupadas por fecha "Correspondiente a": Sistema (suma de las
    // boletas de ese día) junto a Banco (suma de lo que el estado de cuenta
    // registra para esas boletas). Magnitud por categoría → barras; un
    // solo eje Y en quetzales para ambas series. Solo fechas con boletas
    // (eje categórico ordenado por fecha). Cuando el estado de cuenta de
    // alguna boleta del día aún no se importa, la barra Banco va solo con
    // contorno: está incompleta, no descuadrada. Un ▲ sobre el grupo marca
    // los días con diferencia ya importada (codificación secundaria, no
    // solo color).
    private static function graficaSistemaVsBanco(array $dias): string {
        $ancho = 960; $alto = 340;
        $margenIzq = 58; $margenDer = 12; $margenArriba = 22; $margenAbajo = 30;
        $anchoPlot = $ancho - $margenIzq - $margenDer;
        $altoPlot = $alto - $margenArriba - $margenAbajo;

        $max = 0;
        foreach ($dias as $d) {
            $max = max($max, $d['sistema'], $d['banco']);
        }
        $escala = self::escalaMaxima($max);
        $n = count($dias);
        $banda = $anchoPlot / max($n, 1);
        $hueco = 2;                                   // 2px de superficie entre barras
        $anchoBarra = max(2, min(22, ($banda * 0.8 - $hueco) / 2));
        $base = $margenArriba + $altoPlot;
        $cadaCuanto = max(1, (int)ceil($n / 14));     // etiquetas del eje sin encimarse

        $svg = '<svg viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" aria-label="Depósitos por fecha Correspondiente a: registrado en sistema contra registrado en banco" style="width:100%; height:auto; font-family:inherit;">';
        $svg .= '<rect x="0" y="0" width="' . $ancho . '" height="' . $alto . '" fill="' . self::SUPERFICIE . '"/>';
        for ($i = 0; $i <= 4; $i++) {
            $y = $margenArriba + $altoPlot - ($altoPlot * $i / 4);
            $svg .= '<line x1="' . $margenIzq . '" y1="' . round($y, 1) . '" x2="' . ($ancho - $margenDer) . '" y2="' . round($y, 1) . '" stroke="' . ($i === 0 ? self::LINEA_BASE : self::LINEA_REJILLA) . '" stroke-width="1"/>';
            $svg .= '<text x="' . ($margenIzq - 8) . '" y="' . round($y + 3, 1) . '" text-anchor="end" font-size="10.5" fill="' . self::TINTA_MUTED . '">' . self::formatoCompacto($escala * $i / 4) . '</text>';
        }

        foreach ($dias as $i => $d) {
            $centro = $margenIzq + $banda * ($i + 0.5);
            $xSis = $centro - $anchoBarra - $hueco / 2;
            $xBan = $centro + $hueco / 2;
            $hSis = $d['sistema'] / $escala * $altoPlot;
            $hBan = $d['banco'] / $escala * $altoPlot;
            $svg .= '<path d="' . self::pathBarraVertical(round($xSis, 1), round($base - $hSis, 1), round($anchoBarra, 1), round($hSis, 1), 4) . '" fill="' . self::SERIE_SISTEMA . '"/>';
            if ($d['pendientes'] > 0) {
                // Incompleta: contorno a la altura del sistema + relleno solo de lo ya confirmado.
                if ($hBan > 0) {
                    $svg .= '<path d="' . self::pathBarraVertical(round($xBan, 1), round($base - $hBan, 1), round($anchoBarra, 1), round($hBan, 1), 4) . '" fill="' . self::SERIE_BANCO . '"/>';
                }
                $svg .= '<path d="' . self::pathBarraVertical(round($xBan + 1, 1), round($base - $hSis + 1, 1), round($anchoBarra - 2, 1), round(max($hSis - 1, 0), 1), 4) . '" fill="none" stroke="' . self::SERIE_BANCO . '" stroke-width="1.5" stroke-dasharray="3 2"/>';
            } elseif ($hBan > 0) {
                $svg .= '<path d="' . self::pathBarraVertical(round($xBan, 1), round($base - $hBan, 1), round($anchoBarra, 1), round($hBan, 1), 4) . '" fill="' . self::SERIE_BANCO . '"/>';
            }

            $dif = round($d['sistema'] - $d['banco'], 2);
            if ($d['pendientes'] === 0 && abs($dif) >= 0.005) {
                $yMarca = $base - max($hSis, $hBan) - 6;
                $svg .= '<path d="M ' . round($centro - 4, 1) . ' ' . round($yMarca, 1) . ' l 4 -7 l 4 7 Z" fill="' . self::TINTA_SECUNDARIA . '"/>';
            }

            if ($i % $cadaCuanto === 0) {
                $svg .= '<text x="' . round($centro, 1) . '" y="' . ($alto - 10) . '" text-anchor="middle" font-size="10.5" fill="' . self::TINTA_MUTED . '">' . date('d/m', strtotime($d['dia'])) . '</text>';
            }

            $filas = [
                ['etiqueta' => 'Sistema (' . $d['boletas'] . ' boleta' . ($d['boletas'] === 1 ? '' : 's') . ')', 'valor' => self::formatoQ($d['sistema']), 'color' => self::SERIE_SISTEMA],
                ['etiqueta' => 'Banco', 'valor' => self::formatoQ($d['banco']), 'color' => self::SERIE_BANCO],
            ];
            if ($d['pendientes'] > 0) {
                $filas[] = ['etiqueta' => $d['pendientes'] . ' boleta(s) sin importar del estado de cuenta', 'valor' => '🕒', 'color' => 'transparent'];
            } elseif (abs($dif) >= 0.005) {
                $filas[] = ['etiqueta' => 'diferencia (sistema − banco)', 'valor' => '▲ ' . self::formatoQ($dif), 'color' => 'transparent'];
            }
            if ($d['no_aparece'] > 0) {
                $filas[] = ['etiqueta' => 'boleta(s) que no aparecen en el banco', 'valor' => (string)$d['no_aparece'], 'color' => 'transparent'];
            }
            if ($d['monto_distinto'] > 0) {
                $filas[] = ['etiqueta' => 'boleta(s) con monto distinto al banco', 'valor' => (string)$d['monto_distinto'], 'color' => 'transparent'];
            }
            $payload = htmlspecialchars(json_encode(['titulo' => 'Correspondiente a ' . date('d/m/Y', strtotime($d['dia'])), 'filas' => $filas]), ENT_QUOTES);
            $svg .= '<rect x="' . round($margenIzq + $banda * $i, 1) . '" y="' . $margenArriba . '" width="' . round($banda, 1) . '" height="' . $altoPlot . '" fill="transparent" class="viz-col" tabindex="0" data-x="' . round($centro, 1) . '" data-tooltip-json="' . $payload . '"/>';
        }
        $svg .= '<line class="viz-crosshair" x1="0" y1="' . $margenArriba . '" x2="0" y2="' . $base . '" stroke="' . self::LINEA_BASE . '" stroke-width="1" style="display:none"/>';
        $svg .= '</svg>';
        return $svg;
    }

    public static function mostrar(int $anio, int $anioActual, array $porMes, array $meses, array $porDiplomado, array $porConceptos, ?array $depositos = null): void {
        $totalEfectivo = array_sum(array_column($porMes, 'efectivo'));
        $totalDeposito = array_sum(array_column($porMes, 'deposito'));
        $totalCheque = array_sum(array_column($porMes, 'cheque'));
        $totalAnio = $totalEfectivo + $totalDeposito + $totalCheque;
        $totalRecibos = array_sum(array_column($porDiplomado, 'cantidad'));
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Dashboard — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barraSuperior(); ?>
            <h1>Dashboard de Ingresos</h1>

            <form method="GET" action="dashboard.php" class="filtros">
                <label for="anio">Año</label>
                <select id="anio" name="anio" onchange="this.form.submit()">
                    <?php for ($a = $anioActual; $a >= 2020; $a--): ?>
                        <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </form>

            <div class="card">
                <div class="kpis">
                    <div class="kpi">
                        <div class="etiqueta">Total del año</div>
                        <div class="valor"><?= self::formatoCompacto($totalAnio) ?></div>
                    </div>
                    <div class="kpi">
                        <div class="etiqueta">Efectivo</div>
                        <div class="valor"><?= self::formatoCompacto($totalEfectivo) ?></div>
                    </div>
                    <div class="kpi">
                        <div class="etiqueta">Depósito</div>
                        <div class="valor"><?= self::formatoCompacto($totalDeposito) ?></div>
                    </div>
                    <div class="kpi">
                        <div class="etiqueta">Recibos emitidos</div>
                        <div class="valor"><?= number_format($totalRecibos) ?></div>
                    </div>
                </div>
            </div>

            <?php
            // En el año en curso la línea se detiene en el mes actual (los
            // meses futuros aún no tienen ingresos); en un año cerrado se
            // ven los 12 meses completos.
            $mesLimite = $anio === $anioActual ? (int)date('n') : 12;
            ?>
            <div class="card">
                <h2>Ingresos por mes — <?= $anio ?></h2>
                <?= self::graficaIngresosPorMes($porMes, $meses, $mesLimite) ?>
                <div class="viz-leyenda">
                    <span class="item"><span class="cuadro" style="background:<?= self::SERIE_EFECTIVO ?>"></span>Efectivo</span>
                    <span class="item"><span class="cuadro" style="background:<?= self::SERIE_DEPOSITO ?>"></span>Depósito</span>
                    <span class="item"><span class="cuadro" style="background:<?= self::SERIE_CHEQUE ?>"></span>Cheque</span>
                </div>
                <button type="button" class="tabla-toggle" onclick="document.getElementById('tablaMeses').hidden = !document.getElementById('tablaMeses').hidden">Ver tabla de datos</button>
                <table class="datos" id="tablaMeses" hidden>
                    <thead><tr><th>Mes</th><th>Efectivo</th><th>Depósito</th><th>Cheque</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach (array_values($meses) as $idx => $nombreMes): $m = $porMes[$idx + 1]; $t = $m['efectivo'] + $m['deposito'] + $m['cheque']; ?>
                            <tr>
                                <td><?= htmlspecialchars($nombreMes) ?></td>
                                <td class="num"><?= self::formatoQ($m['efectivo']) ?></td>
                                <td class="num"><?= self::formatoQ($m['deposito']) ?></td>
                                <td class="num"><?= self::formatoQ($m['cheque']) ?></td>
                                <td class="num"><?= self::formatoQ($t) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($depositos !== null): ?>
            <div class="card">
                <h2>Depósitos: sistema vs. banco por "Correspondiente a" — <?= $anio ?></h2>
                <?php if (empty($depositos)): ?>
                    <p class="sin-datos">No hay boletas registradas para este año (el cuadre se lleva desde el <?= date('d/m/Y', strtotime(DepositosModel::INICIO_CUADRE)) ?>).</p>
                <?php else:
                    $totSis = array_sum(array_column($depositos, 'sistema'));
                    $totBan = array_sum(array_column($depositos, 'banco'));
                    $conDif = count(array_filter($depositos, fn($d) => $d['pendientes'] === 0 && abs($d['sistema'] - $d['banco']) >= 0.005));
                    $conPend = count(array_filter($depositos, fn($d) => $d['pendientes'] > 0));
                ?>
                    <p style="font-size:12.5px;color:var(--viz-leyenda-texto);margin:-6px 0 12px;">
                        Boletas del mismo día agrupadas y sumadas. Sistema <b><?= self::formatoQ($totSis) ?></b> ·
                        Banco <b><?= self::formatoQ($totBan) ?></b> ·
                        <?= $conDif ?> día(s) con diferencia · <?= $conPend ?> día(s) con estado de cuenta sin importar.
                    </p>
                    <?= self::graficaSistemaVsBanco($depositos) ?>
                    <div class="viz-leyenda">
                        <span class="item"><span class="cuadro" style="background:<?= self::SERIE_SISTEMA ?>"></span>Registrado en sistema (boletas)</span>
                        <span class="item"><span class="cuadro" style="background:<?= self::SERIE_BANCO ?>"></span>Registrado en banco (estado de cuenta)</span>
                        <span class="item"><span class="cuadro" style="border:1.5px dashed <?= self::SERIE_BANCO ?>"></span>Estado de cuenta aún sin importar</span>
                        <span class="item">▲ Diferencia sistema − banco</span>
                    </div>
                    <button type="button" class="tabla-toggle" onclick="document.getElementById('tablaDepositos').hidden = !document.getElementById('tablaDepositos').hidden">Ver tabla de datos</button>
                    <a href="cuadre_depositos.php" style="font-size:12.5px;font-weight:bold;color:var(--acento);margin-left:14px;">Ir a Cuadre de Depósitos →</a>
                    <div style="overflow-x:auto;">
                    <table class="datos" id="tablaDepositos" hidden>
                        <thead><tr><th>Correspondiente a</th><th>Boletas</th><th>Sistema</th><th>Banco</th><th>Diferencia</th><th>Nota</th></tr></thead>
                        <tbody>
                            <?php foreach (array_reverse($depositos) as $d): $dif = round($d['sistema'] - $d['banco'], 2); ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
                                    <td class="num"><?= (int)$d['boletas'] ?></td>
                                    <td class="num"><?= self::formatoQ($d['sistema']) ?></td>
                                    <td class="num"><?= self::formatoQ($d['banco']) ?></td>
                                    <td class="num"><?= abs($dif) < 0.005 ? '—' : self::formatoQ($dif) ?></td>
                                    <td><?php
                                        $notas = [];
                                        if ($d['pendientes']) $notas[] = $d['pendientes'] . ' sin importar';
                                        if ($d['no_aparece']) $notas[] = $d['no_aparece'] . ' no aparece(n) en banco';
                                        if ($d['monto_distinto']) $notas[] = $d['monto_distinto'] . ' con monto distinto';
                                        echo htmlspecialchars(implode(' · ', $notas) ?: '—');
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Ingresos por diplomado — <?= $anio ?></h2>
                <?php if (empty(array_filter($porDiplomado, fn($f) => $f['total'] > 0))): ?>
                    <p class="sin-datos">No hay ingresos registrados para este año.</p>
                <?php else: ?>
                    <?= self::graficaIngresosPorDiplomado($porDiplomado) ?>
                    <button type="button" class="tabla-toggle" onclick="document.getElementById('tablaDiplomados').hidden = !document.getElementById('tablaDiplomados').hidden">Ver tabla de datos</button>
                    <table class="datos" id="tablaDiplomados" hidden>
                        <thead><tr><th>Diplomado</th><th>Recibos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($porDiplomado as $f): if ($f['total'] <= 0) continue; ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['diplomado']) ?></td>
                                    <td class="num"><?= (int)$f['cantidad'] ?></td>
                                    <td class="num"><?= self::formatoQ($f['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Ingresos por Graduación, Libros y Batas — <?= $anio ?></h2>
                <p style="font-size:12px;color:var(--sin-datos);margin:-6px 0 12px;">
                    Aproximado: no existe un campo dedicado para estos conceptos, se detectan por palabra clave en el
                    texto del recibo — un recibo que mezcla el concepto con otra cosa en la misma línea puede sumar de más.
                </p>
                <?php if (empty(array_filter($porConceptos, fn($f) => $f['total'] > 0))): ?>
                    <p class="sin-datos">No hay ingresos detectados para estos conceptos en este año.</p>
                <?php else: ?>
                    <?= self::graficaIngresosPorConcepto($porConceptos) ?>
                    <button type="button" class="tabla-toggle" onclick="document.getElementById('tablaConceptos').hidden = !document.getElementById('tablaConceptos').hidden">Ver tabla de datos</button>
                    <table class="datos" id="tablaConceptos" hidden>
                        <thead><tr><th>Concepto</th><th>Estudiantes</th><th>Recibos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($porConceptos as $f): ?>
                                <tr>
                                    <td><?= htmlspecialchars($f['concepto']) ?></td>
                                    <td class="num"><?= (int)$f['estudiantes'] ?></td>
                                    <td class="num"><?= (int)$f['cantidad'] ?></td>
                                    <td class="num"><?= self::formatoQ($f['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="viz-tooltip" id="vizTooltip"></div>
            <?php Tema::script(); ?>
            <script>
                // Hover/focus en cada marca (barra o segmento): el propio
                // mark es el hit target, no hace falta un overlay aparte.
                // Mismo tooltip para mouse (pointermove) y teclado (focus).
                const tooltip = document.getElementById('vizTooltip');
                function mostrarTooltip(ev, texto) {
                    tooltip.textContent = texto;
                    tooltip.style.display = 'block';
                    const x = ev.clientX ?? (ev.target.getBoundingClientRect().right);
                    const y = ev.clientY ?? (ev.target.getBoundingClientRect().top);
                    tooltip.style.left = Math.min(x + 14, window.innerWidth - 250) + 'px';
                    tooltip.style.top = Math.max(y - 10, 10) + 'px';
                }
                function ocultarTooltip() {
                    tooltip.style.display = 'none';
                }
                document.querySelectorAll('.viz-mark').forEach(el => {
                    const texto = el.getAttribute('data-tooltip');
                    el.addEventListener('pointermove', ev => mostrarTooltip(ev, texto));
                    el.addEventListener('pointerleave', ocultarTooltip);
                    el.addEventListener('focus', ev => mostrarTooltip(ev, texto));
                    el.addEventListener('blur', ocultarTooltip);
                    el.style.cursor = 'pointer';
                    el.addEventListener('pointerenter', () => el.style.opacity = '0.85');
                    el.addEventListener('pointerleave', () => el.style.opacity = '1');
                });

                // Columna de cada mes en la gráfica de líneas: un solo
                // tooltip con las 3 series (el lector no tiene que acertar
                // a una línea de 2px) + una línea vertical (crosshair) que
                // salta al mes más cercano. Construido con createElement/
                // textContent, nunca innerHTML con texto concatenado.
                function construirTooltip(datos) {
                    tooltip.replaceChildren();
                    const titulo = document.createElement('b');
                    titulo.textContent = datos.titulo;
                    tooltip.appendChild(titulo);
                    datos.filas.forEach(fila => {
                        const renglon = document.createElement('div');
                        const marca = document.createElement('span');
                        marca.textContent = '▬ ';
                        marca.style.color = fila.color;
                        const texto = document.createElement('span');
                        texto.textContent = fila.valor + ' — ' + fila.etiqueta;
                        renglon.appendChild(marca);
                        renglon.appendChild(texto);
                        tooltip.appendChild(renglon);
                    });
                }
                document.querySelectorAll('.viz-col').forEach(el => {
                    const datos = JSON.parse(el.getAttribute('data-tooltip-json'));
                    const crosshair = el.closest('svg').querySelector('.viz-crosshair');
                    const x = el.getAttribute('data-x');
                    function activar(ev) {
                        construirTooltip(datos);
                        tooltip.style.display = 'block';
                        const px = ev.clientX ?? el.getBoundingClientRect().right;
                        const py = ev.clientY ?? el.getBoundingClientRect().top;
                        tooltip.style.left = Math.min(px + 14, window.innerWidth - 250) + 'px';
                        tooltip.style.top = Math.max(py - 10, 10) + 'px';
                        crosshair.setAttribute('x1', x);
                        crosshair.setAttribute('x2', x);
                        crosshair.style.display = 'block';
                    }
                    function desactivar() {
                        ocultarTooltip();
                        crosshair.style.display = 'none';
                    }
                    el.addEventListener('pointermove', activar);
                    el.addEventListener('pointerleave', desactivar);
                    el.addEventListener('focus', activar);
                    el.addEventListener('blur', desactivar);
                });
            </script>
        </body>
        </html>
        <?php
    }
}
?>
