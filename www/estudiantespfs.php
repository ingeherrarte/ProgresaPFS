<?php
require_once "controllers/EstudiantesPfsController.php";

$controller = new EstudiantesPfsController();
$controller->handle($_GET['action'] ?? 'buscar');
?>
