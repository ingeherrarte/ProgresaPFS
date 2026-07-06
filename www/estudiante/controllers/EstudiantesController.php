<?php
// controllers/EstudiantesController.php

require_once "models/EstudiantesModel.php";
require_once "views/EstudiantesView.php";

class EstudiantesController {

    public function handle($action) {
        switch ($action) {
            
            // ------------------------------------
            // 1. INGRESO (CREATE)
            // ------------------------------------
            case 'form_ingresar':
                EstudiantesView::mostrarFormularioIngreso();
                break;

            case 'guardar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $nombre = trim($_POST['nombre'] ?? '');
                    $telefono = trim($_POST['telefono'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $errores = [];

                    // Validación
                    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
                    if (empty($telefono)) $errores[] = "El teléfono es obligatorio.";
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email no es válido.";

                    if (count($errores) > 0) {
                        $data = ['nombre' => $nombre, 'telefono' => $telefono, 'email' => $email];
                        EstudiantesView::mostrarFormularioIngreso($errores, $data);
                    } else {
                        EstudiantesModel::insertar($nombre, $telefono, $email);
                        header("Location: index.php"); 
                        exit;
                    }
                }
                break;
                
            // ------------------------------------
            // 2. EDICIÓN (READ & UPDATE)
            // ------------------------------------
            
            // Muestra el formulario de búsqueda inicial
            case 'form_buscar_editar':
                EstudiantesView::mostrarFormularioBusqueda(null, "Ingrese el ID exacto o parte del Nombre del estudiante a editar.");
                break;
                
            // Procesa la búsqueda
            case 'buscar_estudiante':
                $query = $_GET['query'] ?? '';
                if (!empty($query)) {
                    $resultados = EstudiantesModel::buscarPorNombreOId($query);
                    EstudiantesView::mostrarFormularioBusqueda($resultados);
                } else {
                    EstudiantesView::mostrarFormularioBusqueda(null, "Debe ingresar un término de búsqueda.");
                }
                break;

            // Carga el formulario de edición con datos
            case 'form_editar':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $estudiante = EstudiantesModel::obtenerPorId($id); // <-- Aquí se solucionó el error
                    if ($estudiante) {
                        EstudiantesView::mostrarFormularioEditar($estudiante);
                    } else {
                        EstudiantesView::mostrarFormularioBusqueda(null, "Error: Estudiante con ID $id no encontrado.");
                    }
                } else {
                    EstudiantesView::mostrarFormularioBusqueda(null, "Error: ID no especificado.");
                }
                break;

            case 'actualizar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? null;
                    $nombre = trim($_POST['nombre'] ?? '');
                    $telefono = trim($_POST['telefono'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $errores = [];

                    // Validación
                    if (empty($nombre)) $errores[] = "El nombre es obligatorio.";
                    if (empty($telefono)) $errores[] = "El teléfono es obligatorio.";
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email no es válido.";
                    if (empty($id)) $errores[] = "ID de estudiante faltante.";

                    if (count($errores) > 0) {
                        $estudiante = ['id' => $id, 'nombre' => $nombre, 'telefono' => $telefono, 'email' => $email];
                        EstudiantesView::mostrarFormularioEditar($estudiante, $errores);
                    } else {
                        EstudiantesModel::actualizar($id, $nombre, $telefono, $email);
                        header("Location: index.php"); 
                        exit;
                    }
                }
                break;
            
            // ------------------------------------
            // 3. POR DEFECTO
            // ------------------------------------
            default:
                EstudiantesView::mostrarMenu();
                break;
        }
    }
}
?>