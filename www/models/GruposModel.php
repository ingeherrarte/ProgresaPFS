<?php
require_once __DIR__ . "/../config/Conexion.php";

// Un "grupo" es la lista real de estudiantes de una combinación
// Diplomado (codcurso) + Plan/día + Jornada (ver database/grupos.sql).
// No tiene relación con estudiantespfs.activo a propósito.
class GruposModel {

    public static function obtener(PDO $db, int $codcurso, int $plan, int $jornada): ?array {
        $stmt = $db->prepare("SELECT * FROM grupos WHERE codcurso = ? AND plan = ? AND jornada = ?");
        $stmt->execute([$codcurso, $plan, $jornada]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function obtenerPorId(PDO $db, int $id): ?array {
        $sql = "SELECT g.*, d.nombre AS nombrecurso FROM grupos g
                LEFT JOIN `diplomado-curso` d ON d.id = g.codcurso WHERE g.id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function crear(PDO $db, int $codcurso, int $plan, int $jornada, string $usuario): int {
        $stmt = $db->prepare("INSERT INTO grupos (codcurso, plan, jornada, creado_por) VALUES (?, ?, ?, ?)");
        $stmt->execute([$codcurso, $plan, $jornada, $usuario]);
        return (int)$db->lastInsertId();
    }

    // Todos los grupos existentes, con el nombre del diplomado y cuántos
    // miembros activos tiene cada uno, para elegir uno en Asistencia/Notas.
    public static function listar(PDO $db): array {
        $sql = "SELECT g.id, g.codcurso, g.plan, g.jornada, d.nombre AS nombrecurso,
                       (SELECT COUNT(*) FROM grupos_estudiantes ge WHERE ge.grupo_id = g.id AND ge.activo = 1) AS total_miembros
                FROM grupos g
                LEFT JOIN `diplomado-curso` d ON d.id = g.codcurso
                ORDER BY d.nombre, g.plan, g.jornada";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function miembros(PDO $db, int $grupoId): array {
        $sql = "SELECT ge.idestudiante, e.nombre, e.apellidos
                FROM grupos_estudiantes ge
                JOIN estudiantespfs e ON e.idestudiante = ge.idestudiante
                WHERE ge.grupo_id = ? AND ge.activo = 1
                ORDER BY e.nombre, e.apellidos";
        $stmt = $db->prepare($sql);
        $stmt->execute([$grupoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 'agregado', 'reactivado' (estaba quitado del grupo) o 'ya_existe'
    // (ya es miembro activo) — nunca inserta una segunda fila para el
    // mismo estudiante en el mismo grupo (ver UNIQUE KEY uq_miembro).
    public static function agregarMiembro(PDO $db, int $grupoId, int $idestudiante, string $usuario): string {
        $stmt = $db->prepare("SELECT id, activo FROM grupos_estudiantes WHERE grupo_id = ? AND idestudiante = ?");
        $stmt->execute([$grupoId, $idestudiante]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            if ((int)$existente['activo'] === 1) {
                return 'ya_existe';
            }
            $upd = $db->prepare(
                "UPDATE grupos_estudiantes SET activo = 1, agregado_por = ?, agregado_en = NOW(), quitado_por = NULL, quitado_en = NULL
                 WHERE id = ?"
            );
            $upd->execute([$usuario, $existente['id']]);
            return 'reactivado';
        }

        $ins = $db->prepare("INSERT INTO grupos_estudiantes (grupo_id, idestudiante, agregado_por) VALUES (?, ?, ?)");
        $ins->execute([$grupoId, $idestudiante, $usuario]);
        return 'agregado';
    }

    // Soft-remove: nunca borra la fila, para no perder el historial de
    // asistencia/notas que ese estudiante ya tenga con este grupo.
    public static function quitarMiembro(PDO $db, int $grupoId, int $idestudiante, string $usuario): bool {
        $stmt = $db->prepare(
            "UPDATE grupos_estudiantes SET activo = 0, quitado_por = ?, quitado_en = NOW()
             WHERE grupo_id = ? AND idestudiante = ? AND activo = 1"
        );
        $stmt->execute([$usuario, $grupoId, $idestudiante]);
        return $stmt->rowCount() > 0;
    }
}
?>
