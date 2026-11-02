<?php
require_once "controllers/ContabilidadController.php";

$action = $_GET['action'] ?? 'gastos';
$controller = new ContabilidadController();
$controller->handle($action);
?>
