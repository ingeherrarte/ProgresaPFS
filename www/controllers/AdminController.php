<?php
require_once "models/RecibosPfsModel.php";
require_once "models/UsuarioModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "models/BackupModel.php";
require_once "views/AdminView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../helpers/SubidaImagen.php";
require_once __DIR__ . "/../config/Conexion.php";

class AdminController {

    // Mismos límites y misma carpeta que RecibosPfsController::guardar():
    // la foto del comprobante de un recibo vive en un solo lugar sin
    // importar si se adjuntó al crearlo o al editarlo después.
    private const CARPETA_COMPROBANTES = __DIR__ . "/../uploads/recibos";
    private const TAMANO_MAXIMO_COMPROBANTE = 2 * 1024 * 1024;

    // Mismo listado y misma conversión que RecibosPfsController::mesNumero():
    // el formulario de Editar Recibo reutiliza los selects de Día/Mes del
    // pago del formulario de creación, así que necesita la misma lógica para
    // recomponer la fecha completa.
    private array $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private function mesNumero(string $nombre): int|false {
        return array_search(strtolower(trim($nombre)), $this->meses, true);
    }

    // A diferencia de RecibosPfsController::guardar() (que siempre asume el
    // año actual, correcto para un recibo nuevo), aquí el año es explícito:
    // se está editando un recibo que pudo haberse pagado en cualquier año
    // anterior, y asumir el año actual corrompería silenciosamente la fecha
    // de cualquier recibo histórico que no sea de este año.
    private function componerFechaPago(array $post): ?string {
        $dia = (int)($post['diapago'] ?? 0);
        $anio = (int)($post['anio_pago'] ?? 0);
        $mesNum = $this->mesNumero($post['mespago'] ?? '');

        if ($mesNum === false || $anio < 1900 || $anio > 2999 || !checkdate($mesNum, $dia, $anio)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $anio, $mesNum, $dia);
    }

    public function handle(string $action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'anular':
                Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);
                $this->anularBuscar();
                break;

            case 'anular_confirmar':
                Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);
                $this->anularConfirmar();
                break;

            case 'editar_estudiante':
                Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);
                $this->editarEstudianteBuscar();
                break;

            case 'editar_estudiante_guardar':
                Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);
                $this->editarEstudianteGuardar();
                break;

            // Editar un recibo altera un documento financiero ya impreso y
            // entregado, así que a diferencia de anular/editar estudiante
            // queda restringido al rol administrador.
            case 'editar_recibo':
                Auth::requerirRol([Auth::ROL_ADMINISTRADOR]);
                $this->editarReciboBuscar();
                break;

            case 'editar_recibo_guardar':
                Auth::requerirRol([Auth::ROL_ADMINISTRADOR]);
                $this->editarReciboGuardar();
                break;

            // Sin requerirRol(): disponible para cualquier usuario con
            // sesión, a diferencia del resto de acciones de este panel. Es
            // de solo lectura (nunca modifica datos), y se decidió así
            // deliberadamente para que un respaldo no dependa de que un
            // administrador en particular esté disponible para generarlo.
            case 'backup':
                AdminView::mostrarBackup();
                break;

            case 'backup_generar':
                $this->generarBackup();
                break;

            case 'form':
            default:
                AdminView::mostrarMenu();
                break;
        }
    }

    private function anularBuscar() {
        $numero = trim($_GET['numero'] ?? '');
        $recibo = null;
        $errores = [];

        if ($numero !== '') {
            if (!ctype_digit($numero)) {
                $errores[] = "Número de recibo inválido.";
            } else {
                $db = Conexion::conectar();
                $recibo = RecibosPfsModel::obtenerPorNumero($db, (int)$numero);
                if (!$recibo) {
                    $errores[] = "No existe ningún recibo con ese número.";
                } elseif ($recibo['anulado']) {
                    $errores[] = "Este recibo ya fue anulado el "
                        . date('d/m/Y H:i', strtotime($recibo['fecha_anulacion'])) . ".";
                }
            }
        }

        AdminView::mostrarAnular($numero, $recibo, $errores);
    }

    // La contraseña se vuelve a pedir aquí (re-autenticación) para que
    // anular un recibo no sea posible con solo dejar la sesión abierta;
    // tiene que ser la persona con la contraseña quien lo confirme.
    private function anularConfirmar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin.php?action=anular");
            exit;
        }

        $numero = (int)($_POST['numero'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $password = $_POST['password_actual'] ?? '';

        $db = Conexion::conectar();
        $recibo = RecibosPfsModel::obtenerPorNumero($db, $numero);

        if (!$recibo) {
            header("Location: admin.php?action=anular");
            exit;
        }

        $errores = [];
        if ($recibo['anulado']) {
            $errores[] = "Este recibo ya fue anulado.";
        }
        if ($motivo === '') {
            $errores[] = "Debe indicar el motivo de la anulación.";
        }
        if (!UsuarioModel::verificar(Auth::usuarioActual(), $password)) {
            $errores[] = "La contraseña no es correcta.";
        }

        if (!empty($errores)) {
            AdminView::mostrarAnular((string)$numero, $recibo, $errores);
            return;
        }

        RecibosPfsModel::anular($db, $numero, $motivo, Auth::usuarioActual());

        header("Location: recibospfs.php?action=ver&numero=$numero");
        exit;
    }

    private function editarEstudianteBuscar() {
        $carnet = trim($_GET['carnet'] ?? '');
        $db = Conexion::conectar();
        $estudiante = null;
        $errores = [];

        if ($carnet !== '') {
            if (!ctype_digit($carnet)) {
                $errores[] = "Carné inválido.";
            } else {
                $estudiante = EstudiantesPfsModel::obtenerPorId($db, (int)$carnet);
                if (!$estudiante) {
                    $errores[] = "No existe ningún estudiante con ese carné.";
                }
            }
        }

        $mensaje = ($estudiante && ($_GET['msg'] ?? '') === 'actualizado')
            ? "Los datos del estudiante se actualizaron correctamente."
            : null;

        AdminView::mostrarEditarEstudiante($carnet, $estudiante, EstudiantesPfsModel::obtenerCursos($db), $errores, $mensaje);
    }

    // Igual que anularConfirmar(): la contraseña se vuelve a pedir aquí para
    // que editar un estudiante no sea posible con solo dejar la sesión
    // abierta, y se deja constancia de quién hizo el cambio.
    private function editarEstudianteGuardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin.php?action=editar_estudiante");
            exit;
        }

        $carnet = (int)($_POST['carnet'] ?? 0);
        $password = $_POST['password_actual'] ?? '';

        $db = Conexion::conectar();
        $estudiante = EstudiantesPfsModel::obtenerPorId($db, $carnet);

        if (!$estudiante) {
            header("Location: admin.php?action=editar_estudiante");
            exit;
        }

        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        $errores = EstudiantesPfsModel::validar($_POST, $cursos);
        if (!UsuarioModel::verificar(Auth::usuarioActual(), $password)) {
            $errores[] = "La contraseña no es correcta.";
        }

        if (!empty($errores)) {
            AdminView::mostrarEditarEstudiante((string)$carnet, array_merge($estudiante, $_POST), $cursos, $errores);
            return;
        }

        EstudiantesPfsModel::actualizar($db, $carnet, EstudiantesPfsModel::datosDesdePost($_POST), Auth::usuarioActual());

        header("Location: admin.php?action=editar_estudiante&carnet=$carnet&msg=actualizado");
        exit;
    }

    private function editarReciboBuscar() {
        $numero = trim($_GET['numero'] ?? '');
        $db = Conexion::conectar();
        $recibo = null;
        $historial = [];
        $errores = [];

        if ($numero !== '') {
            if (!ctype_digit($numero)) {
                $errores[] = "Número de recibo inválido.";
            } else {
                $recibo = RecibosPfsModel::obtenerPorNumero($db, (int)$numero);
                if (!$recibo) {
                    $errores[] = "No existe ningún recibo con ese número.";
                } else {
                    $historial = RecibosPfsModel::historialEdiciones($db, (int)$numero);
                    // Un recibo anulado ya no representa un cobro vigente:
                    // editarlo solo confundiría los cierres de caja.
                    if ($recibo['anulado']) {
                        $errores[] = "Este recibo está anulado y no puede editarse.";
                    }
                }
            }
        }

        $mensaje = null;
        if ($recibo) {
            if (($_GET['msg'] ?? '') === 'actualizado') {
                $cambios = max(1, (int)($_GET['cambios'] ?? 1));
                $mensaje = "El recibo se actualizó correctamente ($cambios campo(s) modificado(s)).";
            } elseif (($_GET['msg'] ?? '') === 'sin_cambios') {
                $mensaje = "No se modificó ningún campo: los datos enviados son idénticos a los guardados.";
            }
        }

        AdminView::mostrarEditarRecibo($numero, $recibo, $historial, $errores, $mensaje);
    }

    // Igual que anularConfirmar() y editarEstudianteGuardar(): la contraseña
    // se vuelve a pedir para que editar un recibo no sea posible con solo
    // dejar la sesión abierta. El motivo es obligatorio y queda en la
    // bitácora junto al valor anterior de cada campo modificado.
    private function editarReciboGuardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin.php?action=editar_recibo");
            exit;
        }

        // Igual que RecibosPfsController::guardar(): si el POST completo
        // supera post_max_size (comprobante demasiado pesado sumado al
        // resto del formulario), PHP vacía $_POST/$_FILES sin marcar ningún
        // error individual. En ese caso ni siquiera se recupera el número
        // de recibo que se estaba editando, así que se vuelve a la búsqueda.
        if (SubidaImagen::postTruncado()) {
            header("Location: admin.php?action=editar_recibo");
            exit;
        }

        $numero = (int)($_POST['numero'] ?? 0);
        $password = $_POST['password_actual'] ?? '';

        $db = Conexion::conectar();
        $recibo = RecibosPfsModel::obtenerPorNumero($db, $numero);

        if (!$recibo) {
            header("Location: admin.php?action=editar_recibo");
            exit;
        }

        // El formulario reutiliza los selects de Día/Mes del pago del
        // formulario de creación (más un Año explícito, ver
        // componerFechaPago), en vez de un único <input type="date">. Se
        // compone aquí una copia de $_POST para validar/guardar sin tocar el
        // $_POST original: si hay errores, se vuelve a mostrar el formulario
        // con exactamente lo que el usuario tecleó en esos tres selects.
        $post = $_POST;
        $post['fechadelpago'] = $this->componerFechaPago($_POST) ?? '';

        // La foto es opcional al editar: si no se adjunta una nueva, se
        // conserva la que el recibo ya tenía (guardar() devuelve [null,null]
        // cuando no llega archivo). Solo se valida/descarta aquí; a qué
        // nombre final apunta el registro se decide más abajo, ya con el
        // resultado de la validación del resto de campos.
        [$fotoNueva, $errorFoto] = SubidaImagen::guardar(
            $_FILES['foto_deposito'] ?? [],
            self::CARPETA_COMPROBANTES,
            trim($post['carne'] ?? '') ?: 'comprobante',
            self::TAMANO_MAXIMO_COMPROBANTE,
            $db,
            'recibos',
            trim($post['nodeposito'] ?? '')
        );
        $post['foto_deposito'] = $fotoNueva ?? $recibo['foto_deposito'];

        if ($recibo['anulado']) {
            $errores = ["Este recibo está anulado y no puede editarse."];
        } else {
            $errores = RecibosPfsModel::validarEdicion($post, $db);
        }
        if ($errorFoto) {
            $errores[] = $errorFoto;
        }
        if (!UsuarioModel::verificar(Auth::usuarioActual(), $password)) {
            $errores[] = "La contraseña no es correcta.";
        }

        if (!empty($errores)) {
            if ($fotoNueva) {
                SubidaImagen::eliminar(self::CARPETA_COMPROBANTES, $fotoNueva, $db);
            }
            AdminView::mostrarEditarRecibo(
                (string)$numero,
                array_merge($recibo, $_POST),
                RecibosPfsModel::historialEdiciones($db, $numero),
                $errores
            );
            return;
        }

        $cambios = RecibosPfsModel::actualizar(
            $db,
            $numero,
            RecibosPfsModel::datosEdicionDesdePost($post),
            trim($_POST['motivo']),
            Auth::usuarioActual()
        );

        // La foto vieja solo se descarta si de verdad se reemplazó por una
        // distinta: si no se subió nada nuevo, $fotoNueva es null y no hay
        // nada que borrar aquí.
        if ($fotoNueva && $recibo['foto_deposito'] && $recibo['foto_deposito'] !== $fotoNueva) {
            SubidaImagen::eliminar(self::CARPETA_COMPROBANTES, $recibo['foto_deposito'], $db);
        }

        $msg = empty($cambios) ? 'sin_cambios' : 'actualizado';
        header("Location: admin.php?action=editar_recibo&numero=$numero&msg=$msg&cambios=" . count($cambios));
        exit;
    }

    private function generarBackup() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin.php?action=backup");
            exit;
        }

        $resultado = BackupModel::generar();

        if (!$resultado['ok']) {
            AdminView::mostrarBackup($resultado['error']);
            return;
        }

        $subida = BackupModel::subirANube($resultado['archivo']);

        if (!$subida['ok']) {
            AdminView::mostrarBackup($subida['error']);
            return;
        }

        AdminView::mostrarBackup(null, "Respaldo generado y subido a la nube correctamente.");
    }
}
?>
