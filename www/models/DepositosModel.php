<?php
require_once __DIR__ . "/../config/Conexion.php";

class DepositosModel {

    // La columna `banco` existe en la tabla pero el script legacy nunca la
    // llenaba (el <select> solo listaba números de cuenta). Se deriva aquí
    // de una lista blanca fija en vez de aceptar el nombre del banco desde
    // el request.
    private static array $cuentas = [
        '2870011720' => 'BANTRAB',
        '3099203897' => 'BANRURAL',
    ];

    public static function cuentas(): array {
        return self::$cuentas;
    }

    public static function bancoDeCuenta(string $cuenta): ?string {
        return self::$cuentas[$cuenta] ?? null;
    }

    // Devuelve true, 'duplicado' (nodeposito ya existe) o false (error de BD).
    public static function crear(PDO $db, array $datos): bool|string {
        $sql = "INSERT INTO depositos
            (nodeposito, fechadep, cuenta, banco, correspondiente, efectivo, chpropio, chotrobanco, responsable, usuario, horaregistro, foto_boleta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $db->prepare($sql);

        try {
            $stmt->execute([
                $datos['nodeposito'], $datos['fechadep'], $datos['cuenta'], $datos['banco'],
                $datos['correspondiente'], $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'],
                $datos['responsable'], $datos['usuario'], $datos['foto_boleta'],
            ]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'duplicado';
            }
            error_log("Error al guardar depósito: " . $e->getMessage());
            return false;
        }
    }

    public static function obtenerRecientes(PDO $db, int $limite = 30): array {
        $stmt = $db->prepare("SELECT * FROM depositos ORDER BY horaregistro DESC LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Depósitos de un rango de fechas, por fecha de depósito o por
    // "Correspondiente a". $campo viene de una lista blanca, nunca del
    // request tal cual (se interpola en el SQL).
    // Boletas con "Correspondiente a" posterior a la fecha de depósito: no
    // pueden ser correctas (no se deposita dinero antes de recibirlo) y
    // descuadran dos días a la vez. Desde el 07/11/2026 el formulario ya
    // no lo permite; esto deja ver las que se registraron antes.
    public static function fechasMalIngresadas(PDO $db): array {
        return $db->query(
            "SELECT nodeposito, fechadep, correspondiente, cuenta, banco, responsable, usuario, horaregistro,
                    efectivo + chpropio + chotrobanco AS monto
               FROM depositos
              WHERE correspondiente > fechadep
              ORDER BY fechadep DESC, nodeposito"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorRango(PDO $db, string $desde, string $hasta, string $campo): array {
        $campo = $campo === 'correspondiente' ? 'correspondiente' : 'fechadep';
        $stmt = $db->prepare(
            "SELECT * FROM depositos WHERE $campo BETWEEN ? AND ? ORDER BY $campo DESC, horaregistro DESC"
        );
        $stmt->execute([$desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorNumero(PDO $db, string $nodeposito): ?array {
        $stmt = $db->prepare("SELECT * FROM depositos WHERE nodeposito = ?");
        $stmt->execute([$nodeposito]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    // nodeposito NO está aquí a propósito: es la llave con la que se busca
    // el registro (igual que `numero` en recibos), no un campo editable —
    // si de verdad se digitó mal, se corrige borrando y volviendo a
    // capturar, para no perder la trazabilidad del historial (ver
    // historialEdiciones, que queda indexado por este número).
    private static array $camposEditables = [
        'fechadep' => 'Fecha de depósito',
        'cuenta' => 'Cuenta',
        'correspondiente' => 'Correspondiente a',
        'efectivo' => 'Efectivo',
        'chpropio' => 'Cheque propio banco',
        'chotrobanco' => 'Cheque otros bancos',
        'responsable' => 'Responsable',
        'foto_boleta' => 'Foto de la boleta',
    ];

    private const CAMPOS_MONTO = ['efectivo', 'chpropio', 'chotrobanco'];

    public static function camposEditables(): array {
        return self::$camposEditables;
    }

    public static function etiquetaCampo(string $campo): string {
        return self::$camposEditables[$campo] ?? $campo;
    }

    // Misma razón que RecibosPfsModel::normalizar(): la BD devuelve los
    // montos como "1900" y el formulario los manda como "1900.00" — son el
    // mismo valor y no deben quedar registrados como un cambio real.
    private static function normalizar(string $campo, $valor): string {
        if (in_array($campo, self::CAMPOS_MONTO, true)) {
            return number_format((float)$valor, 2, '.', '');
        }
        return trim((string)$valor);
    }

    // Deja una fila por campo modificado en depositos_ediciones (motivo +
    // usuario + antes/después), igual que RecibosPfsModel::actualizar().
    // Sin transacción que cubra ambas escrituras porque depositos es
    // MyISAM (legacy) y no soporta transacciones — se actualiza primero el
    // depósito y solo después se escribe la bitácora, para no registrar un
    // cambio que la BD rechazó. Devuelve la lista de cambios aplicados, o
    // [] si no hubo ninguno.
    public static function actualizar(PDO $db, string $nodeposito, array $datos, string $motivo, string $usuario): array {
        $actual = self::obtenerPorNumero($db, $nodeposito);
        if (!$actual) {
            return [];
        }

        $cambios = [];
        foreach (array_keys(self::$camposEditables) as $campo) {
            $antes = self::normalizar($campo, $actual[$campo]);
            $despues = self::normalizar($campo, $datos[$campo]);
            if ($antes !== $despues) {
                $cambios[] = ['campo' => $campo, 'anterior' => $antes, 'nuevo' => $despues];
            }
        }

        if (empty($cambios)) {
            return [];
        }

        $stmt = $db->prepare(
            "UPDATE depositos SET fechadep = ?, cuenta = ?, banco = ?, correspondiente = ?,
                efectivo = ?, chpropio = ?, chotrobanco = ? , responsable = ?, foto_boleta = ?
             WHERE nodeposito = ?"
        );
        $stmt->execute([
            $datos['fechadep'], $datos['cuenta'], $datos['banco'], $datos['correspondiente'],
            $datos['efectivo'], $datos['chpropio'], $datos['chotrobanco'], $datos['responsable'],
            $datos['foto_boleta'], $nodeposito,
        ]);

        if ($stmt->rowCount() === 0) {
            return [];
        }

        $log = $db->prepare(
            "INSERT INTO depositos_ediciones (nodeposito, campo, valor_anterior, valor_nuevo, motivo, usuario)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($cambios as $c) {
            $log->execute([$nodeposito, $c['campo'], $c['anterior'], $c['nuevo'], $motivo, $usuario]);
        }

        return $cambios;
    }

    public static function historialEdiciones(PDO $db, string $nodeposito): array {
        $stmt = $db->prepare(
            "SELECT campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM depositos_ediciones WHERE nodeposito = ? ORDER BY fecha DESC, id DESC"
        );
        $stmt->execute([$nodeposito]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // A diferencia de historialEdiciones() (un depósito a la vez), esto
    // es la bitácora completa: se muestra en "Editar Depósito" cuando
    // todavía no se ha buscado ningún número, para poder ver de un vistazo
    // qué depósitos se han corregido sin tener que adivinar cuál buscar.
    public static function edicionesRecientes(PDO $db, int $limite = 50): array {
        $stmt = $db->prepare(
            "SELECT nodeposito, campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM depositos_ediciones ORDER BY fecha DESC, id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mismas reglas que al crear (ver DepositosController::validar),
    // adaptadas a edición: aquí además es obligatorio el motivo, que queda
    // en la bitácora junto con el valor anterior de cada campo modificado.
    public static function validarEdicion(array $post): array {
        $errores = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['fechadep'] ?? '')) {
            $errores[] = "La fecha de depósito no es válida.";
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['correspondiente'] ?? '')) {
            $errores[] = "La fecha a la que corresponde el depósito no es válida.";
        }
        // Un depósito solo puede cubrir ingresos de ese día o de días
        // anteriores: el dinero no se puede depositar antes de recibirlo.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['fechadep'] ?? '')
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['correspondiente'] ?? '')
            && $post['correspondiente'] > $post['fechadep']) {
            $errores[] = "\"Correspondiente a\" no puede ser posterior a la fecha de depósito: el depósito cubre ingresos de ese día o de días anteriores.";
        }

        $cuenta = trim($post['cuenta'] ?? '');
        if (!array_key_exists($cuenta, self::$cuentas)) {
            $errores[] = "Seleccione una cuenta válida.";
        }

        foreach (self::CAMPOS_MONTO as $campo) {
            $valor = $post[$campo] ?? '0';
            if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
                $errores[] = "El campo '$campo' debe ser un monto numérico válido.";
            }
        }

        $total = (float)($post['efectivo'] ?? 0) + (float)($post['chpropio'] ?? 0) + (float)($post['chotrobanco'] ?? 0);
        if ($total <= 0) {
            $errores[] = "Debe ingresar al menos un monto en efectivo o cheque.";
        }

        if (trim($post['responsable'] ?? '') === '') {
            $errores[] = "El responsable es obligatorio.";
        }
        if (trim($post['motivo'] ?? '') === '') {
            $errores[] = "Debe indicar el motivo de la edición.";
        }

        return $errores;
    }

    // ── Cuadre boletas ↔ Cierre del Día ───────────────────────────────
    // Lo que entró en caja un día (efectivo + cheques de los recibos no
    // anulados) debe salir al banco en boletas cuyo "Correspondiente a"
    // sea ese mismo día. Se comparan efectivo + cheques juntos porque en la
    // práctica los cheques recibidos se depositan en la misma boleta y a
    // veces se anotan como efectivo; comparar solo efectivo marcaría
    // descuadres que no existen.

    public const TOLERANCIA_CUADRE = 0.005;

    public static function cuadreDia(PDO $db, string $fecha): array {
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(efectivo), 0) AS efectivo, COALESCE(SUM(cheque), 0) AS cheque
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 0"
        );
        $stmt->execute([$fecha]);
        $cierre = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            "SELECT nodeposito, fechadep, banco, efectivo, chpropio, chotrobanco, responsable, foto_boleta
             FROM depositos WHERE correspondiente = ? ORDER BY fechadep, horaregistro"
        );
        $stmt->execute([$fecha]);
        $boletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cierreTotal = (float)$cierre['efectivo'] + (float)$cierre['cheque'];
        $depositado = 0.0;
        foreach ($boletas as $b) {
            $depositado += (float)$b['efectivo'] + (float)$b['chpropio'] + (float)$b['chotrobanco'];
        }
        $diferencia = round($cierreTotal - $depositado, 2);

        return [
            'fecha' => $fecha,
            'cierre_efectivo' => (float)$cierre['efectivo'],
            'cierre_cheque' => (float)$cierre['cheque'],
            'cierre_total' => $cierreTotal,
            'depositado' => $depositado,
            // > 0: falta depositar; < 0: se depositó de más (o la fecha está mal)
            'diferencia' => $diferencia,
            'cuadra' => abs($diferencia) < self::TOLERANCIA_CUADRE,
            'boletas' => $boletas,
        ];
    }

    // ── Reporte "Cuadre de Depósitos" ─────────────────────────────────
    // Desde esta fecha las boletas se registran de forma constante en el
    // sistema (antes de eso hay días con caja y sin ninguna boleta, que
    // inflarían el pendiente con dinero que sí se depositó).
    public const INICIO_CUADRE = '2026-08-12';

    // Un faltante de un día que se cancela con un sobrante igual en los
    // días siguientes es casi siempre una boleta con el "Correspondiente a"
    // del día del depósito en vez del día del ingreso (p. ej. ingresos
    // fuera de horario depositados al día siguiente). Se marca como
    // "compensado", no como error. Tolerancia de Q0.05 para centavos.
    public const DIAS_COMPENSACION = 7;
    private const TOLERANCIA_COMPENSACION = 0.05;

    // Un día por fila (ascendente) desde INICIO_CUADRE hasta $hasta, con
    // caja, boletas, diferencia del día, pendiente acumulado y estado. El
    // acumulado siempre arranca en INICIO_CUADRE aunque la vista filtre un
    // rango menor, para que el saldo arrastrado sea el real.
    public static function cuadrePorDias(PDO $db, string $hasta): array {
        $inicio = self::INICIO_CUADRE;
        $hoy = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT DATE(horaregistro) AS dia, SUM(efectivo) AS efectivo, SUM(cheque) AS cheque, COUNT(*) AS recibos
             FROM recibospfs
             WHERE anulado = 0 AND horaregistro >= ? AND horaregistro < DATE_ADD(?, INTERVAL 1 DAY)
             GROUP BY DATE(horaregistro)"
        );
        $stmt->execute([$inicio, $hasta]);
        $caja = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $caja[$r['dia']] = $r;
        }

        foreach (self::boletasConBanco($db, $inicio, $hasta) as $b) {
            $boletasPorDia[$b['correspondiente']][] = $b;
        }
        $boletasPorDia ??= [];

        $dias = array_unique(array_merge(array_keys($caja), array_keys($boletasPorDia)));
        sort($dias);

        $filas = [];
        $acumulado = 0.0;
        foreach ($dias as $dia) {
            $ef = (float)($caja[$dia]['efectivo'] ?? 0);
            $ch = (float)($caja[$dia]['cheque'] ?? 0);
            $boletas = $boletasPorDia[$dia] ?? [];
            $depositado = array_sum(array_column($boletas, 'monto'));
            if ($ef + $ch == 0 && $depositado == 0) {
                continue; // solo transferencias ese día: nada que depositar
            }
            $dif = round($ef + $ch - $depositado, 2);
            $acumulado = round($acumulado + $dif, 2);
            $filas[] = [
                'dia' => $dia,
                'efectivo' => $ef,
                'cheque' => $ch,
                'caja' => $ef + $ch,
                'depositado' => $depositado,
                'diferencia' => $dif,
                'acumulado' => $acumulado,
                'boletas' => $boletas,
                'estado' => abs($dif) < self::TOLERANCIA_CUADRE ? 'cuadra' : null,
                'pareja' => null,
            ];
        }

        // Emparejar faltante/sobrante opuestos dentro de DIAS_COMPENSACION.
        $n = count($filas);
        for ($i = 0; $i < $n; $i++) {
            if ($filas[$i]['estado'] !== null) {
                continue;
            }
            $limite = date('Y-m-d', strtotime($filas[$i]['dia'] . ' +' . self::DIAS_COMPENSACION . ' days'));
            for ($j = $i + 1; $j < $n && $filas[$j]['dia'] <= $limite; $j++) {
                if ($filas[$j]['estado'] === null
                    && abs($filas[$i]['diferencia'] + $filas[$j]['diferencia']) < self::TOLERANCIA_COMPENSACION) {
                    $filas[$i]['estado'] = $filas[$j]['estado'] = 'compensado';
                    $filas[$i]['pareja'] = $filas[$j]['dia'];
                    $filas[$j]['pareja'] = $filas[$i]['dia'];
                    break;
                }
            }
        }
        foreach ($filas as &$f) {
            if ($f['estado'] === null) {
                $f['estado'] = $f['diferencia'] < 0 ? 'sobra' : ($f['dia'] === $hoy ? 'hoy' : 'falta');
            }
        }
        unset($f);

        return $filas;
    }

    // Para la gráfica del Dashboard: por cada fecha "Correspondiente a",
    // la suma de las boletas registradas en el sistema (varias boletas de
    // un mismo día se agrupan y suman) contra la suma de lo que el estado
    // de cuenta registra para esas mismas boletas. `pendientes` = boletas
    // de ese día cuyo estado de cuenta todavía no se ha importado: ahí el
    // lado del banco está incompleto, no descuadrado.
    public static function sistemaVsBancoPorDia(PDO $db, string $desde, string $hasta): array {
        $porDia = [];
        foreach (self::boletasConBanco($db, $desde, $hasta) as $b) {
            $d = $b['correspondiente'];
            $porDia[$d] ??= ['dia' => $d, 'sistema' => 0.0, 'banco' => 0.0, 'boletas' => 0, 'pendientes' => 0, 'no_aparece' => 0, 'monto_distinto' => 0];
            $porDia[$d]['sistema'] += $b['monto'];
            $porDia[$d]['banco'] += (float)($b['banco_monto'] ?? 0);
            $porDia[$d]['boletas']++;
            if ($b['banco_estado'] === 'sin_importar') {
                $porDia[$d]['pendientes']++;
            } elseif ($b['banco_estado'] === 'no_aparece') {
                $porDia[$d]['no_aparece']++;
            } elseif ($b['banco_estado'] === 'monto_distinto') {
                $porDia[$d]['monto_distinto']++;
            }
        }
        ksort($porDia);
        return array_values($porDia);
    }

    // Boletas con su línea del estado de cuenta (mismo cruce que usa
    // Conciliación: referencia del banco = número de boleta, misma cuenta).
    // banco_estado: ok | monto_distinto | no_aparece | sin_importar.
    // "sin_importar" = la boleta es posterior a la última línea importada
    // de esa cuenta, así que todavía no se puede saber si entró.
    public static function boletasConBanco(PDO $db, string $desde, string $hasta): array {
        $ultimas = [];
        foreach ($db->query("SELECT cuenta, MAX(fecha) AS ultima FROM estados_cuenta GROUP BY cuenta") as $r) {
            $ultimas[$r['cuenta']] = $r['ultima'];
        }

        $stmt = $db->prepare(
            "SELECT d.nodeposito, d.fechadep, d.correspondiente, d.cuenta, d.banco, d.efectivo, d.chpropio, d.chotrobanco,
                    d.responsable, d.foto_boleta,
                    (SELECT ec.fecha FROM estados_cuenta ec
                      WHERE ec.referencia = d.nodeposito AND ec.cuenta = d.cuenta AND ec.tipo = 'credito' AND ec.excluido = 0
                      ORDER BY ec.fecha LIMIT 1) AS banco_fecha,
                    (SELECT ec.monto FROM estados_cuenta ec
                      WHERE ec.referencia = d.nodeposito AND ec.cuenta = d.cuenta AND ec.tipo = 'credito' AND ec.excluido = 0
                      ORDER BY ec.fecha LIMIT 1) AS banco_monto
             FROM depositos d
             WHERE d.correspondiente BETWEEN ? AND ?
             ORDER BY d.correspondiente, d.fechadep, d.horaregistro"
        );
        $stmt->execute([$desde, $hasta]);
        $boletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($boletas as &$b) {
            $b['monto'] = (float)$b['efectivo'] + (float)$b['chpropio'] + (float)$b['chotrobanco'];
            if ($b['banco_fecha'] !== null) {
                $b['banco_estado'] = abs((float)$b['banco_monto'] - $b['monto']) < self::TOLERANCIA_CUADRE ? 'ok' : 'monto_distinto';
            } else {
                $ultima = $ultimas[$b['cuenta']] ?? null;
                $b['banco_estado'] = ($ultima !== null && $b['fechadep'] <= $ultima) ? 'no_aparece' : 'sin_importar';
            }
            $b['ultima_importada'] = $ultimas[$b['cuenta']] ?? null;
        }
        unset($b);
        return $boletas;
    }
}
?>
