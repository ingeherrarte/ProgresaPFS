<?php
require_once __DIR__ . "/../config/Conexion.php";

class ConciliacionModel {

    // Recibos con pago por depósito/transferencia aún no verificados,
    // agrupados por (fecha, monto): no se intenta emparejar recibo por
    // recibo porque dos transferencias del mismo día y mismo monto son
    // indistinguibles entre sí, y no hace falta distinguirlas (el efecto
    // en caja es el mismo). numeros trae los números de recibo del grupo
    // para poder mostrarlos y para el UPDATE al verificar.
    // $desde/$hasta acotan la revisión al período que se está conciliando
    // (normalmente el que cubre el estado de cuenta subido) — la migración
    // marcó como 'pendiente' por default a TODO el histórico existente, y
    // no tiene sentido revisar años anteriores contra el estado de cuenta
    // actual.
    public static function pendientesAgrupados(PDO $db, string $desde, string $hasta): array {
        // Dos topes superiores a propósito, con razones distintas:
        // - fechadep <= CURDATE() es un invariante del dato: un depósito no
        //   puede tener fecha futura, y hay filas legacy con typos de
        //   digitación (años tipo 2052, 2041) que sin este filtro contaminan
        //   la lista porque "en el futuro" también cumple fechadep >= $desde.
        //   Se queda aunque el usuario pida un $hasta futuro.
        // - fechadep <= $hasta es el período que el usuario quiere revisar.
        //
        // Se agrupa también por cuenta_destino (cuál cuenta propia recibió
        // la transferencia) para no cruzar por error una transferencia a
        // BANRURAL con otra a BANTRAB que coincida en fecha y monto. Es
        // NULL para todo el histórico anterior a que existiera esta
        // columna: esos grupos quedan igual que antes (ver clave()).
        $stmt = $db->prepare(
            "SELECT fechadep AS fecha, deposito AS monto, cuenta_destino, COUNT(*) AS cantidad,
                    GROUP_CONCAT(numero ORDER BY numero) AS numeros
             FROM recibospfs
             WHERE deposito > 0 AND estado_transferencia = 'pendiente' AND anulado = 0
                   AND fechadep >= ? AND fechadep <= ? AND fechadep <= CURDATE()
             GROUP BY fechadep, deposito, cuenta_destino
             ORDER BY fechadep DESC, deposito DESC"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Tolerancia de fecha para cruzar un recibo contra el estado de cuenta:
    // una transferencia ACH puede acreditarse 1-2 días después de la fecha
    // de la boleta (fechadep) que digitó el cajero. Sin esta tolerancia,
    // ese desfase deja el recibo en "Falta importar" para siempre aunque
    // la línea correcta ya esté importada, porque las fechas nunca empatan
    // exacto. Es solo para BUSCAR líneas disponibles — pendientesAgrupados
    // sigue agrupando por la fecha EXACTA de la boleta (es el dato real).
    private const TOLERANCIA_DIAS_ACH = 2;

    // Cuenta, para un grupo pendiente puntual, cuántas líneas del estado de
    // cuenta sin conciliar hay dentro de ±TOLERANCIA_DIAS_ACH de su fecha,
    // con el mismo monto y cuenta. Sin cuenta_destino conocida (histórico
    // previo a esa columna), cualquier cuenta cuenta — igual que antes de
    // que existiera la columna.
    private static function contarDisponibles(PDO $db, string $fecha, $monto, ?string $cuenta): int {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM estados_cuenta
             WHERE conciliado = 0 AND monto = ?
                   AND fecha BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND DATE_ADD(?, INTERVAL ? DAY)
                   AND (? IS NULL OR cuenta = ?)"
        );
        $stmt->execute([$monto, $fecha, self::TOLERANCIA_DIAS_ACH, $fecha, self::TOLERANCIA_DIAS_ACH, $cuenta, $cuenta]);
        return (int)$stmt->fetchColumn();
    }

    // Para un grupo sin cuenta_destino conocida (histórico), desglosa las
    // líneas disponibles POR BANCO — sin esto, "2 disponibles" no dice de
    // cuál de los dos bancos vienen, y no hay forma de saber en qué pestaña
    // buscar ni qué pasaría al verificar.
    private static function contarDisponiblesPorCuenta(PDO $db, string $fecha, $monto): array {
        $stmt = $db->prepare(
            "SELECT cuenta, COUNT(*) AS cantidad FROM estados_cuenta
             WHERE conciliado = 0 AND monto = ?
                   AND fecha BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND DATE_ADD(?, INTERVAL ? DAY)
             GROUP BY cuenta"
        );
        $stmt->execute([$monto, $fecha, self::TOLERANCIA_DIAS_ACH, $fecha, self::TOLERANCIA_DIAS_ACH]);
        $porCuenta = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porCuenta[$fila['cuenta']] = (int)$fila['cantidad'];
        }
        return $porCuenta;
    }

    // Agrega a cada grupo pendiente cuántas líneas del estado de cuenta hay
    // disponibles (dentro de la tolerancia ACH), para que la vista pueda
    // mostrar "sugerido" (alcanza) o "falta" (no alcanza). Es solo una
    // sugerencia — verificarGrupo() hace su propio chequeo con bloqueo al
    // confirmar, así que un conteo optimista aquí no genera inconsistencias.
    public static function sugerencias(PDO $db, string $desde, string $hasta): array {
        $pendientes = self::pendientesAgrupados($db, $desde, $hasta);
        foreach ($pendientes as &$grupo) {
            $grupo['disponibles'] = self::contarDisponibles($db, $grupo['fecha'], $grupo['monto'], $grupo['cuenta_destino']);
            $grupo['sugerido'] = $grupo['disponibles'] >= (int)$grupo['cantidad'];
            $grupo['disponibles_por_cuenta'] = $grupo['cuenta_destino']
                ? null
                : self::contarDisponiblesPorCuenta($db, $grupo['fecha'], $grupo['monto']);
        }
        unset($grupo);

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

    // Verifica un grupo completo: toma tantas líneas de estados_cuenta como
    // recibos pendientes haya en (fecha, monto) y las empareja 1:1 (el
    // orden dentro del grupo es arbitrario a propósito, ver comentario en
    // pendientesAgrupados). Todo dentro de una transacción con FOR UPDATE
    // para que dos administradores no verifiquen el mismo grupo a la vez.
    // Devuelve el número de recibos verificados, o null si ya no alcanza
    // (alguien más consumió las líneas del estado de cuenta primero).
    public static function verificarGrupo(PDO $db, string $fecha, float $monto, ?string $cuenta, string $usuario): ?int {
        $db->beginTransaction();
        try {
            // $cuenta null: grupo legacy sin cuenta_destino conocida — se
            // acota solo por fecha+monto (comportamiento de antes de que
            // existiera la columna) y las líneas del estado de cuenta se
            // toman de cualquier cuenta, igual que en disponiblesAgrupados().
            $condicionRecibo = "fechadep = ? AND deposito = ? AND estado_transferencia = 'pendiente' AND anulado = 0";
            $paramsRecibo = [$fecha, $monto];
            if ($cuenta !== null) {
                $condicionRecibo .= " AND cuenta_destino = ?";
                $paramsRecibo[] = $cuenta;
            } else {
                $condicionRecibo .= " AND cuenta_destino IS NULL";
            }

            $stmtRecibos = $db->prepare("SELECT numero FROM recibospfs WHERE $condicionRecibo FOR UPDATE");
            $stmtRecibos->execute($paramsRecibo);
            $numeros = $stmtRecibos->fetchAll(PDO::FETCH_COLUMN);

            if (empty($numeros)) {
                $db->rollBack();
                return 0;
            }

            // Misma tolerancia que en sugerencias()/contarDisponibles(): una
            // transferencia ACH puede acreditarse 1-2 días después de la
            // fecha de la boleta. Se ordena por cercanía a la fecha real del
            // recibo (ABS(DATEDIFF)) para preferir siempre la línea más
            // probable en vez de una casualidad de monto más alejada.
            $condicionLinea = "fecha BETWEEN DATE_SUB(?, INTERVAL ? DAY) AND DATE_ADD(?, INTERVAL ? DAY) AND monto = ? AND conciliado = 0";
            $paramsLinea = [$fecha, self::TOLERANCIA_DIAS_ACH, $fecha, self::TOLERANCIA_DIAS_ACH, $monto];
            if ($cuenta !== null) {
                $condicionLinea .= " AND cuenta = ?";
                $paramsLinea[] = $cuenta;
            }
            $paramsLinea[] = $fecha; // para el ORDER BY ABS(DATEDIFF(...))

            $stmtLineas = $db->prepare(
                "SELECT id, cuenta FROM estados_cuenta
                 WHERE $condicionLinea
                 ORDER BY ABS(DATEDIFF(fecha, ?)), id LIMIT " . count($numeros) . " FOR UPDATE"
            );
            $stmtLineas->execute($paramsLinea);
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

            if (count($lineas) < count($numeros)) {
                $db->rollBack();
                return null;
            }

            // $cuenta null: el recibo no sabía a qué banco entró. Al
            // verificar queda claro (la línea que realmente lo cubrió), así
            // que se rellena cuenta_destino en vez de dejarlo "(sin dato)"
            // para siempre — de lo contrario este mismo hueco se repite en
            // cada futura conciliación.
            $stmtMarcarRecibo = $cuenta !== null
                ? $db->prepare("UPDATE recibospfs SET estado_transferencia = 'verificado', verificado_por = ?, fecha_verificacion = NOW() WHERE numero = ?")
                : $db->prepare("UPDATE recibospfs SET estado_transferencia = 'verificado', verificado_por = ?, fecha_verificacion = NOW(), cuenta_destino = ? WHERE numero = ?");
            $stmtMarcarLinea = $db->prepare(
                "UPDATE estados_cuenta SET conciliado = 1, recibo_numero = ? WHERE id = ?"
            );

            foreach ($numeros as $i => $numero) {
                if ($cuenta !== null) {
                    $stmtMarcarRecibo->execute([$usuario, $numero]);
                } else {
                    $stmtMarcarRecibo->execute([$usuario, $lineas[$i]['cuenta'], $numero]);
                }
                $stmtMarcarLinea->execute([$numero, $lineas[$i]['id']]);
            }

            $db->commit();
            return count($numeros);
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // Búsqueda libre de recibos pendientes (para vincular a mano cuando el
    // cruce automático no aplica: un depósito de un período muy anterior,
    // o una transferencia que cubre varios recibos a la vez). A diferencia
    // de pendientesAgrupados(), NO agrupa por fecha+monto exactos ni acota
    // por rango de fechas — es justo lo que permite encontrar un recibo
    // "fuera del período" que la lista normal no muestra.
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
            // rellena con la cuenta real de la línea, igual que en
            // verificarGrupo().
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

    public static function importarLineas(PDO $db, array $lineas, string $cuenta, string $usuario): int {
        $stmt = $db->prepare(
            "INSERT INTO estados_cuenta (cuenta, fecha, monto, referencia, importado_por) VALUES (?, ?, ?, ?, ?)"
        );
        $insertadas = 0;
        foreach ($lineas as $linea) {
            $stmt->execute([$cuenta, $linea['fecha'], $linea['monto'], $linea['referencia'] ?? null, $usuario]);
            $insertadas++;
        }
        return $insertadas;
    }
}
?>
