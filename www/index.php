<?php
require_once "controllers/AlumnosController.php";

$action = $_GET['action'] ?? 'listar';
$controller = new AlumnosController();
$controller->handle($action);
?>