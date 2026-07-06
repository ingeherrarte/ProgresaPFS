<?php
require_once "controllers/DepositosController.php";

$action = $_GET['action'] ?? 'form';
$controller = new DepositosController();
$controller->handle($action);
?>
