<?php
require_once "models/ConsultaPagosModel.php";
require_once "models/EstudiantesPfsModel.php";
require_once "views/ConsultaPagosView.php";
require_once __DIR__ . "/../helpers/Auth.php";
require_once __DIR__ . "/../config/Conexion.php";

class ConsultaPagosController {

    private const POR_PAGINA = 20;

    // Disponible para cualquier usuario con sesión: es un reporte de solo
    // lectura, no requiere ningún rol especial.
    public function handle($action) {
        switch ($action) {
            case 'buscarJson':
                Auth::requerirSesionJson();
                $this->buscarJson();
                break;

            case 'detalle':
                Auth::requerirSesion();
                $this->detalle();
                break;

            case 'reportes':
                Auth::requerirSesion();
                $this->reportes();
                break;

            case 'buscar':
            default:
                Auth::requerirSesion();
                $this->buscar();
                break;
        }
    }

    private function buscar() {
        $termino = trim($_GET['q'] ?? '');
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));

        $resultado = ['total' => 0, 'filas' => []];
        if ($termino !== '') {
            $db = Conexion::conectar();
            $resultado = ConsultaPagosModel::buscar($db, $termino, $pagina, self::POR_PAGINA);
        }

        $totalPaginas = $resultado['total'] > 0 ? (int)ceil($resultado['total'] / self::POR_PAGINA) : 0;
        ConsultaPagosView::mostrarBuscar($termino, $resultado['filas'], $pagina, $totalPaginas, $resultado['total']);
    }

    private function buscarJson() {
        header('Content-Type: application/json; charset=utf-8');
        $termino = trim($_GET['q'] ?? '');

        if (mb_strlen($termino) < 3) {
            echo json_encode(['total' => 0, 'filas' => []]);
            exit;
        }

        $db = Conexion::conectar();
        $resultado = ConsultaPagosModel::buscar($db, $termino, 1, self::POR_PAGINA);

        $filas = array_map(function ($f) {
            return [
                'idestudiante' => $f['idestudiante'],
                'nombreCompleto' => trim($f['nombre'] . ' ' . $f['apellidos']),
                'nombrecurso' => $f['nombrecurso'],
                'totalRecibos' => (int)$f['total_recibos'],
                'totalPagado' => (float)$f['total_pagado'],
                'activo' => (int)$f['activo'],
            ];
        }, $resultado['filas']);

        echo json_encode(['total' => $resultado['total'], 'filas' => $filas]);
        exit;
    }

    private function detalle() {
        $carnet = trim($_GET['carnet'] ?? '');
        if (!ctype_digit($carnet)) {
            header("Location: consulta_pagos.php");
            exit;
        }

        $db = Conexion::conectar();
        $estudiante = ConsultaPagosModel::estudiante($db, $carnet);
        if (!$estudiante) {
            header("Location: consulta_pagos.php");
            exit;
        }

        $pagos = ConsultaPagosModel::historialPagos($db, $carnet);
        ConsultaPagosView::mostrarDetalle($estudiante, $pagos);
    }

    // Estado de pagos + Graduación + Libro en una sola tabla filtrable en
    // el cliente (diplomado, jornada, nombre, graduación, libro), sin
    // tener que elegir un diplomado primero.
    //
    // MESES_RECIENTES_QA acota el universo a "pagó en los últimos 3
    // meses" — es temporal, solo para poder probar esto en QA mientras
    // Grupos no existe todavía para la mayoría de estudiantes. Cuando sí
    // exista, el universo debe venir de miembros de grupos (ver
    // GruposModel::miembros) en vez de este filtro de pago reciente.
    private const MESES_RECIENTES_QA = 3;

    private function reportes() {
        $db = Conexion::conectar();
        $cursos = EstudiantesPfsModel::obtenerCursos($db);
        $patronGraduacion = trim($_GET['patron_graduacion'] ?? '') ?: 'GRADUA';
        $patronLibro = trim($_GET['patron_libro'] ?? '') ?: 'LIBRO';

        $filas = ConsultaPagosModel::reporteUnificado($db, self::MESES_RECIENTES_QA, $patronGraduacion, $patronLibro);

        ConsultaPagosView::mostrarReportes($filas, $cursos, $patronGraduacion, $patronLibro, self::MESES_RECIENTES_QA);
    }
}
?>
