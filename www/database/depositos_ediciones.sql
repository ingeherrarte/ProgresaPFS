-- Bitácora de ediciones de depósitos, para el formulario de Editar Depósito
-- del panel de Administración (solo rol administrador). Mismo criterio que
-- recibospfs_ediciones.sql: un depósito es un documento financiero, así que
-- cuando un monto cambia (ej. error de digitación 1900 -> 1975) hay que
-- poder reconstruir el valor original, no solo quedarse con el último.
--
-- Sin FOREIGN KEY hacia depositos a propósito: esa tabla es MyISAM (legacy)
-- y MySQL no soporta llaves foráneas hacia MyISAM. El índice sobre
-- `nodeposito` es lo que sostiene la consulta del historial por depósito.
CREATE TABLE IF NOT EXISTS depositos_ediciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nodeposito VARCHAR(11) NOT NULL,
  campo VARCHAR(30) NOT NULL,
  valor_anterior VARCHAR(250) NOT NULL DEFAULT '',
  valor_nuevo VARCHAR(250) NOT NULL DEFAULT '',
  motivo VARCHAR(200) NOT NULL,
  usuario VARCHAR(30) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_nodeposito_fecha (nodeposito, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
