<?php
require_once "controllers/NotasController.php";

$action = $_GET['action'] ?? 'form';
$controller = new NotasController();
$controller->handle($action);
?>
