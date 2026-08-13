<?php
require_once "controllers/ConsultaPagosController.php";

$controller = new ConsultaPagosController();
$controller->handle($_GET['action'] ?? 'buscar');
?>
