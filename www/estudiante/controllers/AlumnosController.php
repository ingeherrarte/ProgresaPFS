<?php
require_once "models/Alumno.php";

class AlumnosController {
    public function handle($action) {
        switch ($action) {
            case 'listar':
                $alumnos = Alumno::obtenerTodos();
                require "views/alumnos.php";
                break;
            case 'form_insertar':
                require "views/form_insertar.php";
                break;
            case 'insertar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = $_POST;
                    if ($this->validar($data)) {
                        Alumno::insertar($data);
                        header("Location: index.php?action=listar");
                    } else {
                        echo "Todos los campos son obligatorios y deben tener formato válido.";
                    }
                }
                break;
            case 'form_editar':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    $alumno = Alumno::obtenerPorId($id);
                    require "views/form_editar.php";
                }
                break;
            case 'editar':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'];
                    $data = $_POST;
                    if ($this->validar($data)) {
                        Alumno::actualizar($id, $data);
                        header("Location: index.php?action=listar");
                    } else {
                        echo "Todos los campos son obligatorios y deben tener formato válido.";
                    }
                }
                break;
            case 'eliminar':
                $id = $_GET['id'] ?? null;
                if ($id) {
                    Alumno::eliminar($id);
                    header("Location: index.php?action=listar");
                }
                break;
        }
    }

    private function validar($data) {
        return !empty($data['nombre']) &&
               !empty($data['telefono']) &&
               !empty($data['email']) &&
               filter_var($data['email'], FILTER_VALIDATE_EMAIL) &&
               !empty($data['fecha_inscripcion']);
    }
}
?>