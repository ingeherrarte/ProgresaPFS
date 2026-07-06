<?php
// index2.php - Router dedicado solo a RecibosPrueba

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Necesitamos la lista de estudiantes para el SELECT del formulario
require_once "models/EstudiantesModel.php"; 
require_once "models/RecibosPruebaModel.php"; // Necesario si usa métodos de su propio modelo
require_once "views/RecibosPruebaView.php";
require_once "controllers/RecibosPruebaController.php";

// 1. Obtener la acción: Por defecto, ir al formulario de ingreso
$action = $_GET['action'] ?? 'form_ingresar';

// 2. Instanciar y ejecutar el controlador de Recibos
$controller = new RecibosPruebaController();
$controller->handle($action);

// Nota: Si quieres que el botón "Guardar" del formulario funcione, 
// el action del formulario debe ser: index2.php?action=guardar
?>