<?php
require_once "models/ConciliacionModel.php";
require_once "models/DepositosModel.php";
require_once "views/ConciliacionView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class ConciliacionController {

    private const MAX_FILAS = 5000;
    private const MAX_TAMANO_ARCHIVO = 3 * 1024 * 1024;

    public function handle(string $action) {
        // Disponible para editor y administrador (igual que anular recibos
        // o editar estudiantes); solo el administrador puede verificar.
        Auth::requerirRol([Auth::ROL_EDITOR, Auth::ROL_ADMINISTRADOR]);

        switch ($action) {
            case 'subir':
                $this->subir();
                break;
            case 'confirmarImport':
                $this->confirmarImport();
                break;
            case 'verificar':
                $this->verificar();
                break;
            case 'form':
            default:
                $this->form();
                break;
        }
    }

    // Default: 1 de enero del año actual, para no arrastrar años de
    // histórico que no hace falta conciliar. El admin puede ampliarlo o
    // acotarlo desde el filtro de la vista.
    private function fechaDesde(?string $valor): string {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$valor) ? $valor : date('Y-01-01');
    }

    private function form(?string $mensaje = null, ?string $error = null, ?string $desde = null) {
        $desde = $this->fechaDesde($desde ?? ($_GET['desde'] ?? null));
        $db = Conexion::conectar();
        $grupos = ConciliacionModel::sugerencias($db, $desde);
        ConciliacionView::mostrarPrincipal($grupos, $mensaje, $error, $desde);
    }

    // Detecta separador (',' o ';') por frecuencia en la primera línea, y
    // normaliza a UTF-8 (los exports de Excel en Windows suelen venir en
    // Latin-1, lo que rompe acentos/ñ en la columna de referencia).
    private function parsearCsv(string $contenido): array {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido); // BOM
        $codificacion = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($codificacion && $codificacion !== 'UTF-8') {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', $codificacion);
        }

        $primeraLinea = strtok($contenido, "\n") ?: '';
        $separador = (substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',')) ? ';' : ',';

        $filas = [];
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $contenido);
        rewind($fh);
        while (($fila = fgetcsv($fh, 0, $separador)) !== false) {
            if (count($fila) === 1 && trim((string)$fila[0]) === '') {
                continue; // línea en blanco
            }
            $filas[] = array_map('trim', $fila);
        }
        fclose($fh);
        return $filas;
    }

    private static function indiceColumnaDesdeRef(string $ref): int {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letras = $m[1] ?? 'A';
        $indice = 0;
        foreach (str_split($letras) as $ch) {
            $indice = $indice * 26 + (ord($ch) - 64);
        }
        return $indice - 1;
    }

    // Un .xlsx es un ZIP con XML adentro: no hace falta PhpSpreadsheet ni
    // Composer, solo ext-zip (agregada en php-config-qa/Dockerfile) y
    // SimpleXML (viene por defecto en la imagen php:8.2-apache). Solo lee
    // la primera hoja, que es lo único que necesita un estado de cuenta.
    private function parsearXlsx(string $rutaArchivo): array {
        $zip = new ZipArchive();
        if ($zip->open($rutaArchivo) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo .xlsx (¿está dañado o protegido con contraseña?).");
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = new SimpleXMLElement($ssXml);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } else {
                    $texto = '';
                    foreach ($si->r as $r) {
                        $texto .= (string)$r->t;
                    }
                    $sharedStrings[] = $texto;
                }
            }
        }

        // Resuelve la ruta real de la primera hoja vía workbook.xml + sus
        // relaciones, en vez de asumir que siempre se llama sheet1.xml.
        $rutaHoja = 'xl/worksheets/sheet1.xml';
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml !== false && $relsXml !== false) {
            $wb = new SimpleXMLElement($workbookXml);
            $rNs = $wb->getNamespaces(true)['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $rId = (string)($wb->sheets->sheet[0]->attributes($rNs)['id'] ?? '');
            if ($rId !== '') {
                $rels = new SimpleXMLElement($relsXml);
                foreach ($rels->Relationship as $rel) {
                    if ((string)$rel['Id'] === $rId) {
                        $rutaHoja = 'xl/' . ltrim((string)$rel['Target'], '/');
                        break;
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName($rutaHoja);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException("No se pudo leer la primera hoja del archivo .xlsx.");
        }

        $sheet = new SimpleXMLElement($sheetXml);
        $filas = [];
        foreach ($sheet->sheetData->row as $row) {
            $celdas = [];
            $colIndex = 0;
            foreach ($row->c as $c) {
                $colActual = self::indiceColumnaDesdeRef((string)$c['r']);
                while ($colIndex < $colActual) {
                    $celdas[] = '';
                    $colIndex++;
                }
                $tipo = (string)$c['t'];
                if ($tipo === 's') {
                    $valor = $sharedStrings[(int)$c->v] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string)($c->is->t ?? '');
                } else {
                    $valor = (string)$c->v;
                }
                $celdas[] = trim($valor);
                $colIndex++;
            }
            if (!empty(array_filter($celdas, fn($v) => $v !== ''))) {
                $filas[] = $celdas;
            }
        }
        return $filas;
    }

    // Muchos portales de banca en línea generan un archivo con extensión
    // .xls que en realidad es una tabla HTML (no un binario Excel real).
    // Reutiliza la misma normalización de codificación que parsearCsv.
    private function parsearHtmlTabla(string $contenido): array {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $codificacion = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($codificacion && $codificacion !== 'UTF-8') {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', $codificacion);
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $contenido);
        libxml_clear_errors();

        $tabla = $doc->getElementsByTagName('table')->item(0);
        if ($tabla === null) {
            throw new RuntimeException("El archivo no contiene ninguna tabla reconocible.");
        }

        $filas = [];
        foreach ($tabla->getElementsByTagName('tr') as $tr) {
            $celdas = [];
            foreach ($tr->childNodes as $nodo) {
                if (in_array($nodo->nodeName, ['td', 'th'], true)) {
                    $celdas[] = trim(preg_replace('/\s+/', ' ', $nodo->textContent));
                }
            }
            if (!empty($celdas)) {
                $filas[] = $celdas;
            }
        }
        return $filas;
    }

    private function subir() {
        $desde = $this->fechaDesde($_POST['desde'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['archivo']['tmp_name'])) {
            $this->form(null, "Selecciona un archivo CSV.", $desde);
            return;
        }
        if ($_FILES['archivo']['size'] > self::MAX_TAMANO_ARCHIVO) {
            $this->form(null, "El archivo supera el tamaño máximo permitido (3 MB).", $desde);
            return;
        }

        // No confiamos en la extensión del nombre: varios bancos generan
        // archivos ".xls" que en realidad son tablas HTML, o ".csv" que
        // vienen con otra codificación. Se detecta por el contenido real
        // (magic bytes / sniffing de las primeras líneas).
        $rutaTemporal = $_FILES['archivo']['tmp_name'];
        $cabecera = file_get_contents($rutaTemporal, false, null, 0, 8);

        try {
            if (substr($cabecera, 0, 2) === 'PK') {
                // ZIP real: .xlsx
                $filas = $this->parsearXlsx($rutaTemporal);
            } elseif (substr($cabecera, 0, 4) === "\xD0\xCF\x11\xE0") {
                // OLE2 real: .xls binario legacy (Excel 97-2003), no soportado
                $this->form(null, "El archivo es un .xls binario real (Excel 97-2003), que no se puede leer directamente. Ábrelo en Excel y guárdalo como .xlsx o CSV.", $desde);
                return;
            } else {
                $contenido = file_get_contents($rutaTemporal);
                $muestra = ltrim($contenido);
                if (stripos($muestra, '<table') !== false || stripos($muestra, '<html') !== false) {
                    // Típico de bancos: exportan HTML con extensión .xls
                    $filas = $this->parsearHtmlTabla($contenido);
                } else {
                    $filas = $this->parsearCsv($contenido);
                }
            }
        } catch (Throwable $e) {
            $this->form(null, "No se pudo leer el archivo: " . $e->getMessage(), $desde);
            return;
        }

        if (empty($filas)) {
            $this->form(null, "El archivo está vacío o no se pudo leer como CSV.", $desde);
            return;
        }
        if (count($filas) > self::MAX_FILAS) {
            $this->form(null, "El archivo tiene demasiadas filas (máximo " . self::MAX_FILAS . "). Divídelo y sube por partes.", $desde);
            return;
        }

        $_SESSION['conciliacion_csv'] = $filas;
        ConciliacionView::mostrarMapeo($filas, $desde);
    }

    private function parsearMonto(string $valor, string $formato): ?float {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if ($formato === 'coma_decimal') {
            $valor = str_replace('.', '', $valor);   // separador de miles
            $valor = str_replace(',', '.', $valor);  // separador decimal
        } else {
            $valor = str_replace(',', '', $valor);   // separador de miles
        }
        return is_numeric($valor) ? (float)$valor : null;
    }

    private function parsearFecha(string $valor, string $formato): ?string {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if ($formato === 'iso') {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null;
        }
        if ($formato === 'excel_serial') {
            // Excel guarda las fechas como días desde 1899-12-30 (no texto).
            if (!is_numeric($valor)) {
                return null;
            }
            $timestamp = ((float)$valor - 25569) * 86400;
            return gmdate('Y-m-d', (int)$timestamp);
        }
        // dd/mm/yyyy o mm/dd/yyyy, admite '/' o '-' como separador
        if (!preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $valor, $m)) {
            return null;
        }
        [$_, $a, $b, $anio] = $m;
        [$dia, $mes] = $formato === 'dmy' ? [$a, $b] : [$b, $a];
        if ((int)$mes < 1 || (int)$mes > 12 || (int)$dia < 1 || (int)$dia > 31) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    private function confirmarImport() {
        $desde = $this->fechaDesde($_POST['desde'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['conciliacion_csv'])) {
            $this->form(null, "La sesión de importación expiró, sube el archivo de nuevo.", $desde);
            return;
        }

        $filas = $_SESSION['conciliacion_csv'];
        // 1-indexado como se muestra en la vista previa ("Fila #"); algunos
        // bancos (BANRURAL) traen varias filas de membrete/metadatos antes
        // de los títulos de columna, así que no basta con saltar solo 1.
        $filaInicioDatos = max(1, (int)($_POST['fila_inicio_datos'] ?? 1));
        $colFecha = (int)($_POST['col_fecha'] ?? -1);
        $colMonto = (int)($_POST['col_monto'] ?? -1);
        $colReferencia = $_POST['col_referencia'] ?? '';
        $formatoFecha = $_POST['formato_fecha'] ?? 'dmy';
        $formatoMonto = $_POST['formato_monto'] ?? 'punto_decimal';
        $cuenta = trim($_POST['cuenta'] ?? '');

        if (!array_key_exists($cuenta, DepositosModel::cuentas())) {
            unset($_SESSION['conciliacion_csv']);
            $this->form(null, "Selecciona una cuenta bancaria válida.", $desde);
            return;
        }

        $inicio = $filaInicioDatos - 1; // índice 0-based del array $filas
        $lineas = [];
        $filasConError = 0;

        for ($i = $inicio; $i < count($filas); $i++) {
            $fila = $filas[$i];
            $fecha = $this->parsearFecha($fila[$colFecha] ?? '', $formatoFecha);
            $monto = $this->parsearMonto($fila[$colMonto] ?? '', $formatoMonto);
            if ($fecha === null || $monto === null || $monto <= 0) {
                $filasConError++;
                continue;
            }
            $referencia = ($colReferencia !== '' && isset($fila[(int)$colReferencia]))
                ? mb_substr($fila[(int)$colReferencia], 0, 255)
                : null;
            $lineas[] = ['fecha' => $fecha, 'monto' => $monto, 'referencia' => $referencia];
        }

        unset($_SESSION['conciliacion_csv']);

        if (empty($lineas)) {
            $this->form(null, "No se pudo interpretar ninguna fila con el mapeo indicado. Revisa las columnas/formatos seleccionados.", $desde);
            return;
        }

        $db = Conexion::conectar();
        $insertadas = ConciliacionModel::importarLineas($db, $lineas, $cuenta, Auth::usuarioActual());

        $mensaje = "Se importaron $insertadas líneas del estado de cuenta.";
        if ($filasConError > 0) {
            $mensaje .= " ($filasConError fila(s) no se pudieron leer y se omitieron.)";
        }
        $this->form($mensaje, null, $desde);
    }

    private function verificar() {
        $desde = $this->fechaDesde($_POST['desde'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: conciliacion.php");
            exit;
        }
        if (!Auth::esAdministrador()) {
            header("Location: inicio.php?error=permiso");
            exit;
        }

        $fecha = $_POST['fecha'] ?? '';
        $monto = (float)($_POST['monto'] ?? 0);
        $cuenta = trim($_POST['cuenta'] ?? '') ?: null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $monto <= 0) {
            $this->form(null, "Datos de verificación inválidos.", $desde);
            return;
        }

        $db = Conexion::conectar();
        $resultado = ConciliacionModel::verificarGrupo($db, $fecha, $monto, $cuenta, Auth::usuarioActual());

        if ($resultado === null) {
            $this->form(null, "Ya no hay suficientes líneas disponibles en el estado de cuenta para ese grupo (puede que alguien más lo haya verificado, o falte importar más líneas).", $desde);
            return;
        }
        if ($resultado === 0) {
            $this->form(null, "Ese grupo ya no tiene recibos pendientes.", $desde);
            return;
        }

        $this->form("$resultado recibo(s) verificados correctamente.", null, $desde);
    }
}
?>
