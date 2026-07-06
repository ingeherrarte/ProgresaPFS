<?php
require_once __DIR__ . "/config/Conexion.php";

$anio_actual = (int)date('Y');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : $anio_actual;
$mes  = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)date('m');

$db   = Conexion::conectar();
$sql  = "
    SELECT
        DATE(horaregistro)                  AS dia,
        COUNT(*)                            AS recibos,
        SUM(efectivo)                       AS total_efectivo,
        SUM(deposito)                       AS total_deposito,
        SUM(cheque)                         AS total_cheque,
        SUM(efectivo + deposito + cheque)   AS total_dia
    FROM recibospfs
    WHERE YEAR(horaregistro)  = :anio
      AND MONTH(horaregistro) = :mes
    GROUP BY DATE(horaregistro)
    ORDER BY dia
";
$stmt = $db->prepare($sql);
$stmt->execute([':anio' => $anio, ':mes' => $mes]);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gran_recibos  = array_sum(array_column($filas, 'recibos'));
$gran_efectivo = array_sum(array_column($filas, 'total_efectivo'));
$gran_deposito = array_sum(array_column($filas, 'total_deposito'));
$gran_cheque   = array_sum(array_column($filas, 'total_cheque'));
$gran_total    = array_sum(array_column($filas, 'total_dia'));

$meses = [
    1=>'Enero',    2=>'Febrero',   3=>'Marzo',     4=>'Abril',
    5=>'Mayo',     6=>'Junio',     7=>'Julio',      8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte Recibos PFS</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #333; }
        h1 { font-size: 22px; margin-bottom: 20px; color: #1a237e; }
        h2 { font-size: 16px; margin-bottom: 12px; color: #444; }

        .filtros {
            background: #fff;
            border-radius: 6px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
        }
        .filtros label { font-size: 14px; font-weight: bold; }
        .filtros select {
            padding: 6px 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .filtros button {
            padding: 7px 20px;
            font-size: 14px;
            background: #1a237e;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filtros button:hover { background: #283593; }

        .card {
            background: #fff;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
        }

        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px 14px; }
        th { background: #1a237e; color: #fff; text-align: center; white-space: nowrap; }
        td { text-align: right; }
        td:first-child { text-align: center; }
        tbody tr:nth-child(even) { background: #f5f7ff; }
        tbody tr:hover { background: #e8eaf6; }

        tfoot tr { background: #1a237e !important; color: #fff; font-weight: bold; }
        tfoot td { border-color: #1a237e; }

        .sin-datos { color: #888; font-style: italic; padding: 20px 0; }
    </style>
</head>
<body>

<h1>Reporte de Recibos PFS</h1>

<form method="GET" class="filtros">
    <label for="anio">Año:</label>
    <select id="anio" name="anio">
        <?php for ($y = $anio_actual; $y >= 2015; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $anio ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
    </select>

    <label for="mes">Mes:</label>
    <select id="mes" name="mes">
        <?php foreach ($meses as $num => $nombre): ?>
            <option value="<?= $num ?>" <?= $num === $mes ? 'selected' : '' ?>><?= $nombre ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Consultar</button>
</form>

<div class="card">
    <h2>Resumen por día — <?= $meses[$mes] ?> <?= $anio ?></h2>

    <?php if (empty($filas)): ?>
        <p class="sin-datos">Sin registros para el período seleccionado.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Día</th>
                <th>Recibos</th>
                <th>Efectivo</th>
                <th>Depósito</th>
                <th>Cheque</th>
                <th>Total del Día</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $f): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($f['dia'])) ?></td>
                <td><?= $f['recibos'] ?></td>
                <td>Q <?= number_format($f['total_efectivo'], 2) ?></td>
                <td>Q <?= number_format($f['total_deposito'], 2) ?></td>
                <td>Q <?= number_format($f['total_cheque'], 2) ?></td>
                <td>Q <?= number_format($f['total_dia'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td><?= $gran_recibos ?></td>
                <td>Q <?= number_format($gran_efectivo, 2) ?></td>
                <td>Q <?= number_format($gran_deposito, 2) ?></td>
                <td>Q <?= number_format($gran_cheque, 2) ?></td>
                <td>Q <?= number_format($gran_total, 2) ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

</body>
</html>
```
