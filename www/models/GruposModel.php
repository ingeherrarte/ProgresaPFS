<?php
require_once __DIR__ . "/../config/Conexion.php";

// Un "grupo" es la lista real de estudiantes de una combinación
// Diplomado (codcurso) + Plan/día + Jornada (ver database/grupos.sql).
// No tiene relación con estudiantespfs.activo a propósito.
class GruposModel {

    // Para el KPI "Estudiantes activos" de Inicio — reemplaza a
    // estudiantespfs.activo, que no es confiable (ver nota arriba): esto
    // cuenta miembros reales de algún grupo, no un campo que nadie
    // mantiene al día.
    public static function totalMiembrosActivos(PDO $db): int {
        return (int)$db->query("SELECT COUNT(*) FROM grupos_estudiantes WHERE activo = 1")->fetchColumn();
    }

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

    // Candidatos para agregar en bloque a un grupo, en vez de uno por uno
    // a mano: estudiantespfs.activo no sirve de filtro (no es confiable,
    // ver grupos.sql), así que aquí "activo" se decide por pago reciente
    // — con el último pago visible para que quien agrega lo juzgue, no
    // escondido. $mesesTolerancia=2 significa "pagó el último mes o debe
    // como máximo 2" (decisión explícita del usuario).
    //
    // Tres bloques, cada uno excluyendo a quien ya es miembro activo del
    // grupo: 1) misma combinación exacta codcurso+plan+jornada (los que
    // "deberían" estar aquí), 2) mismo diplomado pero otro plan/jornada,
    // 3) otro diplomado. El bloque 3 se limita a $limiteOtros filas: puede
    // ser la mayoría del sistema, para encontrar a alguien específico ahí
    // se usa el buscador de texto libre, no este listado.
    public static function candidatos(PDO $db, int $grupoId, int $codcurso, int $plan, int $jornada, int $mesesTolerancia = 2, int $limiteOtros = 300): array {
        $base = "
            SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada, d.nombre AS nombrecurso,
                   up.ultimo_pago
            FROM estudiantespfs e
            LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
            LEFT JOIN (
                -- (carne + 0): un carné con espacio/tab pegado (error de
                -- digitación) arma su propio grupo aparte y duplica al
                -- estudiante al cruzar contra estudiantespfs (idestudiante
                -- es INT). TRIM() no basta porque no quita tabs — agrupar
                -- por el mismo valor numérico que usa la comparación
                -- implícita del JOIN sí colapsa todas las variantes.
                SELECT (carne + 0) AS carne, MAX(horaregistro) AS ultimo_pago FROM recibospfs WHERE anulado = 0 GROUP BY (carne + 0)
            ) up ON up.carne = e.idestudiante
            WHERE up.ultimo_pago >= DATE_SUB(NOW(), INTERVAL ? MONTH)
              AND e.idestudiante NOT IN (SELECT idestudiante FROM grupos_estudiantes WHERE grupo_id = ? AND activo = 1)
        ";

        $stmtCombo = $db->prepare($base . " AND e.codcurso = ? AND e.plan = ? AND e.jornada = ? ORDER BY e.nombre, e.apellidos");
        $stmtCombo->execute([$mesesTolerancia, $grupoId, $codcurso, $plan, $jornada]);

        $stmtDiplomado = $db->prepare($base . " AND e.codcurso = ? AND NOT (e.plan = ? AND e.jornada = ?) ORDER BY e.nombre, e.apellidos");
        $stmtDiplomado->execute([$mesesTolerancia, $grupoId, $codcurso, $plan, $jornada]);

        $stmtOtros = $db->prepare($base . " AND e.codcurso != ? ORDER BY up.ultimo_pago DESC LIMIT ?");
        $stmtOtros->bindValue(1, $mesesTolerancia, PDO::PARAM_INT);
        $stmtOtros->bindValue(2, $grupoId, PDO::PARAM_INT);
        $stmtOtros->bindValue(3, $codcurso, PDO::PARAM_INT);
        $stmtOtros->bindValue(4, $limiteOtros, PDO::PARAM_INT);
        $stmtOtros->execute();

        return [
            'mismoGrupo' => $stmtCombo->fetchAll(PDO::FETCH_ASSOC),
            'mismoDiplomado' => $stmtDiplomado->fetchAll(PDO::FETCH_ASSOC),
            'otrosDiplomados' => $stmtOtros->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
?>
