<?php
// models/EstudiantesModel.php

require_once "Conexion.php"; // Asegúrate de que esta ruta sea correcta

class EstudiantesModel {
    
    public static function insertar($nombre, $telefono, $email) {
        $db = Conexion::conectar();
        $sql = "INSERT INTO alumnos (nombre, telefono, email, fecha_inscripcion) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$nombre, $telefono, $email]);
    }

    // 🛑 ESTA ES LA FUNCIÓN FALTANTE QUE CAUSA EL ERROR
    public static function obtenerPorId($id) {
        $db = Conexion::conectar();
        $sql = "SELECT id, nombre FROM alumnos ORDER BY nombre ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Devuelve todos los estudiantes
    }

    public static function actualizar($id, $nombre, $telefono, $email) {
        $db = Conexion::conectar();
        $sql = "UPDATE alumnos SET nombre = ?, telefono = ?, email = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$nombre, $telefono, $email, $id]);
    }

    public static function buscarPorNombreOId($query) {
        $db = Conexion::conectar();
        
        // 1. Intentar buscar por ID si es numérico
        if (is_numeric($query)) {
            $sql = "SELECT * FROM alumnos WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$query]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? [$resultado] : []; 
        }

        // 2. Buscar por Nombre (usando LIKE)
        $query_like = "%" . $query . "%";
        $sql = "SELECT * FROM alumnos WHERE nombre LIKE ? LIMIT 10"; 
        $stmt = $db->prepare($sql);
        $stmt->execute([$query_like]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>