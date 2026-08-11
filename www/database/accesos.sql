-- Registro de accesos (ingreso/salida) por usuario, usado por el panel de
-- Administración. Se inserta un registro en cada login, logout manual y
-- salida automática por expiración de sesión (ver helpers/Auth.php).
CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  usuario VARCHAR(30) NOT NULL,
  tipo ENUM('ingreso','salida') NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (usuario_id),
  CONSTRAINT accesos_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
