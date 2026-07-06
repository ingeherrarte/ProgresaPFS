<?php
// views/RecibosPruebaView.php

class RecibosPruebaView {

    public static function mostrarFormularioIngreso($alumnos, $errores = null, $data = []) {
        
        $meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];

        // Rellenar campos si hubo un error
        $idAlumno = $data['idAlumno'] ?? '';
        $mesPago = $data['mesPago'] ?? '';
        $detalle = $data['detalle'] ?? '';
        $efectivo = $data['efectivo'] ?? '';
        $deposito = $data['deposito'] ?? '';
        $mensaje_exito = is_string($errores) && $errores === 'exito';


        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ingreso de Recibo</title>
        </head>
        <body>
            <h1>Registro de Nuevo Recibo (Prueba)</h1>";
            
            // Mostrar mensaje de éxito
            if ($mensaje_exito) {
                echo "<div style='color: green; border: 1px solid green; padding: 10px; margin-bottom: 15px;'>✅ ¡Recibo guardado exitosamente!</div>";
            }
            
            // Mostrar errores
            if (is_array($errores) && count($errores) > 0) {
                echo "<div style='color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;'>
                        ⚠️ Errores de validación:
                        <ul>";
                foreach ($errores as $error) {
                    echo "<li>$error</li>";
                }
                echo "</ul></div>";
            }
            
            echo "
            <form method='POST' action='index.php?action=guardar'>
                
                <label>Alumno:</label><br>
                <select name='idAlumno' required>";
            
            echo "<option value=''>-- Seleccione un Alumno --</option>";
            foreach ($alumnos as $alumno) {
                $selected = ($alumno['id'] == $idAlumno) ? 'selected' : '';
                echo "<option value='{$alumno['id']}' $selected>{$alumno['id']} - {$alumno['nombre']}</option>";
            }
            echo "</select><br><br>";


            echo "
                <label>Mes de Pago:</label><br>
                <select name='mesPago' required>";
            
            echo "<option value=''>-- Seleccione el Mes --</option>";
            foreach ($meses as $mes) {
                $selected = ($mes == $mesPago) ? 'selected' : '';
                echo "<option value='$mes' $selected>$mes</option>";
            }
            echo "</select><br><br>
                
                <label>Detalle/Concepto:</label><br>
                <input type='text' name='detalle' value='" . htmlspecialchars($detalle) . "' required><br><br>
                
                <label>Monto Efectivo:</label><br>
                <input type='number' name='efectivo' value='" . htmlspecialchars($efectivo) . "' step='0.01' min='0'><br><br>
                
                <label>Monto Depósito:</label><br>
                <input type='number' name='deposito' value='" . htmlspecialchars($deposito) . "' step='0.01' min='0'><br><br>
                
                <p style='color: gray; font-size: 0.9em;'>* Fecha y Hora de Registro, y Número Aleatorio se generan automáticamente.</p>
                
                <input type='submit' value='Guardar Recibo'>
            </form>
        </body>
        </html>";
    }
}
?>