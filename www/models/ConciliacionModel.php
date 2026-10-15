<?php
require_once __DIR__ . "/../config/Conexion.php";

class ConciliacionModel {

    // Recibos con pago por depósito/transferencia aún no verificados,
    // agrupados por (fecha, monto): no se intenta emparejar recibo por
    // recibo porque dos transferencias del mismo día y mismo monto son
    // indistinguibles entre sí, y no hace falta distinguirlas (el efecto
    // en caja es el mismo). numeros trae los números de recibo del grupo
    // para poder mostrarlos y para el UPDATE al verificar.
    // $desde acota la revisión a partir de esa fecha (inclusive) — la
    // migración marcó como 'pendiente' por default a TODO el histórico
    // existente, y no tiene sentido revisar años anteriores contra el
    // estado de cuenta actual.
    public static function pendientesAgrupados(PDO $db, string $desde): array {
        // fechadep <= CURDATE(): un depósito no puede tener fecha futura.
        // Hay filas legacy con typos de digitación (años tipo 2052, 2041)
        // que sin este filtro contaminan la lista de pendientes porque
        // "en el futuro" también cumple fechadep >= $desde.
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
                   AND fechadep >= ? AND fechadep <= CURDATE()
             GROUP BY fechadep, deposito, cuenta_destino
             ORDER BY fechadep DESC, deposito DESC"
        );
        $stmt->execute([$desde]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Líneas del estado de cuenta aún no usadas en ninguna verificación.
    // Devuelve dos mapas: uno preciso por (fecha, monto, cuenta) para
    // recibos que ya saben a qué cuenta entraron, y otro agregado por
    // (fecha, monto) sin importar la cuenta, para los recibos legacy sin
    // cuenta_destino (NULL) que no pueden pedir esa precisión.
    public static function disponiblesAgrupados(PDO $db): array {
        $stmt = $db->query(
            "SELECT fecha, monto, cuenta, COUNT(*) AS cantidad
             FROM estados_cuenta
             WHERE conciliado = 0
             GROUP BY fecha, monto, cuenta"
        );
        $porCuenta = [];
        $porFechaMonto = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $claveFm = self::clave($fila['fecha'], $fila['monto']);
            $porCuenta[$claveFm . '|' . $fila['cuenta']] = (int)$fila['cantidad'];
            $porFechaMonto[$claveFm] = ($porFechaMonto[$claveFm] ?? 0) + (int)$fila['cantidad'];
        }
        return ['por_cuenta' => $porCuenta, 'por_fecha_monto' => $porFechaMonto];
    }

    // estados_cuenta.monto es DECIMAL (PDO lo entrega como string "900.00")
    // y recibospfs.deposito es DOUBLE (PDO lo entrega como float 900): sin
    // normalizar ambos lados antes de concatenar, la clave de comparación
    // salía "900.00" de un lado y "900" del otro — nunca coincidían aunque
    // el monto real fuera idéntico, y ningún grupo se sugería jamás.
    private static function clave(string $fecha, $monto): string {
        return $fecha . '|' . number_format((float)$monto, 2, '.', '');
    }

    // Combina ambos lados: cada grupo pendiente trae cuántas líneas del
    // estado de cuenta hay disponibles con esa misma fecha+monto, para que
    // la vista pueda mostrar "sugerido" (alcanza) o "falta" (no alcanza).
    public static function sugerencias(PDO $db, string $desde): array {
        $pendientes = self::pendientesAgrupados($db, $desde);
        $disponibles = self::disponiblesAgrupados($db);

        foreach ($pendientes as &$grupo) {
            $claveFm = self::clave($grupo['fecha'], $grupo['monto']);
            // Con cuenta conocida: solo cuentan como disponibles las líneas
            // de esa MISMA cuenta. Sin cuenta (histórico): como antes de
            // que existiera la columna, cualquier línea de cualquier
            // cuenta con esa fecha+monto sirve.
            $grupo['disponibles'] = $grupo['cuenta_destino']
                ? ($disponibles['por_cuenta'][$claveFm . '|' . $grupo['cuenta_destino']] ?? 0)
                : ($disponibles['por_fecha_monto'][$claveFm] ?? 0);
            $grupo['sugerido'] = $grupo['disponibles'] >= (int)$grupo['cantidad'];
        }
        unset($grupo);

        return $pendientes;
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

            $condicionLinea = "fecha = ? AND monto = ? AND conciliado = 0";
            $paramsLinea = [$fecha, $monto];
            if ($cuenta !== null) {
                $condicionLinea .= " AND cuenta = ?";
                $paramsLinea[] = $cuenta;
            }

            $stmtLineas = $db->prepare(
                "SELECT id FROM estados_cuenta
                 WHERE $condicionLinea
                 ORDER BY id LIMIT " . count($numeros) . " FOR UPDATE"
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
