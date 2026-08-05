<?php
require_once "config/Conexion.php";

class Alumno {
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
        return $stmt->execute([$data['nombre'], $data['telefono'], $data['email'], $data['fecha_inscripcion']]);
    }

    public static function actualizar($id, $data) {
        $db = Conexion::conectar();
        $sql = "UPDATE alumnos SET nombre = ?, telefono = ?, email = ?, fecha_inscripcion = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$data['nombre'], $data['telefono'], $data['email'], $data['fecha_inscripcion'], $id]);
    }

    public static function eliminar($id) {
        $db = Conexion::conectar();
        $sql = "DELETE FROM alumnos WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]);
    }
}
?>