<?php
require_once "controllers/ConciliacionController.php";

$action = $_GET['action'] ?? 'form';
$controller = new ConciliacionController();
$controller->handle($action);
?>
