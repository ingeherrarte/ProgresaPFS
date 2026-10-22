<?php
require_once "models/ConciliacionModel.php";
require_once "models/DepositosModel.php";
require_once "views/ConciliacionView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class ConciliacionController {

    private const MAX_FILAS = 5000;
    private const MAX_TAMANO_ARCHIVO = 3 * 1024 * 1024;

    // Formato confirmado de cada banco (siempre el mismo export, columnas
    // 0-based). BANRURAL: Fecha, Oficina, Descripción, Referencia,
    // Secuencial, Cheque Propio/Local/Efectivo, Débito(-), Crédito(+), Saldo
    // Contable, Saldo Disponible (A..J), encabezado en la fila 5, datos
    // desde la 6. BANTRAB: visualmente FECHA, NO. DOCUMENTO, DESCRIPCIÓN,
    // DÉBITOS, CRÉDITOS, SALDO, pero el .xlsx real (exportado con extensión
    // .xls) usa columnas de relleno angostas entre cada dato visible — las
    // columnas REALES, confirmadas abriendo el XML de un archivo real
    // (2026-10-19), son: Fecha=A(0), No.Documento=C(2), Descripción=D(3),
    // Débitos=H(7), Créditos=J(9), Saldo=K(10); encabezado en la fila 6,
    // datos desde la 7.
    private const PRESETS = [
        '3099203897' => [ // BANRURAL
            'colFecha' => 0, 'colDescripcion' => 2, 'colReferencia' => 3, 'colMonto' => 7, 'filaInicio' => 6,
            'formatoFecha' => 'dmy', 'formatoMonto' => 'punto_decimal',
        ],
        '2870011720' => [ // BANTRAB
            'colFecha' => 0, 'colDescripcion' => 3, 'colReferencia' => 2, 'colMonto' => 9, 'filaInicio' => 7,
            'formatoFecha' => 'dmy', 'formatoMonto' => 'punto_decimal',
        ],
    ];

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
            case 'mapeo':
                $this->continuarMapeo();
                break;
            case 'cancelarMapeo':
                $this->cancelarMapeo();
                break;
            case 'verificar':
                $this->verificar();
                break;
            case 'buscarManual':
                $this->buscarManual();
                break;
            case 'vincularManual':
                $this->vincularManualAccion();
                break;
            case 'movimientos':
                $this->movimientos();
                break;
            case 'reporte':
                $this->reporte();
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

    // Default: hoy. El rango normal de trabajo es el período que cubre el
    // estado de cuenta que se acaba de subir (ej. 01/10 al 15/10).
    private function fechaHasta(?string $valor): string {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$valor) ? $valor : date('Y-m-d');
    }

    // Default propio para el formulario de SUBIDA (distinto del "Revisar
    // desde" de la lista de pendientes, que default a 1 de enero para no
    // esconder histórico atrasado): los estados de cuenta que se suben son
    // casi siempre de los últimos días, así que partir de hoy-8 evita tener
    // que mover la fecha a mano cada vez.
    private function fechaDesdeSubida(?string $valor): string {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$valor) ? $valor : date('Y-m-d', strtotime('-8 days'));
    }

    // Si el usuario invierte el rango (hasta < desde) no se devuelve nunca
    // nada y parece que "no hay pendientes": se intercambian para que el
    // filtro siga siendo útil en vez de mostrar una lista vacía engañosa.
    private function ordenar(string $desde, string $hasta): array {
        return $hasta < $desde ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function rango(?string $desde, ?string $hasta): array {
        return $this->ordenar($this->fechaDesde($desde), $this->fechaHasta($hasta));
    }

    // Rango específico del formulario de subida (ver fechaDesdeSubida).
    private function rangoSubida(?string $desde, ?string $hasta): array {
        return $this->ordenar($this->fechaDesdeSubida($desde), $this->fechaHasta($hasta));
    }

    private function form(?string $mensaje = null, ?string $error = null, ?string $desde = null, ?string $hasta = null) {
        [$desde, $hasta] = $this->rango(
            $desde ?? ($_GET['desde'] ?? null),
            $hasta ?? ($_GET['hasta'] ?? null)
        );
        // El formulario de subida tiene su propio rango sugerido (ver
        // fechaDesdeSubida) — se recalcula aparte en vez de reusar $desde,
        // salvo que venga de un intento de subida real, donde $_POST todavía
        // trae lo que el usuario puso (o dejó vacío) en ESE formulario.
        [$desdeSubida, $hastaSubida] = $this->rangoSubida($_POST['desde'] ?? null, $_POST['hasta'] ?? null);
        $db = Conexion::conectar();
        $pendientes = ConciliacionModel::pendientesConCandidatos($db, $desde, $hasta);
        // "Líneas del estado de cuenta" usa el rango de SUBIDA (más angosto,
        // el período que se acaba de trabajar), no el de "Recibos
        // pendientes" (que se queda amplio a propósito para no esconder
        // backlog atrasado) — mostrar aquí todo el año hacía la tabla
        // inútil para revisar "¿mi importación de hoy quedó bien?".
        $lineas = ConciliacionModel::lineasEstadoCuenta($db, $desdeSubida, $hastaSubida);

        // Si el admin se fue a verificar algo a media importación (ver
        // continuarMapeo/cancelarMapeo), se ofrece retomarla aquí en vez de
        // dejarla silenciosamente abandonada en la sesión.
        $pendienteMapeo = !empty($_SESSION['conciliacion_csv']) ? [
            'numero' => ($_SESSION['conciliacion_total'] ?? 1) - count($_SESSION['conciliacion_cola'] ?? []),
            'total' => $_SESSION['conciliacion_total'] ?? 1,
            'banco' => DepositosModel::bancoDeCuenta($_SESSION['conciliacion_cuenta'] ?? '') ?? ($_SESSION['conciliacion_cuenta'] ?? ''),
        ] : null;

        ConciliacionView::mostrarPrincipal($pendientes, $lineas, $mensaje, $error, $desde, $hasta, $desdeSubida, $hastaSubida, $pendienteMapeo);
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
    // Composer, solo ext-zip (agregada al Dockerfile de QA y de producción)
    // y SimpleXML (viene por defecto en la imagen php:8.2-apache). Solo lee
    // la primera hoja, que es lo único que necesita un estado de cuenta.
    // Un nodo de texto de .xlsx (<si> de sharedStrings o <is> inline) viene
    // en dos formas válidas: con <t> como hijo directo, o con uno o más
    // <r><t>...</t></r> ("runs", cuando el texto tiene formato mixto —
    // común en exports de banca en línea). $nodo->t solo encuentra la
    // primera forma; sin este fallback a <r><t>, cualquier celda que venga
    // en la segunda forma se leía como vacía — el síntoma real era que el
    // archivo entero se reportaba como "vacío o no se pudo leer" porque
    // CADA celda de texto resolvía a '' y ninguna fila pasaba el filtro de
    // "no vacía".
    private function textoDeNodo(SimpleXMLElement $nodo): string {
        if (isset($nodo->t)) {
            return (string)$nodo->t;
        }
        $texto = '';
        foreach ($nodo->r as $run) {
            $texto .= (string)$run->t;
        }
        return $texto;
    }

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
                $sharedStrings[] = $this->textoDeNodo($si);
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
                    $valor = isset($c->is) ? $this->textoDeNodo($c->is) : '';
                } else {
                    $valor = (string)$c->v;
                }
                $celdas[] = trim($valor);
                $colIndex++;
            }
            // Solo se descarta una fila si no tenía NINGUNA celda <c>
            // registrada en el XML — nunca por el contenido (filas con
            // celdas presentes pero vacías, como separadores entre el
            // membrete y el encabezado, sí se conservan). De lo contrario
            // "fila_inicio_datos" deja de corresponder al número real de
            // fila del archivo: un export con 2 filas de separador en
            // blanco antes del encabezado desalineaba todo lo que viene
            // después por esas 2 filas.
            if (!empty($celdas)) {
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

    // Mensaje específico por código de error de subida de PHP, en vez del
    // genérico "está vacío o no se pudo leer" que no dice nada útil cuando
    // el problema es, por ejemplo, un límite de tamaño del servidor.
    private function mensajeErrorSubida(int $codigo): string {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                "el archivo excede el tamaño máximo que el servidor acepta para una subida.",
            UPLOAD_ERR_PARTIAL =>
                "la subida se interrumpió a la mitad (conexión lenta o se cerró la pestaña). Intenta de nuevo.",
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                "el servidor no pudo guardar el archivo temporalmente. Intenta de nuevo.",
            UPLOAD_ERR_EXTENSION =>
                "una extensión de PHP del servidor bloqueó la subida.",
            default => "no se pudo recibir el archivo (código $codigo).",
        };
    }

    // Lee y parsea un archivo subido ($_FILES[$campo]), sea CSV, .xlsx real,
    // o .xls-que-en-realidad-es-HTML. Centraliza la detección por contenido
    // (no por extensión) para poder reutilizarla con 'archivo' y 'archivo2'
    // sin duplicar la lógica de sniffing.
    private function leerArchivoSubido(string $campo): array {
        $error = $_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->mensajeErrorSubida($error));
        }
        if ($_FILES[$campo]['size'] > self::MAX_TAMANO_ARCHIVO) {
            throw new RuntimeException("el archivo supera el tamaño máximo permitido (3 MB).");
        }

        // No confiamos en la extensión del nombre: varios bancos generan
        // archivos ".xls" que en realidad son tablas HTML, o ".csv" que
        // vienen con otra codificación. Se detecta por el contenido real
        // (magic bytes / sniffing de las primeras líneas).
        $rutaTemporal = $_FILES[$campo]['tmp_name'];
        $cabecera = file_get_contents($rutaTemporal, false, null, 0, 8);

        if (substr($cabecera, 0, 2) === 'PK') {
            // ZIP real: .xlsx
            $filas = $this->parsearXlsx($rutaTemporal);
        } elseif (substr($cabecera, 0, 4) === "\xD0\xCF\x11\xE0") {
            // OLE2 real: .xls binario legacy (Excel 97-2003), no soportado
            throw new RuntimeException("es un .xls binario real (Excel 97-2003), que no se puede leer directamente. Ábrelo en Excel y guárdalo como .xlsx o CSV.");
        } else {
            $contenido = file_get_contents($rutaTemporal);

            // Algunos exports "Web Page" de banca en línea vienen en
            // UTF-16 (con BOM FF FE / FE FF): el contenido SÍ es HTML, pero
            // un stripos('<table') sobre los bytes crudos nunca lo
            // encuentra porque cada carácter ASCII viene intercalado con
            // bytes NUL ("<\x00t\x00a\x00b\x00l\x00e..."). Sin esta
            // conversión, el archivo caía al parser de CSV, que no
            // reconoce nada de eso y termina devolviendo 0 filas —
            // parecía que el .xls "no se reconocía" cuando en realidad sí
            // era HTML, solo que en una codificación distinta.
            if (substr($contenido, 0, 2) === "\xFF\xFE") {
                $contenido = mb_convert_encoding(substr($contenido, 2), 'UTF-8', 'UTF-16LE');
            } elseif (substr($contenido, 0, 2) === "\xFE\xFF") {
                $contenido = mb_convert_encoding(substr($contenido, 2), 'UTF-8', 'UTF-16BE');
            }

            $muestra = ltrim($contenido);
            if (stripos($muestra, '<table') !== false || stripos($muestra, '<html') !== false) {
                // Típico de bancos: exportan HTML con extensión .xls
                $filas = $this->parsearHtmlTabla($contenido);
            } else {
                $filas = $this->parsearCsv($contenido);
            }
        }

        if (empty($filas)) {
            throw new RuntimeException("está vacío o no se pudo leer (no se encontró ninguna tabla ni fila de datos reconocible).");
        }
        if (count($filas) > self::MAX_FILAS) {
            throw new RuntimeException("tiene demasiadas filas (máximo " . self::MAX_FILAS . "). Divídelo y sube por partes.");
        }
        return $filas;
    }

    // Limpia todo el estado de la cola de importación encadenada (ver
    // subir()/siguienteMapeo()) — se llama en cada salida por error para no
    // dejar un archivo a medio procesar colgado en la sesión.
    private function limpiarColaImportacion(): void {
        unset(
            $_SESSION['conciliacion_csv'],
            $_SESSION['conciliacion_cuenta'],
            $_SESSION['conciliacion_cola'],
            $_SESSION['conciliacion_total'],
            $_SESSION['conciliacion_mensajes']
        );
    }

    // Muestra el mapeo del siguiente archivo en la cola, o —si ya no quedan—
    // regresa a la pantalla principal con el resumen de todo lo importado.
    // BANRURAL y BANTRAB se suben juntos pero se mapean uno tras otro porque
    // cada uno trae columnas distintas (ver mostrarMapeo en la vista).
    private function siguienteMapeo(string $desde, string $hasta): void {
        if (empty($_SESSION['conciliacion_cola'])) {
            $mensaje = !empty($_SESSION['conciliacion_mensajes'])
                ? implode(' ', $_SESSION['conciliacion_mensajes'])
                : null;
            $this->limpiarColaImportacion();
            // null,null (no $desde/$hasta explícitos): al terminar una
            // importación, "Recibos pendientes" debe volver a su propio
            // default (todo el histórico sin resolver desde el 1 de enero),
            // no quedarse acotado a los 8 días del formulario de subida —
            // el formulario de subida recalcula su propio sugerido aparte,
            // a partir de lo que siga en $_POST de este mismo request.
            $this->form($mensaje, null, null, null);
            return;
        }

        $item = array_shift($_SESSION['conciliacion_cola']);
        $_SESSION['conciliacion_csv'] = $item['filas'];
        $_SESSION['conciliacion_cuenta'] = $item['cuenta'];
        $numero = $_SESSION['conciliacion_total'] - count($_SESSION['conciliacion_cola']);
        $preset = self::PRESETS[$item['cuenta']] ?? null;
        ConciliacionView::mostrarMapeo(
            $item['filas'], $desde, $hasta, $numero, $_SESSION['conciliacion_total'],
            DepositosModel::bancoDeCuenta($item['cuenta']) ?? $item['cuenta'], $preset
        );
    }

    // Dado que solo hay dos cuentas configuradas, si se conoce la del
    // primer archivo la del segundo no hace falta preguntarla: es la única
    // que queda. Si en el futuro se agrega una tercera cuenta, esto deja de
    // ser inambiguo a propósito (devuelve null) y el segundo archivo exige
    // su propia selección en vez de adivinar mal.
    private function otraCuenta(string $cuenta): ?string {
        $otras = array_values(array_diff(array_keys(DepositosModel::cuentas()), [$cuenta]));
        return count($otras) === 1 ? $otras[0] : null;
    }

    private function subir() {
        [$desde, $hasta] = $this->rangoSubida($_POST['desde'] ?? null, $_POST['hasta'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['archivo']['tmp_name'])) {
            $this->form(null, "Selecciona al menos el primer archivo.", $desde, $hasta);
            return;
        }

        $cuenta1 = trim($_POST['cuenta1'] ?? '');
        if (!array_key_exists($cuenta1, DepositosModel::cuentas())) {
            $this->form(null, "Indica de qué banco es el primer archivo.", $desde, $hasta);
            return;
        }

        $cola = [];
        try {
            $cola[] = ['filas' => $this->leerArchivoSubido('archivo'), 'cuenta' => $cuenta1];
        } catch (Throwable $e) {
            $this->form(null, "Primer archivo: " . $e->getMessage(), $desde, $hasta);
            return;
        }

        // El segundo archivo es opcional: BANRURAL y BANTRAB se suben juntos
        // en el mismo formulario, pero si solo hay un banco que conciliar
        // ese mes, no hace falta forzar los dos. Su cuenta se infiere sola
        // (ver otraCuenta) — no se vuelve a preguntar.
        if (!empty($_FILES['archivo2']['tmp_name'])) {
            $cuenta2 = $this->otraCuenta($cuenta1);
            if ($cuenta2 === null) {
                $this->form(null, "No se pudo determinar automáticamente el banco del segundo archivo. Súbelo por separado.", $desde, $hasta);
                return;
            }
            try {
                $cola[] = ['filas' => $this->leerArchivoSubido('archivo2'), 'cuenta' => $cuenta2];
            } catch (Throwable $e) {
                $this->form(null, "Segundo archivo: " . $e->getMessage(), $desde, $hasta);
                return;
            }
        }

        $_SESSION['conciliacion_cola'] = $cola;
        $_SESSION['conciliacion_total'] = count($cola);
        $_SESSION['conciliacion_mensajes'] = [];
        // Se guardan aparte de la cola para poder reconstruir la pantalla de
        // mapeo por GET (ver continuarMapeo()) sin depender de que el
        // formulario de confirmarImport siga mandándolos en campos ocultos.
        $_SESSION['conciliacion_desde'] = $desde;
        $_SESSION['conciliacion_hasta'] = $hasta;
        $this->siguienteMapeo($desde, $hasta);
    }

    // Reabre la pantalla de mapeo del archivo que se está procesando SIN
    // sacarlo de la cola (a diferencia de siguienteMapeo) — permite que el
    // usuario se vaya a revisar "Movimientos del banco" o "Recibos
    // pendientes" a media importación y regrese exactamente donde quedó.
    private function continuarMapeo(): void {
        if (empty($_SESSION['conciliacion_csv'])) {
            header("Location: conciliacion.php");
            exit;
        }

        $desde = $_SESSION['conciliacion_desde'] ?? $this->fechaDesde(null);
        $hasta = $_SESSION['conciliacion_hasta'] ?? $this->fechaHasta(null);
        $cuenta = $_SESSION['conciliacion_cuenta'] ?? '';
        $total = $_SESSION['conciliacion_total'] ?? 1;
        $numero = $total - count($_SESSION['conciliacion_cola'] ?? []);
        $preset = self::PRESETS[$cuenta] ?? null;

        ConciliacionView::mostrarMapeo(
            $_SESSION['conciliacion_csv'], $desde, $hasta, $numero, $total,
            DepositosModel::bancoDeCuenta($cuenta) ?? $cuenta, $preset
        );
    }

    // A diferencia de "Volver" (que deja la importación intacta para
    // retomarla con continuarMapeo), esto SÍ descarta el archivo pendiente.
    private function cancelarMapeo(): void {
        [$desde, $hasta] = $this->rango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
        $this->limpiarColaImportacion();
        header("Location: conciliacion.php?desde=" . urlencode($desde) . "&hasta=" . urlencode($hasta));
        exit;
    }

    private function parsearMonto(string $valor, string $formato): ?float {
        // Quita símbolos de moneda y espacios antes de la limpieza de
        // miles/decimales — BANTRAB exporta los montos como "Q547.20" /
        // "Q11,386.92", no como número puro, y is_numeric() los rechaza si
        // queda la "Q" pegada.
        $valor = trim(preg_replace('/[Q$\s]/u', '', $valor));
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
        [$desde, $hasta] = $this->rango($_POST['desde'] ?? null, $_POST['hasta'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['conciliacion_csv'])) {
            $this->limpiarColaImportacion();
            $this->form(null, "La sesión de importación expiró, sube el archivo de nuevo.", $desde, $hasta);
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
        $colDescripcion = $_POST['col_descripcion'] ?? '';
        $formatoFecha = $_POST['formato_fecha'] ?? 'dmy';
        $formatoMonto = $_POST['formato_monto'] ?? 'punto_decimal';
        // La cuenta viene de la sesión (fijada en siguienteMapeo al elegir
        // el banco en el formulario de subida), no de un campo del request:
        // así no depende de que el cliente mande un valor consistente, y no
        // hace falta volver a preguntarla aquí.
        $cuenta = $_SESSION['conciliacion_cuenta'] ?? '';

        if (!array_key_exists($cuenta, DepositosModel::cuentas())) {
            $this->limpiarColaImportacion();
            $this->form(null, "La sesión de importación expiró, sube el archivo de nuevo.", $desde, $hasta);
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
            $descripcion = ($colDescripcion !== '' && isset($fila[(int)$colDescripcion]))
                ? mb_substr($fila[(int)$colDescripcion], 0, 255)
                : null;
            $lineas[] = ['fecha' => $fecha, 'monto' => $monto, 'referencia' => $referencia, 'descripcion' => $descripcion];
        }

        if (empty($lineas)) {
            $this->limpiarColaImportacion();
            $this->form(null, "No se pudo interpretar ninguna fila con el mapeo indicado. Revisa las columnas/formatos seleccionados.", $desde, $hasta);
            return;
        }

        $db = Conexion::conectar();
        $resultadoImport = ConciliacionModel::importarLineas($db, $lineas, $cuenta, Auth::usuarioActual());

        $mensaje = "Se importaron {$resultadoImport['insertadas']} líneas del estado de cuenta.";
        if ($resultadoImport['omitidas'] > 0) {
            $mensaje .= " ({$resultadoImport['omitidas']} ya existían de una importación anterior y se omitieron, sin duplicar.)";
        }
        if ($filasConError > 0) {
            $mensaje .= " ($filasConError fila(s) no se pudieron leer y se omitieron.)";
        }

        // El número de este archivo dentro de la cola: total menos lo que
        // queda POR procesar (el archivo actual ya se sacó de la cola en
        // siguienteMapeo() antes de mostrarse, así que no se resta dos veces).
        $total = $_SESSION['conciliacion_total'] ?? 1;
        $restantes = count($_SESSION['conciliacion_cola'] ?? []);
        $numero = $total - $restantes;
        $_SESSION['conciliacion_mensajes'] ??= [];
        $_SESSION['conciliacion_mensajes'][] = $total > 1 ? "Archivo $numero: $mensaje" : $mensaje;

        unset($_SESSION['conciliacion_csv'], $_SESSION['conciliacion_cuenta']);
        $this->siguienteMapeo($desde, $hasta);
    }

    // Verifica UN recibo contra la línea del banco que el admin eligió
    // explícitamente (selector por recibo en la vista — nunca se asigna en
    // silencio cuando hay varias candidatas). Reusa vincularManual(): es el
    // mismo motor transaccional de la vinculación manual, solo que aquí
    // siempre con un único recibo.
    private function verificar() {
        [$desde, $hasta] = $this->rango($_POST['desde'] ?? null, $_POST['hasta'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: conciliacion.php");
            exit;
        }
        if (!Auth::esAdministrador()) {
            header("Location: inicio.php?error=permiso");
            exit;
        }

        $numeroRecibo = (int)($_POST['numero_recibo'] ?? 0);
        $estadoCuentaId = (int)($_POST['estado_cuenta_id'] ?? 0);
        if ($numeroRecibo <= 0 || $estadoCuentaId <= 0) {
            $this->form(null, "Selecciona una línea del banco para este recibo antes de verificar.", $desde, $hasta);
            return;
        }

        $db = Conexion::conectar();
        $resultado = ConciliacionModel::vincularManual($db, $estadoCuentaId, [$numeroRecibo], Auth::usuarioActual());

        if (!$resultado['ok']) {
            $this->form(null, $resultado['error'], $desde, $hasta);
            return;
        }

        $this->form("Recibo #$numeroRecibo verificado correctamente.", null, $desde, $hasta);
    }

    // Pantalla de vinculación manual: para una transferencia de un período
    // muy anterior (el cruce automático no la habría encontrado porque no
    // hay límite de fecha aquí, es justo lo que permite buscarla) o para
    // un depósito que cubre varios recibos a la vez (ej. una empresa que
    // paga por 2 estudiantes con la misma transferencia).
    private function buscarManual(?string $mensaje = null, ?string $error = null, ?string $carne = null, ?string $montoRecibo = null, ?string $cuentaLinea = null, ?string $montoLinea = null) {
        $carne = trim($carne ?? ($_GET['carne'] ?? ''));
        $montoRecibo = $montoRecibo ?? ($_GET['monto_recibo'] ?? '');
        $cuentaLinea = trim($cuentaLinea ?? ($_GET['cuenta_linea'] ?? ''));
        $montoLinea = $montoLinea ?? ($_GET['monto_linea'] ?? '');

        $montoReciboValor = $montoRecibo !== '' ? (float)$montoRecibo : null;
        $montoLineaValor = $montoLinea !== '' ? (float)$montoLinea : null;
        $cuentaLineaValor = $cuentaLinea !== '' ? $cuentaLinea : null;

        $db = Conexion::conectar();
        // Solo busca si se dio al menos un criterio — sin esto, cargar la
        // pantalla por primera vez listaría TODOS los recibos pendientes o
        // TODAS las líneas disponibles, que es justo lo que las otras dos
        // tablas del módulo ya muestran.
        $recibos = ($carne !== '' || $montoReciboValor !== null)
            ? ConciliacionModel::buscarRecibosPendientes($db, $carne, $montoReciboValor)
            : [];
        $lineas = ($cuentaLineaValor !== null || $montoLineaValor !== null)
            ? ConciliacionModel::buscarLineasDisponibles($db, $cuentaLineaValor, $montoLineaValor)
            : [];

        ConciliacionView::mostrarBusquedaManual($recibos, $lineas, $carne, $montoRecibo, $cuentaLinea, $montoLinea, $mensaje, $error);
    }

    private function vincularManualAccion() {
        $carne = $_POST['carne'] ?? '';
        $montoRecibo = $_POST['monto_recibo'] ?? '';
        $cuentaLinea = $_POST['cuenta_linea'] ?? '';
        $montoLinea = $_POST['monto_linea'] ?? '';

        if (!Auth::esAdministrador()) {
            header("Location: inicio.php?error=permiso");
            exit;
        }

        $estadoCuentaId = (int)($_POST['estado_cuenta_id'] ?? 0);
        $numerosRecibo = array_values(array_unique(array_filter(array_map('intval', $_POST['recibos'] ?? []))));

        if ($estadoCuentaId <= 0 || empty($numerosRecibo)) {
            $this->buscarManual(null, "Selecciona una línea y al menos un recibo.", $carne, $montoRecibo, $cuentaLinea, $montoLinea);
            return;
        }

        $db = Conexion::conectar();
        $resultado = ConciliacionModel::vincularManual($db, $estadoCuentaId, $numerosRecibo, Auth::usuarioActual());

        if (!$resultado['ok']) {
            $this->buscarManual(null, $resultado['error'], $carne, $montoRecibo, $cuentaLinea, $montoLinea);
            return;
        }

        $mensaje = count($numerosRecibo) . " recibo(s) vinculado(s) correctamente por Q" . number_format($resultado['asignado'], 2) . ".";
        $this->buscarManual($mensaje, null, $carne, $montoRecibo, $cuentaLinea, $montoLinea);
    }

    // Navegador libre de todo lo importado — responde "¿esta transferencia
    // ya está en el sistema?" sin tener que revisar la base de datos a
    // mano. Sin límite de fecha propio (usa $this->rango, igual que
    // "Revisar desde" de la pantalla principal) y con búsqueda de texto
    // libre en referencia/descripción.
    private function movimientos() {
        [$desde, $hasta] = $this->rango($_GET['desde'] ?? null, $_GET['hasta'] ?? null);
        $cuenta = trim($_GET['cuenta'] ?? '') ?: null;
        $texto = trim($_GET['texto'] ?? '');

        $db = Conexion::conectar();
        $movimientos = ConciliacionModel::buscarMovimientos($db, $cuenta, $desde, $hasta, $texto);
        ConciliacionView::mostrarMovimientos($movimientos, $desde, $hasta, $cuenta, $texto);
    }

    // Reporte de avance: qué se ha verificado y cuándo — filtra por la
    // fecha en que se VERIFICÓ (no la fecha del depósito), con default de
    // los últimos 7 días (es un reporte de "qué se hizo estos días", no un
    // histórico completo).
    private function reporte() {
        if (!Auth::esAdministrador()) {
            header("Location: inicio.php?error=permiso");
            exit;
        }

        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['desde'] ?? '')) ? $_GET['desde'] : date('Y-m-d', strtotime('-7 days'));
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['hasta'] ?? '')) ? $_GET['hasta'] : date('Y-m-d');
        if ($hasta < $desde) { [$desde, $hasta] = [$hasta, $desde]; }
        $cuenta = trim($_GET['cuenta'] ?? '') ?: null;

        $db = Conexion::conectar();
        $verificados = ConciliacionModel::recibosVerificados($db, $desde, $hasta, $cuenta);
        $resumenMovimientos = ConciliacionModel::resumenMovimientos($db, $desde, $hasta, $cuenta);
        $tendenciaSinMarcar = ConciliacionModel::tendenciaSinMarcar($db, $cuenta);
        ConciliacionView::mostrarReporte($verificados, $desde, $hasta, $cuenta, $resumenMovimientos, $tendenciaSinMarcar);
    }
}
?>
