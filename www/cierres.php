<?php
require_once "controllers/CierresController.php";

$tipo = $_GET['tipo'] ?? 'dia';
$controller = new CierresController();
$controller->handle($tipo);
?>
