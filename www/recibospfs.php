<?php
date_default_timezone_set("America/Guatemala");

require_once "controllers/RecibosPfsController.php";

$action = $_GET['action'] ?? 'form';
$controller = new RecibosPfsController();
$controller->handle($action);
?>
