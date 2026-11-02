<?php
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/Tema.php";
require_once __DIR__ . "/../models/DepositosModel.php";

class ContabilidadView {

    private static array $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private static function estilos(): void {
        ?>
        <style>
            <?php Tema::tokensBase(); ?>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: var(--bg-pagina); padding: 24px; color: var(--texto); }
            .barra {
                display: flex; justify-content: space-between; align-items: center;
                max-width: 1100px; margin: 0 auto 16px;
            }
            .barra .usuario { font-size: 13px; color: var(--texto-muted); }
            .barra a { color: var(--acento); text-decoration: none; font-size: 13px; font-weight: bold; margin-left: 16px; }
            <?php Tema::estiloBoton(); ?>
            h1 { font-size: 22px; margin-bottom: 20px; color: var(--acento); text-align: center; }
            h2 { font-size: 15px; color: var(--acento); margin: 0 0 12px; }
            .card {
                background: var(--bg-tarjeta); max-width: 1100px; margin: 0 auto 24px;
                border-radius: 6px; padding: 24px 28px; box-shadow: 0 1px 4px var(--sombra-tarjeta);
            }
            label { display: block; font-size: 13px; font-weight: bold; color: var(--texto); margin-bottom: 5px; }
            input, select {
                width: 100%; padding: 10px; font-size: 14px;
                border: 1px solid var(--borde-input); border-radius: 4px; margin-bottom: 0;
                background: var(--bg-tarjeta); color: var(--texto);
            }
            input:focus, select:focus { outline: none; border-color: var(--acento); }
            .fila { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
            .campo { flex: 1; min-width: 180px; }
            button { padding: 11px 18px; font-size: 14px; font-weight: bold;
                background: var(--acento); color: #fff; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #283593; }
            .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { border-collapse: collapse; width: 100%; font-size: 13px; }
            th, td { border: 1px solid var(--tabla-borde); padding: 8px 10px; text-align: left; }
            th { background: var(--tabla-header-bg); color: var(--tabla-header-texto); white-space: nowrap; }
            td { color: var(--texto); }
            td.num { text-align: right; font-family: 'Courier New', monospace; }
            .sin-datos { color: var(--sin-datos); font-style: italic; padding: 12px 0; }
            details { margin-bottom: 14px; }
            details summary {
                cursor: pointer; font-weight: bold; padding: 10px 14px; border-radius: 4px;
                background: var(--fila-alterna); color: var(--texto); list-style: none;
            }
            details summary::-webkit-details-marker { display: none; }
            details summary::before { content: "▸ "; }
            details[open] summary::before { content: "▾ "; }
            details .tabla-scroll { margin-top: 10px; }
            .tipo-sin-categorizar summary { background: var(--estado-falta-bg); color: var(--estado-falta-texto); }
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

    // $porTipo: [tipo => ['total'=>float, 'lineas'=>[['id','cuenta','fecha','monto','referencia','descripcion'],...]]]
    // (ver GastosModel::reportePorTipo). "Gastos" aquí es SOLO lo que ya
    // está en el estado de cuenta importado (débitos) — todavía no hay
    // registro manual de gastos en efectivo o con comprobante propio.
    public static function mostrarReporteGastos(array $porTipo, int $anio, ?int $mes, string $texto): void {
        $totalGeneral = array_sum(array_column($porTipo, 'total'));
        $lineasGeneral = array_sum(array_map(fn($t) => count($t['lineas']), $porTipo));
        $periodo = ($mes !== null ? self::$meses[$mes] . ' ' : '') . $anio;
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Contabilidad — Gastos — CETECPRO</title>
            <?php Tema::scriptInicial(); ?>
            <?php self::estilos(); ?>
        </head>
        <body>
            <?php self::barra(); ?>
            <h1>Contabilidad — Gastos</h1>

            <div class="card">
                <a href="inicio.php" style="font-size:13px;color:var(--acento);font-weight:bold;">← Volver a Inicio</a>
                <p style="font-size:12.5px;color:var(--texto-muted,var(--sin-datos));margin:12px 0 0;">
                    Agrupa los débitos ya importados del estado de cuenta (ver Conciliación) por un tipo estimado,
                    a partir de la descripción que trae el banco — no es una categorización contable exacta,
                    algunas transferencias no dicen para qué fueron y quedan en "Sin categorizar".
                </p>
                <form method="GET" action="contabilidad.php" style="margin-top:14px;">
                    <input type="hidden" name="action" value="gastos">
                    <div class="fila">
                        <div class="campo" style="max-width:140px;">
                            <label for="anio">Año</label>
                            <select id="anio" name="anio" onchange="this.form.submit()">
                                <?php for ($a = (int)date('Y'); $a >= 2020; $a--): ?>
                                    <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="campo" style="max-width:160px;">
                            <label for="mes">Mes</label>
                            <select id="mes" name="mes" onchange="this.form.submit()">
                                <option value="">-- Todos --</option>
                                <?php foreach (self::$meses as $num => $nombre): ?>
                                    <option value="<?= $num ?>" <?= $mes === $num ? 'selected' : '' ?>><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="campo">
                            <label for="texto">Buscar en referencia/descripción</label>
                            <input type="text" id="texto" name="texto" value="<?= htmlspecialchars($texto) ?>" style="margin-bottom:0;" placeholder="ej. TIGO, CHEQUE, IGSS">
                        </div>
                        <div class="campo" style="flex:0 0 auto;">
                            <button type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Resumen por tipo — <?= $periodo ?></h2>
                <?php if (empty($porTipo)): ?>
                    <p class="sin-datos">No hay débitos importados para <?= $periodo ?><?= $texto !== '' ? ' con "' . htmlspecialchars($texto) . '"' : '' ?>.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                    <table>
                        <thead><tr><th>Tipo</th><th>Líneas</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ($porTipo as $tipo => $datos): ?>
                                <tr>
                                    <td><?= htmlspecialchars($tipo) ?></td>
                                    <td class="num"><?= count($datos['lineas']) ?></td>
                                    <td class="num">Q <?= number_format($datos['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight:bold;">
                                <td>Total <?= $periodo ?></td>
                                <td class="num"><?= $lineasGeneral ?></td>
                                <td class="num">Q <?= number_format($totalGeneral, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach ($porTipo as $tipo => $datos): ?>
                <div class="card">
                    <details class="<?= $tipo === 'Sin categorizar' ? 'tipo-sin-categorizar' : '' ?>">
                        <summary><?= htmlspecialchars($tipo) ?> — Q <?= number_format($datos['total'], 2) ?> (<?= count($datos['lineas']) ?> líneas)</summary>
                        <div class="tabla-scroll">
                        <table>
                            <thead>
                                <tr><th>Fecha</th><th>Banco</th><th>Monto</th><th>Referencia</th><th>Descripción</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos['lineas'] as $l): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($l['fecha'])) ?></td>
                                        <td><?= htmlspecialchars(DepositosModel::bancoDeCuenta($l['cuenta']) ?? $l['cuenta']) ?></td>
                                        <td class="num">Q <?= number_format((float)$l['monto'], 2) ?></td>
                                        <td><?= $l['referencia'] ? htmlspecialchars($l['referencia']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                        <td><?= $l['descripcion'] ? htmlspecialchars($l['descripcion']) : '<span style="color:var(--sin-datos);">—</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
            <?php Tema::script(); ?>
        </body>
        </html>
        <?php
    }
}
?>
