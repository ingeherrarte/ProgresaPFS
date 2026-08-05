-- Tabla de alumnos para el CRUD de AlumnosModel/AlumnosController.
-- El email es UNIQUE porque el modelo depende del error 23000 (duplicidad)
-- de MySQL para mostrar el mensaje "el email ya existe".
CREATE TABLE IF NOT EXISTS alumnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  telefono VARCHAR(20) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  fecha_inscripcion DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
