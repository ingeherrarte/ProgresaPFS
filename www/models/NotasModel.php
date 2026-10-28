<?php
require_once __DIR__ . "/../config/Conexion.php";

// Una evaluación por mes de calendario por estudiante (no por parcial/
// zona) — decisión explícita del usuario, no el default que se iba a usar.
class NotasModel {

    private static array $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public static function meses(): array {
        return self::$meses;
    }

    public static function nombreMes(int $mes): string {
        return self::$meses[$mes] ?? (string)$mes;
    }

    // Trae a todos los miembros activos del grupo con su nota de ese
    // año/mes (null si todavía no se ha calificado ese período).
    public static function paraPeriodo(PDO $db, int $grupoId, int $anio, int $mes): array {
        $sql = "SELECT ge.idestudiante, e.nombre, e.apellidos, n.nota
                FROM grupos_estudiantes ge
                JOIN estudiantespfs e ON e.idestudiante = ge.idestudiante
                LEFT JOIN notas n ON n.grupo_id = ge.grupo_id AND n.idestudiante = ge.idestudiante AND n.anio = ? AND n.mes = ?
                WHERE ge.grupo_id = ? AND ge.activo = 1
                ORDER BY e.nombre, e.apellidos";
        $stmt = $db->prepare($sql);
        $stmt->execute([$anio, $mes, $grupoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // $notas: [idestudiante => valor]. Vacío = no se guarda nada para ese
    // estudiante (no es lo mismo que calificar con 0). Volver a guardar el
    // mismo período actualiza en vez de duplicar (UNIQUE uq_nota).
    public static function guardar(PDO $db, int $grupoId, int $anio, int $mes, array $notas, string $usuario): array {
        $stmt = $db->prepare(
            "INSERT INTO notas (grupo_id, idestudiante, anio, mes, nota, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nota = VALUES(nota), registrado_por = VALUES(registrado_por), registrado_en = NOW()"
        );
        $guardadas = 0;
        $errores = [];
        foreach ($notas as $idestudiante => $valor) {
            $valor = trim((string)$valor);
            if ($valor === '') {
                continue;
            }
            if (!is_numeric($valor) || (float)$valor < 0 || (float)$valor > 100) {
                $errores[] = "Nota inválida para el carné $idestudiante (debe ser de 0 a 100).";
                continue;
            }
            $stmt->execute([$grupoId, (int)$idestudiante, $anio, $mes, (float)$valor, $usuario]);
            $guardadas++;
        }
        return ['guardadas' => $guardadas, 'errores' => $errores];
    }

    // Qué períodos ya tienen notas en este grupo, con cantidad calificada
    // y promedio — para ver de un vistazo el historial.
    public static function historialPeriodos(PDO $db, int $grupoId, int $limite = 24): array {
        $sql = "SELECT anio, mes, COUNT(*) AS cantidad, AVG(nota) AS promedio
                FROM notas WHERE grupo_id = ?
                GROUP BY anio, mes ORDER BY anio DESC, mes DESC LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $grupoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
