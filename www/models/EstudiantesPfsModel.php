<?php
require_once __DIR__ . "/../config/Conexion.php";

class EstudiantesPfsModel {

    // El buscador legacy usaba 3 = 'Miércoles', pero el alta de estudiantes
    // (ingresoestudiantepfs.php) define 3 = 'Diario'; se usa la semántica
    // del formulario de alta, que es la fuente real de los datos.
    private static array $planes = [1 => 'Sábado', 2 => 'Domingo', 3 => 'Diario'];
    private static array $jornadas = [1 => 'Matutina', 2 => 'Vespertina'];

    public static function nombrePlan($plan): string {
        return self::$planes[$plan] ?? 'No definido';
    }

    public static function nombreJornada($jornada): string {
        return self::$jornadas[$jornada] ?? 'No definida';
    }

    public static function planes(): array {
        return self::$planes;
    }

    public static function jornadas(): array {
        return self::$jornadas;
    }

    // Los cursos se leen de la tabla real en vez de repetir un arreglo fijo:
    // el buscador legacy tenía un curso con la Ñ mal codificada en un
    // switch/case hardcodeado que nunca hacía match contra el valor real
    // enviado por el <select>, dejando el estudiante sin curso asignado.
    public static function obtenerCursos(PDO $db): array {
        $stmt = $db->query("SELECT id, nombre FROM `diplomado-curso` ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function obtenerPorId(PDO $db, int $id): ?array {
        $stmt = $db->prepare("SELECT * FROM estudiantespfs WHERE idestudiante = ?");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public static function buscarPorCarnet(PDO $db, string $carnet): ?array {
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.codcurso, e.activo,
                       d.nombre AS nombrecurso
                FROM estudiantespfs e
                LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
                WHERE e.idestudiante = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$carnet]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    // Búsqueda server-side por nombre/apellidos, paginada. Devuelve
    // ['total' => N, 'filas' => [...]] para que el controlador arme la
    // paginación sin tener que traer todo el resultado a memoria.
    public static function buscarPorNombre(PDO $db, string $termino, int $pagina, int $porPagina): array {
        $comodin = '%' . $termino . '%';

        $stmtTotal = $db->prepare(
            "SELECT COUNT(*) FROM estudiantespfs WHERE CONCAT(nombre, ' ', apellidos) LIKE ?"
        );
        $stmtTotal->execute([$comodin]);
        $total = (int)$stmtTotal->fetchColumn();

        $offset = max(0, ($pagina - 1) * $porPagina);
        $sql = "SELECT e.idestudiante, e.nombre, e.apellidos, e.plan, e.jornada,
                       e.telefonomovil, e.activo, d.nombre AS nombrecurso
                FROM estudiantespfs e
                LEFT JOIN `diplomado-curso` d ON d.id = e.codcurso
                WHERE CONCAT(e.nombre, ' ', e.apellidos) LIKE ?
                ORDER BY e.idestudiante DESC
                LIMIT ? OFFSET ?";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $comodin, PDO::PARAM_STR);
        $stmt->bindValue(2, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['total' => $total, 'filas' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    private static function siguienteId(PDO $db): int {
        return (int)$db->query("SELECT COALESCE(MAX(idestudiante),0)+1 FROM estudiantespfs")->fetchColumn();
    }

    // El correlativo se calcula con MAX(idestudiante)+1, igual que en
    // recibos; ante una colisión de PK se reintenta con un nuevo
    // correlativo en vez de fallar (o, peor, insertar silenciosamente
    // nada como hacía ingresopfs2.php, que nunca revisaba el resultado).
    public static function crear(PDO $db, array $datos): int {
        $sql = "INSERT INTO estudiantespfs (
            idestudiante, nombre, apellidos, nacimiento, codcurso, plan, jornada,
            dpi, cedula, direccion, email, telefonomovil, telefonocasa, telefonotrabajo,
            ultimoanio, establecimiento,
            pnombre, papellidos, pcedula, ptelefono, ptrabajo, ptelefonotrabajo, pdirecciont,
            mnombre, mapellidos, mcedula, mtelefono, mtrabajo, mtelefonotrabajo, mdirecciont,
            activo, fechainscripcion, horaregistro, usuario, enteradopor, observacion
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            1, CURDATE(), NOW(), ?, ?, ?
        )";

        $intentos = 0;
        do {
            $intentos++;
            $id = self::siguienteId($db);
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute([
                    $id, $datos['nombre'], $datos['apellidos'], $datos['nacimiento'], $datos['codcurso'], $datos['plan'], $datos['jornada'],
                    $datos['dpi'], $datos['cedula'], $datos['direccion'], $datos['email'], $datos['telefonomovil'], $datos['telefonocasa'], $datos['telefonotrabajo'],
                    $datos['ultimoanio'], $datos['establecimiento'],
                    $datos['pnombre'], $datos['papellidos'], $datos['pcedula'], $datos['ptelefono'], $datos['ptrabajo'], $datos['ptelefonotrabajo'], $datos['pdirecciont'],
                    $datos['mnombre'], $datos['mapellidos'], $datos['mcedula'], $datos['mtelefono'], $datos['mtrabajo'], $datos['mtelefonotrabajo'], $datos['mdirecciont'],
                    $datos['usuario'], $datos['enteradopor'], $datos['observacion'],
                ]);
                return $id;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000' && $intentos < 5) {
                    continue;
                }
                throw $e;
            }
        } while ($intentos < 5);

        throw new RuntimeException("No se pudo generar un carné único.");
    }

    // Actualiza los mismos campos editables del alta (todo menos carné,
    // activo, fecha de inscripción y el usuario que registró originalmente).
    // Se deja constancia de quién editó y cuándo, igual que en la anulación
    // de recibos.
    public static function actualizar(PDO $db, int $id, array $datos, string $usuario): void {
        $sql = "UPDATE estudiantespfs SET
            nombre = ?, apellidos = ?, nacimiento = ?, codcurso = ?, plan = ?, jornada = ?,
            dpi = ?, cedula = ?, direccion = ?, email = ?, telefonomovil = ?, telefonocasa = ?, telefonotrabajo = ?,
            ultimoanio = ?, establecimiento = ?,
            pnombre = ?, papellidos = ?, pcedula = ?, ptelefono = ?, ptrabajo = ?, ptelefonotrabajo = ?, pdirecciont = ?,
            mnombre = ?, mapellidos = ?, mcedula = ?, mtelefono = ?, mtrabajo = ?, mtelefonotrabajo = ?, mdirecciont = ?,
            enteradopor = ?, observacion = ?,
            editado_por = ?, fecha_edicion = NOW()
            WHERE idestudiante = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $datos['nombre'], $datos['apellidos'], $datos['nacimiento'], $datos['codcurso'], $datos['plan'], $datos['jornada'],
            $datos['dpi'], $datos['cedula'], $datos['direccion'], $datos['email'], $datos['telefonomovil'], $datos['telefonocasa'], $datos['telefonotrabajo'],
            $datos['ultimoanio'], $datos['establecimiento'],
            $datos['pnombre'], $datos['papellidos'], $datos['pcedula'], $datos['ptelefono'], $datos['ptrabajo'], $datos['ptelefonotrabajo'], $datos['pdirecciont'],
            $datos['mnombre'], $datos['mapellidos'], $datos['mcedula'], $datos['mtelefono'], $datos['mtrabajo'], $datos['mtelefonotrabajo'], $datos['mdirecciont'],
            $datos['enteradopor'], $datos['observacion'],
            $usuario, $id,
        ]);
    }

    // Toda la validación vive aquí (y no en el controlador) para que la
    // compartan el alta y la edición de estudiantes. El <select> de curso
    // viene de la tabla real (ver obtenerCursos), así que el valor recibido
    // solo puede ser un id que ya existe en esa lista.
    public static function validar(array $post, array $cursos): array {
        $errores = [];

        if (trim($post['nombre'] ?? '') === '') {
            $errores[] = "El nombre es obligatorio.";
        }
        if (trim($post['apellidos'] ?? '') === '') {
            $errores[] = "Los apellidos son obligatorios.";
        }

        $nacimiento = trim($post['nacimiento'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nacimiento, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            $errores[] = "La fecha de nacimiento no es válida.";
        }

        $codcurso = (int)($post['codcurso'] ?? 0);
        if (!array_key_exists($codcurso, $cursos)) {
            $errores[] = "Seleccione un curso válido.";
        }

        $plan = (int)($post['plan'] ?? 0);
        if (!array_key_exists($plan, self::planes())) {
            $errores[] = "Seleccione un plan/día válido.";
        }

        $jornada = (int)($post['jornada'] ?? 0);
        if (!array_key_exists($jornada, self::jornadas())) {
            $errores[] = "Seleccione una jornada válida.";
        }

        $dpi = trim($post['dpi'] ?? '');
        if ($dpi !== '' && !preg_match('/^\d{13}$/', $dpi)) {
            $errores[] = "El DPI debe tener 13 dígitos.";
        }

        $email = trim($post['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El email no es válido.";
        }

        foreach (['telefonomovil', 'telefonocasa', 'telefonotrabajo', 'ptelefono', 'ptelefonotrabajo', 'mtelefono', 'mtelefonotrabajo'] as $campoTel) {
            $valor = trim($post[$campoTel] ?? '');
            if ($valor !== '' && !preg_match('/^\d{8}$/', $valor)) {
                $errores[] = "El campo teléfono ($campoTel) debe tener 8 dígitos.";
            }
        }

        $ultimoanio = trim($post['ultimoanio'] ?? '');
        if ($ultimoanio !== '' && (!preg_match('/^\d{4}$/', $ultimoanio) || (int)$ultimoanio < 1980 || (int)$ultimoanio > (int)date('Y') + 1)) {
            $errores[] = "El último año cursado no es válido.";
        }

        if (trim($post['enteradopor'] ?? '') === '') {
            $errores[] = "Seleccione cómo se enteró del centro.";
        }

        return $errores;
    }

    // Arma el arreglo de columnas editables a partir de $_POST, compartido
    // entre el alta (que añade 'usuario' aparte) y la edición.
    public static function datosDesdePost(array $post): array {
        $ultimoanio = trim($post['ultimoanio'] ?? '');

        return [
            'nombre' => trim($post['nombre']),
            'apellidos' => trim($post['apellidos']),
            'nacimiento' => $post['nacimiento'],
            'codcurso' => (int)$post['codcurso'],
            'plan' => (int)$post['plan'],
            'jornada' => (int)$post['jornada'],
            'dpi' => trim($post['dpi'] ?? ''),
            'cedula' => trim($post['cedula'] ?? ''),
            'direccion' => trim($post['direccion'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'telefonomovil' => trim($post['telefonomovil'] ?? ''),
            'telefonocasa' => trim($post['telefonocasa'] ?? ''),
            'telefonotrabajo' => trim($post['telefonotrabajo'] ?? ''),
            'ultimoanio' => $ultimoanio !== '' ? $ultimoanio : '0000',
            'establecimiento' => trim($post['establecimiento'] ?? ''),
            'pnombre' => trim($post['pnombre'] ?? ''),
            'papellidos' => trim($post['papellidos'] ?? ''),
            'pcedula' => trim($post['pcedula'] ?? ''),
            'ptelefono' => trim($post['ptelefono'] ?? ''),
            'ptrabajo' => trim($post['ptrabajo'] ?? ''),
            'ptelefonotrabajo' => trim($post['ptelefonotrabajo'] ?? ''),
            'pdirecciont' => trim($post['pdirecciont'] ?? ''),
            'mnombre' => trim($post['mnombre'] ?? ''),
            'mapellidos' => trim($post['mapellidos'] ?? ''),
            'mcedula' => trim($post['mcedula'] ?? ''),
            'mtelefono' => trim($post['mtelefono'] ?? ''),
            'mtrabajo' => trim($post['mtrabajo'] ?? ''),
            'mtelefonotrabajo' => trim($post['mtelefonotrabajo'] ?? ''),
            'mdirecciont' => trim($post['mdirecciont'] ?? ''),
            'enteradopor' => trim($post['enteradopor']),
            'observacion' => trim($post['observacion'] ?? ''),
        ];
    }

    public static function totalActivos(PDO $db): int {
        return (int)$db->query("SELECT COUNT(*) FROM estudiantespfs WHERE activo = 1")->fetchColumn();
    }

    public static function nuevosEsteMes(PDO $db): int {
        return (int)$db->query(
            "SELECT COUNT(*) FROM estudiantespfs
             WHERE YEAR(horaregistro) = YEAR(CURDATE()) AND MONTH(horaregistro) = MONTH(CURDATE())"
        )->fetchColumn();
    }
}
?>
