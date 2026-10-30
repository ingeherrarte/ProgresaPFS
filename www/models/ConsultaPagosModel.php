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

    // Reporte unificado: una fila por estudiante con estado de pagos +
    // Graduación + Libro juntos, para filtrar todo en el cliente (sin
    // recargar) en vez de tener que elegir un diplomado primero.
    //
    // $mesesRecientes acota el universo a quien pagó en esos últimos
    // meses — ES TEMPORAL, solo mientras Grupos no existe para la mayoría
    // de estudiantes (ver nota en ConsultaPagosController::reportes()).
    // Cuando Grupos esté poblado, este método debe recibir una lista de
    // idestudiante (miembros de grupos) en vez de este filtro de
    // recencia de pago.
    //
    // Graduación/Libro son aproximados por palabra clave sobre
    // recibospfs.detalle (no existe campo estructurado) — el monto es la
    // suma de `otro` de los recibos que coinciden, puede incluir más de
    // lo que es solo ese concepto si el recibo mezcla varias cosas en la
    // misma línea ("MES DE DICIEMBRE Y ACTO DE GRADUACION"). Por eso se
    // trae también el texto del detalle, para que quien lo revisa juzgue
    // abono vs. pago completo en vez de que el sistema invente un umbral.
    //
    // (carne + 0) en vez de carne a secas: algunos recibos tienen el
    // carné con espacios/tabs pegados (error de digitación); agrupar por
    // el mismo valor numérico que usa la comparación implícita del JOIN
    // (idestudiante es INT) colapsa esas variantes en vez de duplicar al
    // estudiante.
    public static function reporteUnificado(PDO $db, int $mesesRecientes, string $patronGraduacion, string $patronLibro): array {
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada, e.codcurso,
                       d.nombre AS nombrecurso,
                       up.ultimo_pago, TIMESTAMPDIFF(MONTH, up.ultimo_pago, NOW()) AS meses_desde_pago,
                       g.monto_graduacion, g.detalle_graduacion,
                       l.monto_libro, l.detalle_libro
                FROM estudiantespfs e
                LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
                INNER JOIN (
                    SELECT (carne + 0) AS carne, MAX(horaregistro) AS ultimo_pago
                    FROM recibospfs
                    WHERE anulado = 0 AND (efectivo + deposito + cheque) > 0
                    GROUP BY (carne + 0)
                    HAVING ultimo_pago >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                ) up ON up.carne = e.idestudiante
                LEFT JOIN (
                    SELECT (carne + 0) AS carne, SUM(otro) AS monto_graduacion,
                           GROUP_CONCAT(DISTINCT detalle SEPARATOR ' | ') AS detalle_graduacion
                    FROM recibospfs WHERE anulado = 0 AND detalle LIKE ? GROUP BY (carne + 0)
                ) g ON g.carne = e.idestudiante
                LEFT JOIN (
                    SELECT (carne + 0) AS carne, SUM(otro) AS monto_libro,
                           GROUP_CONCAT(DISTINCT detalle SEPARATOR ' | ') AS detalle_libro
                    FROM recibospfs WHERE anulado = 0 AND detalle LIKE ? GROUP BY (carne + 0)
                ) l ON l.carne = e.idestudiante
                ORDER BY d.nombre, e.plan, e.jornada, e.nombre, e.apellidos";
        $stmt = $db->prepare($sql);
        $stmt->execute([$mesesRecientes, '%' . $patronGraduacion . '%', '%' . $patronLibro . '%']);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as &$f) {
            $meses = (int)$f['meses_desde_pago'];
            $f['estado_pago'] = $meses <= 1 ? 'al_dia' : ($meses === 2 ? 'debe_1_mes' : ($meses <= 4 ? 'debe_2_3_meses' : 'mora_mayor'));
        }
        unset($f);
        return $filas;
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
