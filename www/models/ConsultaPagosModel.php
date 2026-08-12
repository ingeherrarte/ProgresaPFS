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
                       nodeposito, fechadep, banco, mesquepaga, detalle, anulado, motivo_anulacion
                FROM recibospfs
                WHERE carne = ?
                ORDER BY horaregistro DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$carnet]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
