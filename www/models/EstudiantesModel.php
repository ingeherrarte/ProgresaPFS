<?php
// models/EstudiantesModel.php

// 🛑 RUTA CORREGIDA: sube al raíz (..), entra a 'config', y encuentra 'Conexion.php'
require_once __DIR__ . "/../config/Conexion.php";

class EstudiantesModel {
    
    // ... (insertar, obtenerPorId, actualizar se mantienen igual) ...

    public static function obtenerTodos() {
        $db = Conexion::conectar();
        $sql = "SELECT id, nombre FROM alumnos ORDER BY nombre ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    }
}
?>