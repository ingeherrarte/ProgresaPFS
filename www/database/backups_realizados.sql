-- Bitácora de cada intento de respaldo (manual desde el panel o automático
-- por cron), para mostrar en Administración un historial con fecha/hora y
-- si llegó a guardarse en disco del servidor y/o se subió a la nube.
--
-- Se registra el intento completo, incluso si falla (disco_local/nube en 0
-- con detalle_error), para que un backup fallido quede visible en vez de
-- desaparecer en el log de PHP sin que nadie se entere.
CREATE TABLE IF NOT EXISTS backups_realizados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  archivo VARCHAR(100) NULL,
  origen ENUM('manual','automatico') NOT NULL,
  usuario VARCHAR(30) NULL,
  disco_local TINYINT(1) NOT NULL DEFAULT 0,
  nube TINYINT(1) NOT NULL DEFAULT 0,
  detalle_error VARCHAR(255) NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
