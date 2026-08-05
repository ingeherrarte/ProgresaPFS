<?php
require_once "models/DepositosModel.php";
require_once "views/DepositosView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class DepositosController {

    public function handle(string $action) {
        Auth::requerirSesion();

        switch ($action) {
            case 'guardar':
                $this->guardar();
                break;

            case 'form':
            default:
                $this->form();
                break;
        }
    }

    private function form(array $errores = [], array $data = []) {
        $db = Conexion::conectar();
        $recientes = DepositosModel::obtenerRecientes($db);
        $mensaje = (empty($errores) && ($_GET['msg'] ?? '') === 'creado') ? 'Depósito guardado correctamente.' : null;
        DepositosView::mostrarFormulario($errores, $data, $recientes, $mensaje);
    }

    // Reemplaza a procesadeposito.php, que estaba completamente roto: llamaba
    // a cambiaf_a_mysql() sin que la función existiera (fatal error en
    // cualquier PHP >= 7) y nunca leía $_POST['fechadep'].
    private function validar(array $post): array {
        $errores = [];

        $nodeposito = trim($post['nodeposito'] ?? '');
        if ($nodeposito === '' || strlen($nodeposito) > 11) {
            $errores[] = "El número de depósito es obligatorio (máximo 11 caracteres).";
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['fechadep'] ?? '')) {
            $errores[] = "La fecha de depósito no es válida.";
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post['correspondiente'] ?? '')) {
            $errores[] = "La fecha a la que corresponde el depósito no es válida.";
        }

        $cuenta = trim($post['cuenta'] ?? '');
        if (!array_key_exists($cuenta, DepositosModel::cuentas())) {
            $errores[] = "Seleccione una cuenta válida.";
        }

        foreach (['efectivo', 'chpropio', 'chotrobanco'] as $campo) {
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

        return $errores;
    }

    private const CARPETA_BOLETAS = __DIR__ . "/../uploads/depositos";

    // Límite propio, independiente de upload_max_filesize/post_max_size del
    // php.ini del servidor (que puede ser mayor o menor que esto).
    private const TAMANO_MAXIMO_BOLETA = 2 * 1024 * 1024;

    // Tipos de imagen aceptados. Se detectan con getimagesize() sobre el
    // contenido real del archivo, no por la extensión ni el Content-Type que
    // manda el navegador (ambos falsificables), y el nombre final se genera
    // aquí en vez de reutilizar el nombre original subido.
    private const TIPOS_IMAGEN = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    // La foto es opcional. Devuelve [nombreGuardado, error]: si no se
    // adjuntó nada, ambos son null y el registro sigue sin foto.
    private function procesarFoto(array $archivo, string $nodeposito): array {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return [null, "La foto de la boleta supera el tamaño máximo permitido (2 MB)."];
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return [null, "No se pudo subir la foto de la boleta (máximo 2 MB)."];
        }
        if (($archivo['size'] ?? 0) > self::TAMANO_MAXIMO_BOLETA) {
            return [null, "La foto de la boleta supera el tamaño máximo permitido (2 MB)."];
        }

        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false || !isset(self::TIPOS_IMAGEN[$info[2]])) {
            return [null, "La foto de la boleta debe ser una imagen JPG, PNG o WEBP."];
        }

        if (!is_dir(self::CARPETA_BOLETAS)) {
            mkdir(self::CARPETA_BOLETAS, 0755, true);
        }

        $prefijo = preg_replace('/[^A-Za-z0-9_-]/', '', $nodeposito) ?: 'boleta';
        $nombre = $prefijo . '_' . bin2hex(random_bytes(8)) . '.' . self::TIPOS_IMAGEN[$info[2]];

        if (!move_uploaded_file($archivo['tmp_name'], self::CARPETA_BOLETAS . '/' . $nombre)) {
            return [null, "No se pudo guardar la foto de la boleta. Intente de nuevo."];
        }

        return [$nombre, null];
    }

    private function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: depositos.php");
            exit;
        }

        // Si el POST completo supera post_max_size, PHP vacía $_POST y
        // $_FILES sin marcar ningún error de archivo individual: sin este
        // chequeo, procesarFoto() interpretaría eso como "no se adjuntó
        // foto" y el depósito se guardaría descartando la boleta en
        // silencio, sin avisar al usuario del motivo real.
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->form(["El formulario supera el tamaño máximo permitido. Si adjuntó una foto, verifique que pese menos de 2 MB."], []);
            return;
        }

        $errores = $this->validar($_POST);

        [$fotoBoleta, $errorFoto] = $this->procesarFoto($_FILES['foto_boleta'] ?? [], trim($_POST['nodeposito'] ?? ''));
        if ($errorFoto) {
            $errores[] = $errorFoto;
        }

        if (!empty($errores)) {
            if ($fotoBoleta) {
                @unlink(self::CARPETA_BOLETAS . '/' . $fotoBoleta);
            }
            $this->form($errores, $_POST);
            return;
        }

        $cuenta = trim($_POST['cuenta']);
        $datos = [
            'nodeposito' => trim($_POST['nodeposito']),
            'fechadep' => $_POST['fechadep'],
            'cuenta' => $cuenta,
            'banco' => DepositosModel::bancoDeCuenta($cuenta),
            'correspondiente' => $_POST['correspondiente'],
            'efectivo' => (float)$_POST['efectivo'],
            'chpropio' => (float)$_POST['chpropio'],
            'chotrobanco' => (float)$_POST['chotrobanco'],
            'responsable' => trim($_POST['responsable']),
            'usuario' => Auth::usuarioActual(),
            'foto_boleta' => $fotoBoleta,
        ];

        $db = Conexion::conectar();
        $resultado = DepositosModel::crear($db, $datos);

        if ($resultado !== true && $fotoBoleta) {
            @unlink(self::CARPETA_BOLETAS . '/' . $fotoBoleta);
        }

        if ($resultado === 'duplicado') {
            $this->form(["Ya existe un depósito registrado con el número {$datos['nodeposito']}."], $_POST);
            return;
        }
        if ($resultado === false) {
            $this->form(["No se pudo guardar el depósito. Intente de nuevo."], $_POST);
            return;
        }

        header("Location: depositos.php?msg=creado");
        exit;
    }
}
?>
