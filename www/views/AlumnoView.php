<?php
class AlumnoView {
    
    public static function mostrar($alumnos) {
        echo "<h1>Listado de Alumnos</h1>";
        echo "<table border='1'>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Fecha de Inscripción</th>
                </tr>";
        foreach ($alumnos as $alumno) {
            echo "<tr>
                    <td>{$alumno['id']}</td>
                    <td>{$alumno['nombre']}</td>
                    <td>{$alumno['telefono']}</td>
                    <td>{$alumno['email']}</td>
                    <td>{$alumno['fecha_inscripcion']}</td>
                  </tr>";
        }
        echo "</table>";
    }
}