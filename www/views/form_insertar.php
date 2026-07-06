<!DOCTYPE html>
<html>
<head>
    <title>Insertar Alumno</title>
</head>
<body>
    <h1>Insertar Alumno</h1>
    <?php 
    // 1. Mostrar el mensaje de error si existe
    if (isset($error)): ?>
        <div style="color: red; border: 1px solid red; padding: 10px; margin-bottom: 15px;">
            ⚠️ Error: <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

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