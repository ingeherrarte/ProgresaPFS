<?php
// models/AlumnosModel.php
require_once "config/Conexion.php";

// 🛑 El nombre de la clase cambia a AlumnosModel
class AlumnosModel {
    
    // Mantenemos los métodos estáticos para simplificar la llamada desde el controlador
    public static function obtenerTodos() {
        $db = Conexion::conectar();
        $sql = "SELECT * FROM alumnos";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerPorId($id) {
        $db = Conexion::conectar();
        $sql = "SELECT * FROM alumnos WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function insertar($data) {
        $db = Conexion::conectar();
        $sql = "INSERT INTO alumnos (nombre, telefono, email, fecha_inscripcion) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        try {
            // Intenta ejecutar la inserción
            $result = $stmt->execute([$data['nombre'], $data['telefono'], $data['email'], $data['fecha_inscripcion']]);
            return $result;
            
        } catch (PDOException $e) {
            // 🛑 MANEJO DE DUPLICIDAD
            if ($e->getCode() === '23000') {
                return 'duplicate'; 
            }
            
            error_log("Error al insertar alumno (general): " . $e->getMessage());
            return false;
        }
    }

    public static function actualizar($id, $data) {
        $db = Conexion::conectar();
        $sql = "UPDATE alumnos SET nombre = ?, telefono = ?, email = ?, fecha_inscripcion = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        
        try {
            return $stmt->execute([$data['nombre'], $data['telefono'], $data['email'], $data['fecha_inscripcion'], $id]);
        } catch (PDOException $e) {
             if ($e->getCode() === '23000') {
                return 'duplicate'; 
            }
            error_log("Error al actualizar alumno: " . $e->getMessage());
            return false;
        }
    }

    public static function eliminar($id) {
        $db = Conexion::conectar();
        $sql = "DELETE FROM alumnos WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>