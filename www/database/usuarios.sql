-- Tabla de credenciales para el login del módulo de recibos PFS.
-- No existía ninguna tabla de usuarios/contraseñas en el sistema legacy;
-- el campo `recibospfs.usuario` se llenaba con un valor fijo ('eduardo').
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ejemplo para crear un usuario nuevo (generar el hash con password_hash() en PHP):
-- INSERT INTO usuarios (usuario, password_hash, nombre_completo) VALUES ('usuario', '$2y$10$...', 'Nombre Completo');
