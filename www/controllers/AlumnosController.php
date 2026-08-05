<?php
// controllers/AlumnosController.php

// 🛑 Incluir la nueva clase Modelo
require_once "models/AlumnosModel.php";

class AlumnosController {
    
    // Método de validación que se mantiene igual
    private function validar($data) {
        return !empty($data['nombre']) &&
               !empty($data['telefono']) &&
               !empty($data['email']) &&
               filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
               !empty($data['fecha_inscripcion']);
    }

    public function handle($action) {
        switch ($action) {
            case 'listar':
                // 🛑 Llamada a la nueva clase Modelo
                $alumnos = AlumnosModel::obtenerTodos();
                require "views/alumnos.php";
                break;
                
            case 'form_insertar':
                // Inicializar $data para que la vista no falle si no viene de un POST
                $data = []; 
                $error = null;
                require "views/form_insertar.php";
                break;
                
            case 'insertar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = $_POST;
                    $error_message = null;

                    if ($this->validar($data)) {
                        // 🛑 Llamada a la nueva clase Modelo
                        $resultado = AlumnosModel::insertar($data);
                        
                        if ($resultado === true) {
                            header("Location: index.php?action=listar");
                            exit;
                        } elseif ($resultado === 'duplicate') {
                            // Error de duplicidad
                            $error_message = "El email ingresado ya existe en el sistema. Por favor, corrija el correo.";
                        } else {
                            // Error general de BD
                            $error_message = "Ocurrió un error inesperado al intentar guardar el alumno.";
                        }
                    } else {
                        // Error de validación (campos vacíos, formato)
                        $error_message = "Todos los campos son obligatorios y deben tener formato válido.";
                    }

                    // 🛑 VOLVER AL FORMULARIO EN CASO DE CUALQUIER ERROR
                    if ($error_message) {
                        $error = $error_message; 
                        require "views/form_insertar.php"; // Volver a cargar la vista del formulario
                    }
                }
                break;

            case 'form_editar':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    // 🛑 Llamada a la nueva clase Modelo
                    $alumno = AlumnosModel::obtenerPorId($id);
                    // Inicializar $error para la vista de edición
                    $error = null;
                    require "views/form_editar.php";
                }
                break;
                
            case 'editar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'];
                    $data = $_POST;
                    $error_message = null;

                    if ($this->validar($data)) {
                        // 🛑 Llamada a la nueva clase Modelo
                        $resultado = AlumnosModel::actualizar($id, $data);
                        
                        if ($resultado === true) {
                            header("Location: index.php?action=listar");
                            exit;
                        } elseif ($resultado === 'duplicate') {
                            $error_message = "El email ingresado ya existe en el sistema. Por favor, corrija el correo.";
                        } else {
                            $error_message = "Ocurrió un error inesperado al intentar actualizar el alumno.";
                        }
                    } else {
                        $error_message = "Todos los campos son obligatorios y deben tener formato válido.";
                    }

                    // 🛑 VOLVER AL FORMULARIO DE EDICIÓN EN CASO DE ERROR
                    if ($error_message) {
                        $error = $error_message; 
                        // Necesitamos recargar los datos del alumno original
                        $alumno = AlumnosModel::obtenerPorId($id); 
                        require "views/form_editar.php"; 
                    }
                }
                break;
                
            case 'eliminar':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    // 🛑 Llamada a la nueva clase Modelo
                    AlumnosModel::eliminar($id);
                    header("Location: index.php?action=listar");
                    exit;
                }
                break;
        }
    }
}
?>