<?php
require_once "controllers/GruposController.php";

$action = $_GET['action'] ?? 'form';
$controller = new GruposController();
$controller->handle($action);
?>
