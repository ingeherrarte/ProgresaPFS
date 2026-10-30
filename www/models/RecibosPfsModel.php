<?php
require_once __DIR__ . "/../config/Conexion.php";
require_once __DIR__ . "/EstudiantesPfsModel.php";
require_once __DIR__ . "/DepositosModel.php";

class RecibosPfsModel {

    // Lista blanca de columnas buscables. El nombre de columna se interpola
    // en el SQL, pero solo puede ser una de estas claves fijas: nunca un
    // valor tomado del request (a diferencia del buscador legacy, que
    // aceptaba cualquier nombre de columna vía SHOW FIELDS + $_POST).
    private static array $columnasBuscables = [
        'numero'  => 'Número de recibo',
        'carne'   => 'Carné',
        'detalle' => 'Detalle',
        'usuario' => 'Registrado por',
        'banco'   => 'Banco',
    ];

    public static function columnasBuscables(): array {
        return self::$columnasBuscables;
    }

    public static function buscar(PDO $db, string $campo, string $palabra): array {
        if (!array_key_exists($campo, self::$columnasBuscables)) {
            throw new InvalidArgumentException("Campo de búsqueda no permitido: $campo");
        }

        $sql = "SELECT * FROM recibospfs WHERE `$campo` LIKE ? ORDER BY horaregistro DESC LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute(['%' . $palabra . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function siguienteNumero(PDO $db): int {
        return (int)$db->query("SELECT COALESCE(MAX(numero),0)+1 FROM recibospfs")->fetchColumn();
    }

    // El correlativo se calcula con MAX(numero)+1 (tabla legacy MyISAM sin
    // soporte de autoincrement/transacciones). Ante una colisión (numero es
    // PRIMARY KEY) se reintenta con un nuevo correlativo en vez de fallar.
    public static function insertar(PDO $db, array $datos): array {
        $sql = "INSERT INTO recibospfs
            (numero, carne, fechadelpago, primero, segundo, tercero, cuarto,
             mesquepaga, mensualidad, inscripcion, otro, detalle,
             efectivo, deposito, nodeposito, fechadep, cheque, nocheque, banco, cuenta_destino,
             usuario, horaregistro, aleatorio, foto_deposito)
            VALUES
            (?, ?, ?, 0, 0, 0, 0,
             ?, ?, ?, ?, ?,
             ?, ?, ?, ?, ?, ?, ?, ?,
             ?, NOW(), ?, ?)";

        $intentos = 0;
        do {
            $intentos++;
            $numero = self::siguienteNumero($db);
            $aleatorio = random_int(1000, 9999);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute([
                    $numero, $datos['carne'], $datos['fechadelpago'],
                    $datos['mesquepaga'], $datos['mensualidad'], $datos['inscripcion'], $datos['otro'], $datos['detalle'],
                    $datos['efectivo'], $datos['deposito'], $datos['nodeposito'], $datos['fechadep'],
                    $datos['cheque'], $datos['nocheque'], $datos['banco'], $datos['cuenta_destino'],
                    $datos['usuario'], $aleatorio, $datos['foto_deposito'],
                ]);
                return ['numero' => $numero, 'aleatorio' => $aleatorio];
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $intentos < 5) {
                    continue;
                }
                throw $e;
            }
        } while ($intentos < 5);

        throw new RuntimeException("No se pudo generar un número de recibo único.");
    }

    public static function obtenerPorNumero(PDO $db, int $numero): ?array {
        $stmt = $db->prepare("SELECT * FROM recibospfs WHERE numero = ?");
        $stmt->execute([$numero]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public static function estadisticasHoy(PDO $db): array {
        $stmt = $db->query(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = CURDATE() AND anulado = 0"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function estadisticasMes(PDO $db): array {
        $stmt = $db->query(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs
             WHERE YEAR(horaregistro) = YEAR(CURDATE()) AND MONTH(horaregistro) = MONTH(CURDATE()) AND anulado = 0"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Marca un recibo como anulado (nunca se borra ni se altera el detalle
    // original). Devuelve false si el número no existe o ya estaba anulado.
    public static function anular(PDO $db, int $numero, string $motivo, string $usuario): bool {
        $stmt = $db->prepare(
            "UPDATE recibospfs
             SET anulado = 1, motivo_anulacion = ?, anulado_por = ?, fecha_anulacion = NOW()
             WHERE numero = ? AND anulado = 0"
        );
        $stmt->execute([$motivo, $usuario, $numero]);
        return $stmt->rowCount() > 0;
    }

    // Campos que el formulario de Editar Recibo (panel de Administración)
    // puede modificar. Quedan fuera a propósito: `numero` y `aleatorio`
    // (identifican al recibo ya impreso y entregado), `usuario` y
    // `horaregistro` (son el rastro de quién cobró y cuándo, no un dato
    // corregible) y las columnas de anulación, que tienen su propio flujo.
    private static array $camposEditables = [
        'carne'        => 'Carné',
        'fechadelpago' => 'Fecha del pago',
        'mesquepaga'   => 'Mes que paga',
        'mensualidad'  => 'Mensualidad',
        'inscripcion'  => 'Inscripción',
        'otro'         => 'Otro',
        'detalle'      => 'Detalle',
        'efectivo'     => 'Efectivo',
        'deposito'     => 'Depósito',
        'nodeposito'   => 'No. de depósito',
        'fechadep'     => 'Fecha de depósito',
        'cheque'       => 'Cheque',
        'nocheque'     => 'No. de cheque',
        'banco'        => 'Banco',
        'cuenta_destino' => 'Cuenta que recibió la transferencia',
        'foto_deposito' => 'Foto del comprobante',
    ];

    private const CAMPOS_MONTO = ['mensualidad', 'inscripcion', 'otro', 'efectivo', 'deposito', 'cheque'];
    private const CAMPOS_ENTEROS = ['mesquepaga', 'nodeposito', 'nocheque'];

    public static function camposEditables(): array {
        return self::$camposEditables;
    }

    public static function etiquetaCampo(string $campo): string {
        return self::$camposEditables[$campo] ?? $campo;
    }

    // Normaliza un valor para compararlo y para guardarlo en la bitácora: la
    // BD devuelve los montos como "900" y el formulario los manda como
    // "900.00". Son el mismo valor y no deben quedar registrados como cambio.
    private static function normalizar(string $campo, $valor): string {
        if (in_array($campo, self::CAMPOS_MONTO, true)) {
            return number_format((float)$valor, 2, '.', '');
        }
        if (in_array($campo, self::CAMPOS_ENTEROS, true)) {
            return (string)(int)$valor;
        }
        return trim((string)$valor);
    }

    private static function esFechaValida(string $fecha): bool {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    public static function datosEdicionDesdePost(array $post): array {
        $deposito = (float)($post['deposito'] ?? 0);
        $fechadelpago = trim($post['fechadelpago'] ?? '');

        return [
            'carne'        => trim($post['carne'] ?? ''),
            'fechadelpago' => $fechadelpago,
            'mesquepaga'   => (int)($post['mesquepaga'] ?? 0),
            'mensualidad'  => (float)($post['mensualidad'] ?? 0),
            'inscripcion'  => (float)($post['inscripcion'] ?? 0),
            'otro'         => (float)($post['otro'] ?? 0),
            'detalle'      => trim($post['detalle'] ?? ''),
            'efectivo'     => (float)($post['efectivo'] ?? 0),
            'deposito'     => $deposito,
            'nodeposito'   => (int)($post['nodeposito'] ?? 0),
            // Sin depósito no hay fecha de depósito real que guardar, pero la
            // columna es NOT NULL: se replica la fecha del pago, igual que al
            // crear el recibo (ver RecibosPfsController::guardar).
            'fechadep'     => ($deposito > 0 && trim($post['fechadep'] ?? '') !== '')
                ? trim($post['fechadep'])
                : $fechadelpago,
            'cheque'       => (float)($post['cheque'] ?? 0),
            'nocheque'     => (int)($post['nocheque'] ?? 0),
            'banco'        => trim($post['banco'] ?? ''),
            'cuenta_destino' => $deposito > 0 ? (trim($post['cuenta_destino'] ?? '') ?: null) : null,
            // Resuelto por el controlador antes de llegar aquí (ver
            // AdminController::editarReciboGuardar): el nombre nuevo si se
            // subió una foto, o el que ya tenía el recibo si no.
            'foto_deposito' => trim($post['foto_deposito'] ?? '') ?: null,
        ];
    }

    // Mismas reglas de negocio que al crear un recibo
    // (RecibosPfsController::validar), adaptadas al formulario de edición:
    // aquí las fechas llegan como <input type="date"> en vez de día y mes
    // por nombre.
    public static function validarEdicion(array $post, PDO $db): array {
        $errores = [];

        $carne = trim($post['carne'] ?? '');
        if (!ctype_digit($carne)) {
            $errores[] = "El carné es obligatorio y debe ser numérico.";
        } elseif (!EstudiantesPfsModel::buscarPorCarnet($db, $carne)) {
            $errores[] = "No existe ningún estudiante registrado con el carné $carne.";
        }

        if (!self::esFechaValida(trim($post['fechadelpago'] ?? ''))) {
            $errores[] = "La fecha del pago no es una fecha válida.";
        }

        $mesquepaga = (int)($post['mesquepaga'] ?? 0);
        if ($mesquepaga < 1 || $mesquepaga > 12) {
            $errores[] = "El mes que paga no es válido.";
        }

        foreach (self::CAMPOS_MONTO as $campo) {
            $valor = $post[$campo] ?? '0';
            if ($valor === '' || !is_numeric($valor) || (float)$valor < 0) {
                $errores[] = "El campo '$campo' debe ser un monto numérico válido.";
            }
        }

        $totalCargos = round((float)($post['mensualidad'] ?? 0)
            + (float)($post['inscripcion'] ?? 0) + (float)($post['otro'] ?? 0), 2);
        $totalPagado = round((float)($post['efectivo'] ?? 0)
            + (float)($post['deposito'] ?? 0) + (float)($post['cheque'] ?? 0), 2);

        if ($totalCargos <= 0) {
            $errores[] = "Debe ingresar al menos un monto en mensualidad, inscripción u otro.";
        }
        if (abs($totalCargos - $totalPagado) > 0.01) {
            $errores[] = "El total a cobrar (Q " . number_format($totalCargos, 2)
                . ") no coincide con el total pagado (Q " . number_format($totalPagado, 2) . ").";
        }

        if (trim($post['detalle'] ?? '') === '') {
            $errores[] = "El detalle del pago es obligatorio.";
        }

        $deposito = (float)($post['deposito'] ?? 0);
        $cheque = (float)($post['cheque'] ?? 0);

        if ($deposito > 0 && trim($post['nodeposito'] ?? '') === '') {
            $errores[] = "Debe indicar el número de depósito.";
        }
        if ($deposito > 0 && !self::esFechaValida(trim($post['fechadep'] ?? ''))) {
            $errores[] = "Debe indicar una fecha de depósito válida.";
        }
        if ($cheque > 0 && trim($post['nocheque'] ?? '') === '') {
            $errores[] = "Debe indicar el número de cheque.";
        }
        if (($deposito > 0 || $cheque > 0) && trim($post['banco'] ?? '') === '') {
            $errores[] = "Debe indicar el banco de origen.";
        }
        if ($deposito > 0 && !array_key_exists(trim($post['cuenta_destino'] ?? ''), DepositosModel::cuentas())) {
            $errores[] = "Debe indicar a cuál cuenta propia entró la transferencia.";
        }

        if (trim($post['motivo'] ?? '') === '') {
            $errores[] = "Debe indicar el motivo de la edición.";
        }

        return $errores;
    }

    // Aplica los cambios y deja una fila por campo modificado en
    // recibospfs_ediciones. Devuelve la lista de cambios registrados, o []
    // si no hubo ninguno (o si el recibo fue anulado entre que se cargó el
    // formulario y se guardó).
    //
    // No hay transacción que cubra ambas escrituras: recibospfs es MyISAM
    // (legacy) y MyISAM no soporta transacciones. Se actualiza el recibo
    // primero y solo después se escribe la bitácora, para no dejar
    // registrado un cambio que la BD rechazó.
    public static function actualizar(PDO $db, int $numero, array $datos, string $motivo, string $usuario): array {
        $actual = self::obtenerPorNumero($db, $numero);
        if (!$actual || $actual['anulado']) {
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

        $asignaciones = [];
        $valores = [];
        foreach (array_keys(self::$camposEditables) as $campo) {
            $asignaciones[] = "`$campo` = ?";
            $valores[] = $datos[$campo];
        }
        $valores[] = $numero;

        $stmt = $db->prepare(
            "UPDATE recibospfs SET " . implode(', ', $asignaciones) . " WHERE numero = ? AND anulado = 0"
        );
        $stmt->execute($valores);

        if ($stmt->rowCount() === 0) {
            return [];
        }

        $log = $db->prepare(
            "INSERT INTO recibospfs_ediciones
                (numero, campo, valor_anterior, valor_nuevo, motivo, usuario)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($cambios as $c) {
            $log->execute([$numero, $c['campo'], $c['anterior'], $c['nuevo'], $motivo, $usuario]);
        }

        return $cambios;
    }

    public static function historialEdiciones(PDO $db, int $numero): array {
        $stmt = $db->prepare(
            "SELECT campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM recibospfs_ediciones WHERE numero = ? ORDER BY fecha DESC, id DESC"
        );
        $stmt->execute([$numero]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // A diferencia de historialEdiciones() (un recibo a la vez), esto es la
    // bitácora completa: se muestra en "Editar Recibo" cuando todavía no se
    // ha buscado ningún número, igual que DepositosModel::edicionesRecientes().
    public static function edicionesRecientes(PDO $db, int $limite = 50): array {
        $stmt = $db->prepare(
            "SELECT numero, campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
             FROM recibospfs_ediciones ORDER BY fecha DESC, id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Lista (no solo el agregado de anuladosDelAnio) de los recibos
    // anulados más recientes, sin importar el año — para "Anular Recibo"
    // cuando todavía no se ha buscado ningún número.
    public static function anuladosRecientes(PDO $db, int $limite = 50): array {
        $stmt = $db->prepare(
            "SELECT numero, carne, (efectivo + deposito + cheque) AS total,
                    motivo_anulacion, anulado_por, fecha_anulacion
             FROM recibospfs WHERE anulado = 1 ORDER BY fecha_anulacion DESC LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Detalle + resumen por concepto de un día específico (reemplazo de
    // cierrehoy_new.php). Se usa DATE(horaregistro) = :fecha con un
    // parámetro enlazado, en vez de interpolar la fecha en el SQL.
    public static function cierreDia(PDO $db, string $fecha): array {
        $stmtDetalle = $db->prepare(
            "SELECT * FROM recibospfs WHERE DATE(horaregistro) = ? ORDER BY horaregistro"
        );
        $stmtDetalle->execute([$fecha]);

        // Los recibos anulados se siguen mostrando en el detalle (para que
        // quede visible qué pasó ese día), pero no cuentan en las sumas.
        $stmtResumen = $db->prepare(
            "SELECT
                COALESCE(SUM(efectivo), 0) AS efectivo,
                COALESCE(SUM(deposito), 0) AS deposito,
                COALESCE(SUM(cheque), 0) AS cheque,
                COALESCE(SUM(inscripcion), 0) AS inscripcion,
                COALESCE(SUM(mensualidad), 0) AS mensualidad,
                COALESCE(SUM(otro), 0) AS otro,
                COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 0"
        );
        $stmtResumen->execute([$fecha]);

        // Cuenta y monto de los anulados del día, para explicar en el
        // resumen por qué el total no coincide con la suma cruda del detalle.
        $stmtAnulados = $db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE DATE(horaregistro) = ? AND anulado = 1"
        );
        $stmtAnulados->execute([$fecha]);

        return [
            'detalle' => $stmtDetalle->fetchAll(PDO::FETCH_ASSOC),
            'resumen' => $stmtResumen->fetch(PDO::FETCH_ASSOC),
            'anulados' => $stmtAnulados->fetch(PDO::FETCH_ASSOC),
        ];
    }

    // Totales por mes de un año (reemplazo de la tabla de 12 meses de
    // cierre_anio_moderno.php). El detalle día-por-día de un mes puntual
    // ya lo cubre reporte_recibospfs.php, así que no se duplica aquí.
    public static function cierreAnioPorMes(PDO $db, int $anio): array {
        $stmt = $db->prepare(
            "SELECT MONTH(horaregistro) AS mes,
                    COALESCE(SUM(efectivo), 0) AS efectivo,
                    COALESCE(SUM(deposito), 0) AS deposito,
                    COALESCE(SUM(cheque), 0) AS cheque
             FROM recibospfs
             WHERE YEAR(horaregistro) = ? AND anulado = 0
             GROUP BY MONTH(horaregistro)"
        );
        $stmt->execute([$anio]);

        $porMes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $porMes[(int)$fila['mes']] = $fila;
        }

        $resultado = [];
        for ($m = 1; $m <= 12; $m++) {
            $resultado[$m] = $porMes[$m] ?? ['efectivo' => 0, 'deposito' => 0, 'cheque' => 0];
        }
        return $resultado;
    }

    public static function anuladosDelAnio(PDO $db, int $anio): array {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(efectivo + deposito + cheque), 0) AS total
             FROM recibospfs WHERE YEAR(horaregistro) = ? AND anulado = 1"
        );
        $stmt->execute([$anio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Ingresos del año agrupados por diplomado, para el Dashboard. Un
    // recibo no sabe a qué curso pertenece directamente — se llega ahí vía
    // el carné del estudiante (carne = idestudiante). Los recibos de
    // estudiantes sin curso asignado (codcurso NULL, o un código que ya no
    // existe en diplomado-curso) se agrupan aparte en vez de perderse en
    // silencio: son un problema real de datos que vale la pena mostrar, no
    // ocultar.
    public static function ingresosPorDiplomado(PDO $db, int $anio): array {
        $stmt = $db->prepare(
            "SELECT d.nombre AS diplomado,
                    COALESCE(SUM(r.efectivo + r.deposito + r.cheque), 0) AS total,
                    COUNT(*) AS cantidad
             FROM recibospfs r
             JOIN estudiantespfs e ON e.idestudiante = r.carne
             LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
             WHERE r.anulado = 0 AND YEAR(r.horaregistro) = ?
             GROUP BY d.nombre
             ORDER BY total DESC"
        );
        $stmt->execute([$anio]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$fila) {
            $fila['diplomado'] = $fila['diplomado'] ?? 'Sin curso asignado';
        }
        unset($fila);
        return $filas;
    }

    // Graduación/Libros/Batas no tienen campo estructurado (igual
    // limitación que ConsultaPagosModel::reporteUnificado): se aproximan
    // por palabra clave sobre detalle, sumando `otro`. Un recibo que
    // mezcla el concepto con otra cosa en la misma línea ("MES DE
    // DICIEMBRE Y ACTO DE GRADUACION") puede sumar de más — es un
    // estimado para el dashboard, no una cifra contable exacta.
    public static function ingresosPorConceptosEspeciales(PDO $db, int $anio): array {
        $conceptos = ['Graduación' => 'GRADUA', 'Libros' => 'LIBRO', 'Batas' => 'BATA'];
        // COUNT(DISTINCT (carne+0)), no COUNT(*): un estudiante puede pagar
        // el mismo concepto en varios abonos (varios recibos), y "cantidad"
        // de recibos no responde "cuántos estudiantes son". (carne+0) en
        // vez de carne a secas, misma razón que en otros reportes: un
        // espacio/tab pegado en el carné no debe contar dos veces al mismo
        // estudiante.
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(otro), 0) AS total, COUNT(*) AS cantidad, COUNT(DISTINCT (carne + 0)) AS estudiantes
             FROM recibospfs
             WHERE anulado = 0 AND YEAR(horaregistro) = ? AND detalle LIKE ?"
        );

        $resultado = [];
        foreach ($conceptos as $etiqueta => $patron) {
            $stmt->execute([$anio, '%' . $patron . '%']);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $resultado[] = [
                'concepto' => $etiqueta,
                'total' => (float)$fila['total'],
                'cantidad' => (int)$fila['cantidad'],
                'estudiantes' => (int)$fila['estudiantes'],
            ];
        }
        return $resultado;
    }
}
?>
