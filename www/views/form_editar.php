<!DOCTYPE html>
<html>
<head>
    <title>Editar Alumno</title>
</head>
<body>
    <h1>Editar Alumno</h1>
    <form method="POST" action="index.php?action=editar">
        <input type="hidden" name="id" value="<?= $alumno['id'] ?>">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" value="<?= $alumno['nombre'] ?>" required><br>
        <label>Teléfono:</label><br>
        <input type="text" name="telefono" value="<?= $alumno['telefono'] ?>" required><br>
        <label>Email:</label><br>
        <input type="email" name="email" value="<?= $alumno['email'] ?>" required><br>
        <label>Fecha de inscripción:</label><br>
        <input type="date" name="fecha_inscripcion" value="<?= $alumno['fecha_inscripcion'] ?>" required><br><br>
        <input type="submit" value="Actualizar">
    </form>
</body>
</html>