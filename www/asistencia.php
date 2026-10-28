<?php
require_once "controllers/AsistenciaController.php";

$action = $_GET['action'] ?? 'form';
$controller = new AsistenciaController();
$controller->handle($action);
?>
