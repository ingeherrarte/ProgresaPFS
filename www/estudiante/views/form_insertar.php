<!DOCTYPE html>
<html>
<head>
    <title>Insertar Alumno</title>
</head>
<body>
    <h1>Insertar Alumno</h1>
    <form method="POST" action="index.php?action=insertar">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required><br>
        <label>Teléfono:</label><br>
        <input type="text" name="telefono" required><br>
        <label>Email:</label><br>
        <input type="email" name="email" required><br>
        <label>Fecha de inscripción:</label><br>
        <input type="date" name="fecha_inscripcion" required><br><br>
        <input type="submit" value="Guardar">
    </form>
</body>
</html>