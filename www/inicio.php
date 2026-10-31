<?php
require_once "controllers/InicioController.php";

$vista = $_GET['vista'] ?? 'dashboard';
$controller = new InicioController();
$controller->handle($vista);
?>
