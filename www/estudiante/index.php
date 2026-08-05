<?php
// index.php - Router Principal

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "controllers/EstudiantesController.php";
require_once "controllers/RecibosPruebaController.php"; // ¡NUEVO!

// Determinar qué controlador usar: 'estudiantes' o 'recibos'
$controllerName = $_GET['controller'] ?? 'estudiantes';

// Determinar la acción a ejecutar
$action = $_GET['action'] ?? '';

switch ($controllerName) {
    case 'estudiantes':
        $controller = new EstudiantesController();
        break;
    
    case 'recibos': // ¡NUEVO MÓDULO!
        $controller = new RecibosPruebaController();
        break;
        
    default:
        http_response_code(404);
        die("Módulo/Controlador no encontrado.");
}

$controller->handle($action);
?>