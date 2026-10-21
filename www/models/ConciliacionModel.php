<?php
require_once __DIR__ . "/../config/Conexion.php";

class ConciliacionModel {

    // Tolerancia de fecha para cruzar un recibo contra el estado de cuenta:
    // una transferencia ACH puede acreditarse 1-2 días DESPUÉS de la fecha
    // de la boleta (fechadep) que digitó el cajero. Sin esta tolerancia,
    // ese desfase deja el recibo en "Falta importar" para siempre aunque
    // la línea correcta ya esté importada, porque las fechas nunca empatan
    // exacto. Es solo para BUSCAR líneas candidatas — nunca se reinterpreta
    // la fecha real de la boleta.
    //
    // Solo hacia adelante a propósito: el banco no puede acreditar ANTES de
    // que la transferencia exista, así que una línea con fecha anterior a
    // fechadep nunca es una candidata real, por más que el monto coincida
    // (sería otra transferencia distinta, una coincidencia de monto). Una
    // ventana simétrica (±N días) ofrecía candidatas imposibles hacia atrás
    // y añadía ambigüedad que no existe de verdad.
    private const TOLERANCIA_DIAS_ACH = 2;

    // Máximo de líneas candidatas que se muestran/ofrecen por recibo. Antes
    // esto alimentaba un botón de "Verificar" en bloque sobre un GRUPO de
    // recibos con fecha+monto+cuenta idénticos, eligiendo en silencio cuál
    // línea le tocaba a cuál recibo cuando había más candidatas que
    // recibos (confuso: no quedaba claro cuál correspondía a cuál, y el
    // admin no podía elegir). Ahora cada recibo se revisa y verifica por
    // separado, con su propio selector de línea cuando hay más de una
    // candidata — nunca se asigna en silencio.
    private const MAX_CANDIDATOS_MOSTRADOS = 20;

    // Recibos pendientes de verificar, UNO POR FILA (no agrupados por
    // fecha+monto). $desde/$hasta acotan el período que se está conciliando
    // (normalmente el que cubre el estado de cuenta subido) — la migración
    // marcó 'pendiente' por default a TODO el histórico, y no tiene sentido
    // revisar años anteriores contra el estado de cuenta actual aquí (para
    // eso está la búsqueda manual, sin límite de fecha).
    public static function pendientesIndividuales(PDO $db, string $desde, string $hasta): array {
        // fechadep <= CURDATE() es un invariante del dato (no al revés de lo
        // que pida $hasta): un depósito no puede tener fecha futura, y hay
        // filas legacy con typos de digitación (años tipo 2052, 2041) que
        // sin este filtro contaminan la lista.
        $stmt = $db->prepare(
            "SELECT numero, carne, fechadep AS fecha, deposito AS monto, cuenta_destino
             FROM recibospfs
             WHERE deposito > 0 AND estado_transferencia = 'pendiente' AND anulado = 0
                   AND fechadep >= ? AND fechadep <= ? AND fechadep <= CURDATE()
             ORDER BY fechadep DESC, numero"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista las líneas candidatas (id, banco, fecha, referencia) dentro de
    // la tolerancia ACH para UN recibo puntual — el admin elige cuál usar
    // cuando hay más de una (nunca se asigna automáticamente si hay
    // ambigüedad). Sin cuenta_destino conocida (histórico), cualquier
    // cuenta es candidata, y cada fila ya trae su propio banco para que se
    // distinga en el selector.
    private static function listarCandidatos(PDO $db, string $fecha, $monto, ?string $cuenta): array {
        // Solo fecha >= $fecha (nunca antes) hasta +TOLERANCIA_DIAS_ACH.
        $condiciones = ["conciliado = 0", "monto = ?", "fecha BETWEEN ? AND DATE_ADD(?, INTERVAL ? DAY)"];
        $params = [$monto, $fecha, $fecha, self::TOLERANCIA_DIAS_ACH];
        if ($cuenta !== null) {
            $condiciones[] = "cuenta = ?";
            $params[] = $cuenta;
        }
        $params[] = $fecha; // para el ORDER BY DATEDIFF(...) (ya no hace falta ABS: fecha siempre >= $fecha)
        $stmt = $db->prepare(
            "SELECT id, cuenta, fecha, monto, referencia, descripcion FROM estados_cuenta
             WHERE " . implode(' AND ', $condiciones) . "
             ORDER BY DATEDIFF(fecha, ?), id
             LIMIT " . self::MAX_CANDIDATOS_MOSTRADOS
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Combina ambos: cada recibo pendiente trae su propia lista de líneas
    // candidatas, para que la vista arme un selector por recibo en vez de
    // un conteo o un grupo. Es la función que alimenta la pantalla
    // principal de Conciliación.
    public static function pendientesConCandidatos(PDO $db, string $desde, string $hasta): array {
        $pendientes = self::pendientesIndividuales($db, $desde, $hasta);
        foreach ($pendientes as &$r) {
            $r['candidatos'] = self::listarCandidatos($db, $r['fecha'], $r['monto'], $r['cuenta_destino']);
        }
        unset($r);

        return $pendientes;
    }

    // Líneas del estado de cuenta ya importadas dentro del período, con el
    // recibo contra el que quedó marcada cada una. Combina los dos
    // mecanismos de vínculo: el automático (recibo_numero escalar, 1
    // recibo) y el manual (conciliacion_vinculos, 1 o varios recibos — ej.
    // una empresa que paga por 2 estudiantes con la misma transferencia).
    // recibos_vinculados trae "numero:monto" por cada recibo vinculado,
    // cualquiera que sea el mecanismo, para que la vista no tenga que saber
    // cuál de los dos se usó.
    public static function lineasEstadoCuenta(PDO $db, string $desde, string $hasta): array {
        $stmt = $db->prepare(
            "SELECT ec.id, ec.cuenta, ec.fecha, ec.monto, ec.referencia, ec.conciliado,
                    ec.recibo_numero,
                    r.carne AS carne_automatico,
                    (SELECT GROUP_CONCAT(cv.recibo_numero, ':', cv.monto_asignado SEPARATOR ',')
                       FROM conciliacion_vinculos cv WHERE cv.estado_cuenta_id = ec.id) AS vinculos_manuales
             FROM estados_cuenta ec
             LEFT JOIN recibospfs r ON r.numero = ec.recibo_numero
             WHERE ec.fecha >= ? AND ec.fecha <= ?
             ORDER BY ec.cuenta, ec.fecha, ec.id"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Búsqueda libre de recibos pendientes (para vincular a mano cuando el
    // cruce automático no aplica: un depósito de un período muy anterior,
    // o una transferencia que cubre varios recibos a la vez). A diferencia
    // de pendientesIndividuales(), NO acota por rango de fechas — es justo
    // lo que permite encontrar un recibo "fuera del período" que la lista
    // normal no muestra.
    public static function buscarRecibosPendientes(PDO $db, string $carne, ?float $monto): array {
        $condiciones = ["deposito > 0", "estado_transferencia = 'pendiente'", "anulado = 0"];
        $params = [];
        if ($carne !== '') {
            $condiciones[] = "carne LIKE ?";
            $params[] = '%' . $carne . '%';
        }
        if ($monto !== null) {
            $condiciones[] = "deposito = ?";
            $params[] = $monto;
        }
        $stmt = $db->prepare(
            "SELECT numero, carne, fechadep, deposito AS monto, cuenta_destino
             FROM recibospfs
             WHERE " . implode(' AND ', $condiciones) . "
             ORDER BY fechadep DESC
             LIMIT 50"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Búsqueda libre de líneas del estado de cuenta con saldo disponible
    // (monto menos lo ya asignado vía vínculo manual) — excluye las que ya
    // tiene recibo_numero fijado por el flujo automático, que ya están
    // completamente explicadas por ese mecanismo. Sin límite de fecha a
    // propósito, por la misma razón que buscarRecibosPendientes().
    public static function buscarLineasDisponibles(PDO $db, ?string $cuenta, ?float $monto): array {
        $condiciones = ["ec.recibo_numero IS NULL"];
        $params = [];
        if ($cuenta !== null) {
            $condiciones[] = "ec.cuenta = ?";
            $params[] = $cuenta;
        }
        if ($monto !== null) {
            $condiciones[] = "ec.monto = ?";
            $params[] = $monto;
        }
        $stmt = $db->prepare(
            "SELECT ec.id, ec.cuenta, ec.fecha, ec.monto, ec.referencia,
                    ec.monto - COALESCE(SUM(cv.monto_asignado), 0) AS disponible,
                    GROUP_CONCAT(cv.recibo_numero, ':', cv.monto_asignado SEPARATOR ',') AS vinculos_previos
             FROM estados_cuenta ec
             LEFT JOIN conciliacion_vinculos cv ON cv.estado_cuenta_id = ec.id
             WHERE " . implode(' AND ', $condiciones) . "
             GROUP BY ec.id
             HAVING disponible > 0
             ORDER BY ec.fecha DESC
             LIMIT 50"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vincula a mano uno o más recibos pendientes con UNA línea del estado
    // de cuenta (el caso de una empresa que paga por varios estudiantes con
    // la misma transferencia, o un depósito de un período muy anterior que
    // el cruce automático no habría encontrado). Todo bajo bloqueo
    // transaccional: se fija la línea primero (FOR UPDATE) para serializar
    // cualquier otro intento de vincular contra la MISMA línea — así nunca
    // se asigna más de lo que la línea realmente tiene disponible, aunque
    // dos administradores intenten vincular al mismo tiempo.
    // Devuelve ['ok' => true, 'asignado' => float] o ['ok' => false, 'error' => string].
    public static function vincularManual(PDO $db, int $estadoCuentaId, array $numerosRecibo, string $usuario): array {
        $db->beginTransaction();
        try {
            $stmtLinea = $db->prepare("SELECT id, cuenta, monto, recibo_numero FROM estados_cuenta WHERE id = ? FOR UPDATE");
            $stmtLinea->execute([$estadoCuentaId]);
            $linea = $stmtLinea->fetch(PDO::FETCH_ASSOC);
            if (!$linea) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'La línea del estado de cuenta ya no existe.'];
            }
            if ($linea['recibo_numero'] !== null) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Esta línea ya está completamente explicada por el cruce automático.'];
            }

            $stmtAsignado = $db->prepare("SELECT COALESCE(SUM(monto_asignado), 0) FROM conciliacion_vinculos WHERE estado_cuenta_id = ?");
            $stmtAsignado->execute([$estadoCuentaId]);
            $yaAsignado = (float)$stmtAsignado->fetchColumn();

            if (empty($numerosRecibo)) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Selecciona al menos un recibo.'];
            }

            $marcadores = implode(',', array_fill(0, count($numerosRecibo), '?'));
            $stmtRecibos = $db->prepare(
                "SELECT numero, deposito, cuenta_destino FROM recibospfs
                 WHERE numero IN ($marcadores) AND estado_transferencia = 'pendiente' AND anulado = 0
                 FOR UPDATE"
            );
            $stmtRecibos->execute($numerosRecibo);
            $recibos = $stmtRecibos->fetchAll(PDO::FETCH_ASSOC);

            if (count($recibos) !== count($numerosRecibo)) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Uno o más recibos ya no están pendientes (puede que alguien más los haya verificado).'];
            }

            $sumaRecibos = array_sum(array_column($recibos, 'deposito'));
            // Epsilon por redondeo de DOUBLE/DECIMAL, no para permitir
            // sobregirar la línea de verdad.
            if ($yaAsignado + $sumaRecibos > (float)$linea['monto'] + 0.01) {
                $db->rollBack();
                $disponible = (float)$linea['monto'] - $yaAsignado;
                return ['ok' => false, 'error' => "La suma de los recibos seleccionados (Q" . number_format($sumaRecibos, 2) . ") excede el disponible de la línea (Q" . number_format($disponible, 2) . ")."];
            }

            $stmtInsertar = $db->prepare(
                "INSERT INTO conciliacion_vinculos (estado_cuenta_id, recibo_numero, monto_asignado, vinculado_por) VALUES (?, ?, ?, ?)"
            );
            // COALESCE(cuenta_destino, ?): si ya tenía cuenta_destino, se
            // respeta (no debería cambiar); si era NULL (histórico), se
            // rellena con la cuenta real de la línea que se acaba de usar.
            $stmtMarcarRecibo = $db->prepare(
                "UPDATE recibospfs SET estado_transferencia = 'verificado', verificado_por = ?, fecha_verificacion = NOW(), cuenta_destino = COALESCE(cuenta_destino, ?) WHERE numero = ?"
            );

            foreach ($recibos as $r) {
                $stmtInsertar->execute([$estadoCuentaId, $r['numero'], $r['deposito'], $usuario]);
                $stmtMarcarRecibo->execute([$usuario, $linea['cuenta'], $r['numero']]);
            }

            if ($yaAsignado + $sumaRecibos >= (float)$linea['monto'] - 0.01) {
                $db->prepare("UPDATE estados_cuenta SET conciliado = 1 WHERE id = ?")->execute([$estadoCuentaId]);
            }

            $db->commit();
            return ['ok' => true, 'asignado' => $sumaRecibos];
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // INSERT IGNORE + la restricción única (cuenta, fecha, monto, referencia)
    // hacen que re-subir el mismo estado de cuenta sea seguro: una línea ya
    // importada se omite en silencio en vez de duplicarse. Pasó en vivo el
    // 2026-10-20 (el mismo archivo se subió dos veces, duplicando 51 líneas
    // reales sin aviso) — ahora el mismo re-upload simplemente no agrega
    // nada nuevo, y permite "completar" un archivo que la primera vez dejó
    // alguna fila fuera por un error de parseo puntual.
    // Devuelve ['insertadas' => N, 'omitidas' => M] (M = ya existían).
    public static function importarLineas(PDO $db, array $lineas, string $cuenta, string $usuario): array {
        $stmt = $db->prepare(
            "INSERT IGNORE INTO estados_cuenta (cuenta, fecha, monto, referencia, descripcion, importado_por) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insertadas = 0;
        $omitidas = 0;
        foreach ($lineas as $linea) {
            $stmt->execute([$cuenta, $linea['fecha'], $linea['monto'], $linea['referencia'] ?? null, $linea['descripcion'] ?? null, $usuario]);
            if ($stmt->rowCount() > 0) {
                $insertadas++;
            } else {
                $omitidas++;
            }
        }
        return ['insertadas' => $insertadas, 'omitidas' => $omitidas];
    }

    // Navegador libre de TODO lo importado (sin acotarse al período de
    // subida ni a un recibo puntual) — para responder "¿esta transferencia
    // ya está en el sistema?" sin tener que revisar la base de datos a
    // mano. $texto busca en referencia y descripción a la vez.
    public static function buscarMovimientos(PDO $db, ?string $cuenta, string $desde, string $hasta, string $texto): array {
        $condiciones = ["ec.fecha >= ?", "ec.fecha <= ?"];
        $params = [$desde, $hasta];
        if ($cuenta !== null) {
            $condiciones[] = "ec.cuenta = ?";
            $params[] = $cuenta;
        }
        if ($texto !== '') {
            $condiciones[] = "(ec.referencia LIKE ? OR ec.descripcion LIKE ?)";
            $params[] = '%' . $texto . '%';
            $params[] = '%' . $texto . '%';
        }
        $stmt = $db->prepare(
            "SELECT ec.id, ec.cuenta, ec.fecha, ec.monto, ec.referencia, ec.descripcion, ec.conciliado, ec.recibo_numero,
                    r.carne AS carne_automatico,
                    (SELECT GROUP_CONCAT(cv.recibo_numero SEPARATOR ', ') FROM conciliacion_vinculos cv WHERE cv.estado_cuenta_id = ec.id) AS recibos_manuales,
                    d.nodeposito AS deposito_propio, d.responsable AS deposito_responsable, d.efectivo AS deposito_efectivo
             FROM estados_cuenta ec
             LEFT JOIN recibospfs r ON r.numero = ec.recibo_numero
             LEFT JOIN depositos d ON d.nodeposito = ec.referencia AND d.cuenta = ec.cuenta
             WHERE " . implode(' AND ', $condiciones) . "
             ORDER BY ec.fecha DESC, ec.id DESC
             LIMIT 300"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Reporte de avance: recibos ya verificados, filtrado por CUÁNDO se
    // verificaron (no por la fecha del depósito) — para responder "¿cómo
    // va quedando el trabajo de conciliación de estos días?". manual=1 si
    // se resolvió por vinculación manual (conciliacion_vinculos), 0 si fue
    // por el cruce automático (fecha+monto exactos, ver antes verificarGrupo).
    public static function recibosVerificados(PDO $db, string $desde, string $hasta, ?string $cuenta): array {
        $condiciones = ["r.estado_transferencia = 'verificado'", "r.deposito > 0", "r.anulado = 0",
            "DATE(r.fecha_verificacion) >= ?", "DATE(r.fecha_verificacion) <= ?"];
        $params = [$desde, $hasta];
        if ($cuenta !== null) {
            $condiciones[] = "r.cuenta_destino = ?";
            $params[] = $cuenta;
        }
        $stmt = $db->prepare(
            "SELECT r.numero, r.carne, r.fechadep, r.deposito AS monto, r.cuenta_destino,
                    r.verificado_por, r.fecha_verificacion,
                    (SELECT 1 FROM conciliacion_vinculos cv WHERE cv.recibo_numero = r.numero LIMIT 1) AS es_manual
             FROM recibospfs r
             WHERE " . implode(' AND ', $condiciones) . "
             ORDER BY r.fecha_verificacion DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Clasifica cada línea del banco en el período con EXISTS correlacionados
    // (no JOIN+GROUP BY) a propósito: una línea con varios vínculos
    // manuales (pago dividido) daría una fila por vínculo si se usara JOIN,
    // inflando el SUM(monto) — EXISTS solo pregunta "¿tiene al menos uno?",
    // nunca duplica la fila.
    private const CLASIFICACION_SQL = "
        CASE
            WHEN ec.conciliado = 1 THEN 'marcada'
            WHEN EXISTS(SELECT 1 FROM conciliacion_vinculos cv WHERE cv.estado_cuenta_id = ec.id) THEN 'parcial'
            WHEN EXISTS(SELECT 1 FROM depositos d WHERE d.nodeposito = ec.referencia AND d.cuenta = ec.cuenta) THEN 'deposito_propio'
            ELSE 'sin_marcar'
        END";

    // Totales por estado (cantidad y monto) de las líneas del banco en el
    // período — "sin_marcar" es la métrica clave: probablemente son
    // estudiantes que ya transfirieron pero no han venido a pedir su
    // recibo, o depósitos propios sin boleta ingresada todavía.
    public static function resumenMovimientos(PDO $db, string $desde, string $hasta, ?string $cuenta): array {
        $condiciones = ["ec.fecha >= ?", "ec.fecha <= ?"];
        $params = [$desde, $hasta];
        if ($cuenta !== null) {
            $condiciones[] = "ec.cuenta = ?";
            $params[] = $cuenta;
        }
        $stmt = $db->prepare(
            "SELECT " . self::CLASIFICACION_SQL . " AS estado, COUNT(*) AS cantidad, SUM(ec.monto) AS total
             FROM estados_cuenta ec
             WHERE " . implode(' AND ', $condiciones) . "
             GROUP BY estado"
        );
        $stmt->execute($params);
        $porEstado = ['marcada' => ['cantidad' => 0, 'total' => 0.0], 'parcial' => ['cantidad' => 0, 'total' => 0.0],
            'deposito_propio' => ['cantidad' => 0, 'total' => 0.0], 'sin_marcar' => ['cantidad' => 0, 'total' => 0.0]];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porEstado[$fila['estado']] = ['cantidad' => (int)$fila['cantidad'], 'total' => (float)$fila['total']];
        }
        return $porEstado;
    }

    // Tendencia de "sin marcar" por mes (últimos N meses, independiente del
    // filtro del reporte) — para ver si el rezago crece, se mantiene o baja
    // en el tiempo, no solo un corte puntual.
    public static function tendenciaSinMarcar(PDO $db, ?string $cuenta, int $meses = 6): array {
        $condiciones = [self::CLASIFICACION_SQL . " = 'sin_marcar'", "ec.fecha >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)"];
        $params = [$meses];
        if ($cuenta !== null) {
            $condiciones[] = "ec.cuenta = ?";
            $params[] = $cuenta;
        }
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(ec.fecha, '%Y-%m') AS mes, COUNT(*) AS cantidad, SUM(ec.monto) AS total
             FROM estados_cuenta ec
             WHERE " . implode(' AND ', $condiciones) . "
             GROUP BY mes ORDER BY mes"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
