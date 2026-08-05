<?php
require_once "controllers/AdminController.php";

$action = $_GET['action'] ?? 'form';
$controller = new AdminController();
$controller->handle($action);
?>
