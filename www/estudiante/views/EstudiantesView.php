<?php
// views/EstudiantesView.php

class EstudiantesView {
    

// --- Menú Principal ---
    public static function mostrarMenu() {
        echo "<h1>Menú Principal</h1>";
        echo "<ul>
                 <li><a href='index2.php?controller=estudiantes&action=form_ingresar'>Ingresar Estudiante</a></li>
                 <li><a href='index2.php?controller=estudiantes&action=form_buscar_editar'>Editar Estudiante (Buscar)</a></li>
                 
                 <li><a href='index2.php?controller=recibos&action=form_ingresar'>Ingresar Nuevo Recibo</a></li>
                 
              </ul>";
    }

    // --- Formulario de Ingreso ---
    public static function mostrarFormularioIngreso($errores = [], $data = []) {
        $nombre = $data['nombre'] ?? '';
        $telefono = $data['telefono'] ?? '';
        $email = $data['email'] ?? '';
        
        echo "<h2>Ingresar Estudiante</h2>";
        if ($errores) {
            echo "<ul style='color:red;'>";
            foreach ($errores as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
        }
        
        echo "<form method='POST' action='index.php?action=guardar'>
                <label>Nombre:</label><br>
                <input type='text' name='nombre' value='" . htmlspecialchars($nombre) . "'><br>
                <label>Teléfono:</label><br>
                <input type='text' name='telefono' value='" . htmlspecialchars($telefono) . "'><br>
                <label>Email:</label><br>
                <input type='email' name='email' value='" . htmlspecialchars($email) . "'><br><br>
                <input type='submit' value='Guardar'>
              </form>";
    }

    // --- Formulario de Búsqueda ---
    public static function mostrarFormularioBusqueda($resultados = null, $mensaje = null) {
        echo "<h2>Buscar Estudiante para Editar</h2>";
        
        if ($mensaje) {
            echo "<p style='color:blue;'>$mensaje</p>";
        }
        
        echo "<form method='GET' action='index.php'>
                <input type='hidden' name='action' value='buscar_estudiante'>
                <label>Buscar por Nombre o ID:</label><br>
                <input type='text' name='query' required><br><br>
                <input type='submit' value='Buscar'>
              </form>";

        if ($resultados && is_array($resultados)) {
            echo "<h3>Resultados:</h3>";
            echo "<ul>";
            foreach ($resultados as $estudiante) {
                echo "<li>ID: {$estudiante['id']} - Nombre: {$estudiante['nombre']} 
                      <a href='index.php?action=form_editar&id={$estudiante['id']}' style='margin-left: 20px;'>[Editar]</a></li>";
            }
            echo "</ul>";
        } elseif ($resultados === []) {
             echo "<p style='color:red;'>No se encontraron estudiantes con ese nombre o ID.</p>";
        }
    }
    
    // 🛑 ESTA ES LA FUNCIÓN FALTANTE QUE CAUSA EL ERROR
    public static function mostrarFormularioEditar($estudiante, $errores = []) {
        echo "<h2>Editar Estudiante</h2>";
        if ($errores) {
            echo "<ul style='color:red;'>";
            foreach ($errores as $error) {
                echo "<li>$error</li>";
            }
            echo "</ul>";
        }
        echo "<form method='POST' action='index.php?action=actualizar'>
                <input type='hidden' name='id' value='" . htmlspecialchars($estudiante['id']) . "'>
                <label>Nombre:</label><br>
                <input type='text' name='nombre' value='" . htmlspecialchars($estudiante['nombre']) . "'><br>
                <label>Teléfono:</label><br>
                <input type='text' name='telefono' value='" . htmlspecialchars($estudiante['telefono']) . "'><br>
                <label>Email:</label><br>
                <input type='email' name='email' value='" . htmlspecialchars($estudiante['email']) . "'><br><br>
                <input type='submit' value='Actualizar'>
              </form>";
    }
}
?>