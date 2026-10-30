<?php
require_once __DIR__ . "/../config/Conexion.php";

class ConsultaPagosModel {

    private static array $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function nombreMes($mes): string {
        return self::$meses[(int)$mes] ?? 'No definido';
    }

    private static function condicionesCurso(int $codcurso, ?int $plan, ?int $jornada): array {
        $condiciones = ["e.codcurso = ?"];
        $params = [$codcurso];
        if ($plan !== null) {
            $condiciones[] = "e.plan = ?";
            $params[] = $plan;
        }
        if ($jornada !== null) {
            $condiciones[] = "e.jornada = ?";
            $params[] = $jornada;
        }
        return [$condiciones, $params];
    }

    // Estado de pagos de TODOS los estudiantes de un diplomado (y
    // opcionalmente plan/jornada), por último pago registrado — mismo
    // criterio que GruposModel::candidatos (horaregistro de recibos no
    // anulados con monto > 0), generalizado a todo el diplomado en vez de
    // excluir a quien ya es miembro de un grupo. Clasifica en 5 franjas:
    // al_dia (pagó en el último mes), debe_1_mes, debe_2_3_meses,
    // mora_mayor (4+ meses) y sin_pagos (nunca ha pagado nada).
    public static function estadoPagosPorDiplomado(PDO $db, int $codcurso, ?int $plan, ?int $jornada): array {
        [$condiciones, $params] = self::condicionesCurso($codcurso, $plan, $jornada);

        // (carne + 0): algunos recibos tienen el carné con espacios/tabs
        // pegados por error de digitación (ej. " 2026191", "\t2026191").
        // TRIM() no basta (no quita tabs); agrupar por el mismo valor
        // numérico que usa la comparación implícita del JOIN (idestudiante
        // es INT) colapsa todas las variantes en un solo grupo — sin esto,
        // cada variante arma su propio grupo y el mismo estudiante sale
        // duplicado al cruzar.
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada, up.ultimo_pago,
                       TIMESTAMPDIFF(MONTH, up.ultimo_pago, NOW()) AS meses_desde_pago
                FROM estudiantespfs e
                LEFT JOIN (
                    SELECT (carne + 0) AS carne, MAX(horaregistro) AS ultimo_pago
                    FROM recibospfs
                    WHERE anulado = 0 AND (efectivo + deposito + cheque) > 0
                    GROUP BY (carne + 0)
                ) up ON up.carne = e.idestudiante
                WHERE " . implode(' AND ', $condiciones) . "
                ORDER BY up.ultimo_pago IS NULL DESC, up.ultimo_pago ASC, e.nombre, e.apellidos";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $resultado = ['al_dia' => [], 'debe_1_mes' => [], 'debe_2_3_meses' => [], 'mora_mayor' => [], 'sin_pagos' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            if ($f['ultimo_pago'] === null) {
                $resultado['sin_pagos'][] = $f;
            } elseif ((int)$f['meses_desde_pago'] <= 1) {
                $resultado['al_dia'][] = $f;
            } elseif ((int)$f['meses_desde_pago'] === 2) {
                $resultado['debe_1_mes'][] = $f;
            } elseif ((int)$f['meses_desde_pago'] <= 4) {
                $resultado['debe_2_3_meses'][] = $f;
            } else {
                $resultado['mora_mayor'][] = $f;
            }
        }
        return $resultado;
    }

    // Reporte aproximado por palabra clave sobre recibospfs.detalle (ej.
    // "GRADUA", "LIBRO") — no hay campo estructurado para esto, se infiere
    // del texto libre del recibo (ver recibospfs.detalle/otro). El monto
    // mostrado es la suma de `otro` de TODOS los recibos que coinciden, así
    // que si un recibo mezcla el concepto con otra cosa en la misma línea
    // ("MES DE DICIEMBRE Y ACTO DE GRADUACION"), el monto puede incluir más
    // de lo que es solo ese concepto — por eso se muestra también el texto
    // del detalle, para que quien lo revisa juzgue si es abono o pago
    // completo, en vez de que el sistema lo decida con un umbral inventado.
    public static function conceptoPorDiplomado(PDO $db, int $codcurso, ?int $plan, ?int $jornada, string $patron): array {
        [$condiciones, $params] = self::condicionesCurso($codcurso, $plan, $jornada);
        $comodin = '%' . $patron . '%';

        $sqlCon = "SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada,
                          SUM(r.otro) AS monto_detectado, MAX(r.horaregistro) AS ultima_fecha,
                          GROUP_CONCAT(DISTINCT r.detalle SEPARATOR ' | ') AS detalles
                   FROM estudiantespfs e
                   INNER JOIN recibospfs r ON r.carne = e.idestudiante AND r.anulado = 0 AND r.detalle LIKE ?
                   WHERE " . implode(' AND ', $condiciones) . "
                   GROUP BY e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada
                   ORDER BY e.nombre, e.apellidos";
        $stmtCon = $db->prepare($sqlCon);
        $stmtCon->execute(array_merge([$comodin], $params));
        $conPago = $stmtCon->fetchAll(PDO::FETCH_ASSOC);

        $idsConPago = array_map(fn($f) => (int)$f['idestudiante'], $conPago);
        $exclusion = $idsConPago ? " AND e.idestudiante NOT IN (" . implode(',', array_fill(0, count($idsConPago), '?')) . ")" : "";

        $sqlSin = "SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada
                   FROM estudiantespfs e
                   WHERE " . implode(' AND ', $condiciones) . $exclusion . "
                   ORDER BY e.nombre, e.apellidos";
        $stmtSin = $db->prepare($sqlSin);
        $stmtSin->execute(array_merge($params, $idsConPago));
        $sinPago = $stmtSin->fetchAll(PDO::FETCH_ASSOC);

        return ['conPago' => $conPago, 'sinPago' => $sinPago];
    }

    // Estudiantes con al menos un recibo NO anulado, con el total pagado
    // agregado. Los recibos anulados nunca cuentan aquí ni en el total
    // (a diferencia del reporte legacy, que sumaba todo sin filtrar).
    public static function buscar(PDO $db, string $termino, int $pagina, int $porPagina): array {
        $comodin = '%' . $termino . '%';
        $condicion = "CONCAT(e.nombre, ' ', e.apellidos) LIKE ? OR CAST(e.idestudiante AS CHAR) LIKE ?";

        $stmtTotal = $db->prepare(
            "SELECT COUNT(*) FROM (
                SELECT e.idestudiante
                FROM estudiantespfs e
                INNER JOIN recibospfs r ON r.carne = e.idestudiante AND r.anulado = 0
                WHERE $condicion
                GROUP BY e.idestudiante
            ) t"
        );
        $stmtTotal->execute([$comodin, $comodin]);
        $total = (int)$stmtTotal->fetchColumn();

        $offset = max(0, ($pagina - 1) * $porPagina);
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.telefonomovil, e.activo,
                       d.nombre AS nombrecurso, e.plan, e.jornada,
                       COUNT(r.numero) AS total_recibos,
                       SUM(r.efectivo + r.deposito + r.cheque) AS total_pagado
                FROM estudiantespfs e
                INNER JOIN recibospfs r ON r.carne = e.idestudiante AND r.anulado = 0
                LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
                WHERE $condicion
                GROUP BY e.idestudiante, e.nombre, e.apellidos, e.telefonomovil, e.activo, d.nombre, e.plan, e.jornada
                ORDER BY e.apellidos ASC, e.nombre ASC
                LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $comodin, PDO::PARAM_STR);
        $stmt->bindValue(2, $comodin, PDO::PARAM_STR);
        $stmt->bindValue(3, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['total' => $total, 'filas' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public static function estudiante(PDO $db, string $carnet): ?array {
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.telefonomovil, e.email, e.activo,
                       d.nombre AS nombrecurso, e.plan, e.jornada
                FROM estudiantespfs e
                LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
                WHERE e.idestudiante = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$carnet]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Trae TODOS los recibos, incluidos los anulados (se muestran marcados
    // en la vista para que quede trazabilidad), pero el llamador debe
    // excluir los anulados al sumar totales.
    public static function historialPagos(PDO $db, string $carnet): array {
        $sql = "SELECT numero, fechadelpago, horaregistro, efectivo, deposito, cheque,
                       nodeposito, fechadep, banco, mesquepaga, detalle, anulado, motivo_anulacion,
                       foto_deposito
                FROM recibospfs
                WHERE carne = ?
                ORDER BY horaregistro DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$carnet]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
