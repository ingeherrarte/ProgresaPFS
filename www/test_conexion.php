<?php
try {
    $conexion = new PDO("mysql:host=mysql;dbname=cetecpro;charset=utf8", "root", "CLAVE12345+");
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa a la base de datos cetecpro.";
} catch (PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
}