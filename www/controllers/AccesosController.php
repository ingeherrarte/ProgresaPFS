<?php
require_once "models/AccesoModel.php";
require_once "views/AccesosView.php";
require_once __DIR__ . "/../helpers/Auth.php";

class AccesosController {

    // Historial de ingresos/salidas: exclusivo del administrador.
    public function handle(): void {
        Auth::requerirRol([Auth::ROL_ADMINISTRADOR]);
        AccesosView::mostrar(AccesoModel::obtenerTodos());
    }
}
?>
