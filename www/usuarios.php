<?php
require_once "controllers/UsuariosController.php";

$action = $_GET['action'] ?? 'listar';
$controller = new UsuariosController();
$controller->handle($action);
?>
