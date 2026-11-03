<?php
require_once __DIR__ . "/../config/Conexion.php";

class GastosModel {

    // Clasificación por palabra clave sobre la descripción real del banco —
    // es un ESTIMADO para el reporte, no una categorización contable
    // exacta (igual limitación que RecibosPfsModel::ingresosPorConceptosEspeciales:
    // el texto del banco es libre, no estructurado). El orden importa: una
    // descripción se queda con la PRIMERA categoría cuyo patrón encuentre,
    // así que las más específicas van antes que "Transferencias internas"
    // (que de otro modo se comería casi todo, porque la mayoría de líneas
    // vienen como "CTA. DESTINO:...").
    private const CATEGORIAS = [
        'Servicios' => ['TIGO', 'COMCEL', 'BANCA SAT', 'SERVICIO', 'MANTENIMIENTO', 'HONORARIOS', 'ALQUILER', 'RENTA'],
        'Compras' => ['LIBRO', 'BATA', 'TERRENO', 'SILLA', 'PINTURA', 'ANTEOJO', 'LENTE', 'MICROONDAS', 'CAFETERA', 'CUBIERTA', 'PLAYERA', 'HERRAMIENTA', 'LUBRICANTE', 'VEHICULO'],
        'Planilla / IGSS' => ['IGSS', 'MINTRABAJO'],
        'Cheques sin detalle' => ['CHEQUE PAGADO', 'OPERACION CHEQUE PROPIO'],
        'Transferencias internas / ahorro' => ['CTA. DESTINO', 'AHORRO', 'CETECPRO DIRECC'],
    ];

    private static function clasificar(?string $descripcion): string {
        $texto = mb_strtoupper($descripcion ?? '');
        foreach (self::CATEGORIAS as $categoria => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return $categoria;
                }
            }
        }
        return 'Sin categorizar';
    }

    // Débitos del año (opcionalmente un mes, y/o un texto libre sobre
    // referencia/descripción) agrupados por tipo estimado — base del
    // reporte de "Gastos" en Contabilidad. excluido=0: una línea marcada
    // como error del banco (ver ConciliacionModel::excluir) tampoco debe
    // contar aquí.
    public static function reportePorTipo(PDO $db, int $anio, ?int $mes = null, string $texto = ''): array {
        $condiciones = ["tipo = 'debito'", "YEAR(fecha) = ?", "excluido = 0"];
        $params = [$anio];
        if ($mes !== null) {
            $condiciones[] = "MONTH(fecha) = ?";
            $params[] = $mes;
        }
        if ($texto !== '') {
            $condiciones[] = "(referencia LIKE ? OR descripcion LIKE ?)";
            $params[] = '%' . $texto . '%';
            $params[] = '%' . $texto . '%';
        }
        $stmt = $db->prepare(
            "SELECT id, cuenta, fecha, monto, referencia, descripcion
             FROM estados_cuenta
             WHERE " . implode(' AND ', $condiciones) . "
             ORDER BY fecha"
        );
        $stmt->execute($params);

        $porTipo = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linea) {
            $tipo = self::clasificar($linea['descripcion']);
            $porTipo[$tipo]['total'] = ($porTipo[$tipo]['total'] ?? 0.0) + (float)$linea['monto'];
            $porTipo[$tipo]['lineas'][] = $linea;
        }

        // "Sin categorizar" siempre al final, aunque tenga un total grande
        // — es a propósito visible, no se esconde (mismo criterio que
        // "Sin curso asignado" en ingresosPorDiplomado), pero no debe
        // competir con las categorías reales por el primer lugar visual.
        uasort($porTipo, fn($a, $b) => $b['total'] <=> $a['total']);
        if (isset($porTipo['Sin categorizar'])) {
            $sinCategorizar = $porTipo['Sin categorizar'];
            unset($porTipo['Sin categorizar']);
            $porTipo['Sin categorizar'] = $sinCategorizar;
        }

        return $porTipo;
    }
}
?>
