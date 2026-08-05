<!DOCTYPE html>
<html>
<head>
    <title>Listado de Alumnos</title>
</head>
<body>
    <h1>Listado de Alumnos</h1>
    <a href="index.php?action=form_insertar">Agregar nuevo alumno</a>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Fecha de Inscripción</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($alumnos as $alumno): ?>
        <tr>
            <td><?= $alumno['id'] ?></td>
            <td><?= $alumno['nombre'] ?></td>
            <td><?= $alumno['telefono'] ?></td>
            <td><?= $alumno['email'] ?></td>
            <td><?= $alumno['fecha_inscripcion'] ?></td>
            <td>
                <a href="index.php?action=form_editar&id=<?= $alumno['id'] ?>">Editar</a> |
                <a href="index.php?action=eliminar&id=<?= $alumno['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este alumno?')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>