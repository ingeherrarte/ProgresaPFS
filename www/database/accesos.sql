-- Registro de ingresos y salidas de cada usuario (login/logout).
-- Se guarda el nombre de usuario tal cual (además del id) para que el
-- historial siga siendo legible aunque la cuenta cambie de nombre o se
-- desactive más adelante, igual que se hace con recibospfs.anulado_por.
CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  usuario VARCHAR(30) NOT NULL,
  tipo ENUM('ingreso', 'salida') NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
