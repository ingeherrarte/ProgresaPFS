<?php
// views/EstudiantesView.php

class EstudiantesView {
    
    public static function mostrarFormularioInsertar($data = [], $error = null) {
        $nombre = $data['nombre'] ?? '';
        $telefono = $data['telefono'] ?? '';
        $email = $data['email'] ?? '';

        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Insertar Estudiante</title>
        </head>
        <body>
            <h1>Registro de Nuevo Estudiante</h1>";
            
            if ($error) {
                echo "<div style='color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;'>⚠️ Error: " . htmlspecialchars($error) . "</div>";
            }
            
            echo "
            <form method='POST' action='index.php?controller=estudiantes&action=guardar'>
                <label>Nombre:</label><br>
                <input type='text' name='nombre' value='" . htmlspecialchars($nombre) . "' required><br>
                
                <label>Teléfono:</label><br>
                <input type='text' name='telefono' value='" . htmlspecialchars($telefono) . "'><br>
                
                <label>Email:</label><br>
                <input type='email' name='email' value='" . htmlspecialchars($email) . "' required><br><br>
                
                <input type='submit' value='Guardar Estudiante'>
                <a href='index.php?controller=estudiantes&action=listar'>Cancelar</a>
            </form>
        </body>
        </html>";
    }
    
    // (Por ahora, omitimos el método de listado para simplificar)
}
?>