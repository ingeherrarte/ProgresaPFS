<?php
require_once __DIR__ . "/../config/Conexion.php";

class AsistenciaModel {

    // Trae a todos los miembros activos del grupo con su estado para esa
    // fecha (null si todavía no se ha pasado lista ese día).
    public static function paraFecha(PDO $db, int $grupoId, string $fecha): array {
        $sql = "SELECT ge.idestudiante, e.nombre, e.apellidos, a.estado
                FROM grupos_estudiantes ge
                JOIN estudiantespfs e ON e.idestudiante = ge.idestudiante
                LEFT JOIN asistencias a ON a.grupo_id = ge.grupo_id AND a.idestudiante = ge.idestudiante AND a.fecha = ?
                WHERE ge.grupo_id = ? AND ge.activo = 1
                ORDER BY e.nombre, e.apellidos";
        $stmt = $db->prepare($sql);
        $stmt->execute([$fecha, $grupoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // $estados: [idestudiante => 'presente'|'ausente']. Volver a guardar
    // la misma fecha actualiza en vez de duplicar (UNIQUE uq_asistencia).
    public static function guardar(PDO $db, int $grupoId, string $fecha, array $estados, string $usuario): int {
        $stmt = $db->prepare(
            "INSERT INTO asistencias (grupo_id, idestudiante, fecha, estado, registrado_por)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), registrado_por = VALUES(registrado_por), registrado_en = NOW()"
        );
        $n = 0;
        foreach ($estados as $idestudiante => $estado) {
            if (!in_array($estado, ['presente', 'ausente'], true)) {
                continue;
            }
            $stmt->execute([$grupoId, (int)$idestudiante, $fecha, $estado, $usuario]);
            $n++;
        }
        return $n;
    }

    // Para las tarjetas de Inicio: presentes/ausentes de TODOS los grupos
    // juntos, no de uno en particular. "Semana" es una ventana móvil de 7
    // días (incluye hoy), no lunes-domingo — así el número tiene sentido
    // se consulte el día que se consulte, no solo cerca del lunes.
    public static function resumenHoy(PDO $db): array {
        $fila = $db->query(
            "SELECT SUM(estado = 'presente') AS presentes, SUM(estado = 'ausente') AS ausentes
             FROM asistencias WHERE fecha = CURDATE()"
        )->fetch(PDO::FETCH_ASSOC);
        return ['presentes' => (int)($fila['presentes'] ?? 0), 'ausentes' => (int)($fila['ausentes'] ?? 0)];
    }

    public static function resumenSemana(PDO $db): array {
        $fila = $db->query(
            "SELECT SUM(estado = 'presente') AS presentes, SUM(estado = 'ausente') AS ausentes
             FROM asistencias WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)"
        )->fetch(PDO::FETCH_ASSOC);
        return ['presentes' => (int)($fila['presentes'] ?? 0), 'ausentes' => (int)($fila['ausentes'] ?? 0)];
    }

    // Qué fechas ya tienen asistencia tomada en este grupo, y cuántos
    // presentes/ausentes cada una — para ver de un vistazo el historial
    // sin tener que adivinar qué día revisar.
    public static function historialFechas(PDO $db, int $grupoId, int $limite = 30): array {
        $sql = "SELECT fecha, SUM(estado = 'presente') AS presentes, SUM(estado = 'ausente') AS ausentes
                FROM asistencias WHERE grupo_id = ?
                GROUP BY fecha ORDER BY fecha DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $grupoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
