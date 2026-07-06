<?php
// controllers/EstudiantesController.php

require_once "models/EstudiantesModel.php";
require_once "views/EstudiantesView.php";

class EstudiantesController {
    
    private function validar($data) {
        // Validación: Nombre y Email deben existir, Email debe ser válido
        return !empty($data['nombre']) &&
               !empty($data['email']) &&
               filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    }

    public function handle($action) {
        switch ($action) {
            
            case 'form_insertar':
                // Mostrar el formulario vacío
                EstudiantesView::mostrarFormularioInsertar();
                break;
                
            case 'guardar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = $_POST;
                    $error_message = null;

                    if ($this->validar($data)) {
                        $resultado = EstudiantesModel::insertar($data);
                        
                        if ($resultado === true) {
                            // Éxito: Redirigir al listado (asumimos 'listar' existe)
                            header("Location: index.php?controller=estudiantes&action=listar");
                            exit;
                        } elseif ($resultado === 'duplicate') {
                            $error_message = "El email ingresado ya existe. Por favor, corrija el correo.";
                        } else {
                            $error_message = "Error desconocido al guardar en la base de datos.";
                        }
                    } else {
                        $error_message = "El nombre y el email son obligatorios y el email debe ser válido.";
                    }

                    // Si hay error, volvemos a mostrar el formulario con los datos y el error
                    if ($error_message) {
                        EstudiantesView::mostrarFormularioInsertar($data, $error_message);
                    }
                }
                break;

            case 'listar':
                // Por ahora, solo un mensaje simple para probar la redirección
                echo "<h1>Lista de Estudiantes</h1><p>¡Inserción exitosa! (Aquí iría la tabla).</p>";
                echo "<a href='index.php?controller=estudiantes&action=form_insertar'>Volver al Formulario</a>";
                break;
        }
    }
}
?>