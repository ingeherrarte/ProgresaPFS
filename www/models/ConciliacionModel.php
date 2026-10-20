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
        }
        unset($grupo);

        return $pendientes;
    }

    // Líneas del estado de cuenta ya importadas dentro del período, con el
    // recibo contra el que quedó marcada cada una (NULL si sigue pendiente).
    // Es el equivalente de la hoja de Excel donde se anotaba a mano el No.
    // de recibo a la par de cada línea del banco.
    public static function lineasEstadoCuenta(PDO $db, string $desde, string $hasta): array {
        $stmt = $db->prepare(
            "SELECT ec.id, ec.cuenta, ec.fecha, ec.monto, ec.referencia,
                    ec.conciliado, ec.recibo_numero, r.carne
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
                "SELECT id FROM estados_cuenta
                 WHERE $condicionLinea
                 ORDER BY ABS(DATEDIFF(fecha, ?)), id LIMIT " . count($numeros) . " FOR UPDATE"
            );
            $stmtLineas->execute($paramsLinea);
            $ids = $stmtLineas->fetchAll(PDO::FETCH_COLUMN);

            if (count($ids) < count($numeros)) {
                $db->rollBack();
                return null;
            }

            $stmtMarcarRecibo = $db->prepare(
                "UPDATE recibospfs SET estado_transferencia = 'verificado', verificado_por = ?, fecha_verificacion = NOW() WHERE numero = ?"
            );
            $stmtMarcarLinea = $db->prepare(
                "UPDATE estados_cuenta SET conciliado = 1, recibo_numero = ? WHERE id = ?"
            );

            foreach ($numeros as $i => $numero) {
                $stmtMarcarRecibo->execute([$usuario, $numero]);
                $stmtMarcarLinea->execute([$numero, $ids[$i]]);
            }

            $db->commit();
            return count($numeros);
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
