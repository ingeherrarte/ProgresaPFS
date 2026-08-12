<?php
require_once "models/ConsultaPagosModel.php";
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
}
?>
