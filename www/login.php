<?php
require_once "controllers/AuthController.php";

$action = $_GET['action'] ?? 'form';
$controller = new AuthController();
$controller->handle($action);
?>
