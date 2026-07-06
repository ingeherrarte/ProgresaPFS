<?php
// models/RecibosPruebaModel.php

// 🛑 RUTA CORREGIDA: sube al raíz (..), entra a 'config', y encuentra 'Conexion.php'
require_once __DIR__ . "/../config/Conexion.php"; 

class RecibosPruebaModel {
    
    public static function insertar($data) {
// ... el resto del código es el mismo
        $db = Conexion::conectar();
        
        $sql = "INSERT INTO recibosPrueba 
                (idAlumno, mesPago, detalle, efectivo, deposito, fechaHoraRegistro, numeroRecibo, numeroAleatorio) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)";
                
        $stmt = $db->prepare($sql);
        
        try {
            $stmt->execute([
                $data['idAlumno'], 
                $data['mesPago'], 
                $data['detalle'], 
                $data['efectivo'], 
                $data['deposito'],
                $data['numeroRecibo'],
                $data['numeroAleatorio']
            ]);
            return true;
        } catch (PDOException $e) {
            // 🛑 ESTO DEBE ESTAR ACTIVO PARA VER ERRORES
            error_log("Error al insertar recibo: " . $e->getMessage()); 
            // 🛑 ADEMÁS, PUEDES MOSTRAR EL ERROR DIRECTO POR AHORA:
            // die("Error SQL: " . $e->getMessage()); 
            return false;
        }
    }
}
?>