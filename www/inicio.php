<?php
require_once "controllers/InicioController.php";

$vista = $_GET['vista'] ?? 'simple';
$controller = new InicioController();
$controller->handle($vista);
?>
