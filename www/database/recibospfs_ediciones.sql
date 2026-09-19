-- Bitácora de ediciones de recibos, para el formulario de Editar Recibo del
-- panel de Administración (solo rol administrador).
--
-- Se usa una tabla aparte en vez de columnas `editado_por`/`fecha_edicion`
-- sobre recibospfs (como sí se hizo en estudiantespfs_edicion.sql) porque un
-- recibo es un documento financiero: cuando un monto cambia hay que poder
-- reconstruir el valor original para justificar un descuadre en el cierre de
-- caja. Dos columnas se sobrescriben en cada edición y solo conservan al
-- último editor; aquí queda una fila por campo modificado, con el antes y el
-- después, así que la historia completa del recibo es reconstruible.
--
-- No hay FOREIGN KEY hacia recibospfs a propósito: esa tabla es MyISAM
-- (legacy) y MySQL no soporta llaves foráneas hacia MyISAM. El índice sobre
-- `numero` es lo que sostiene la consulta del historial por recibo.
CREATE TABLE IF NOT EXISTS recibospfs_ediciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT NOT NULL,
  campo VARCHAR(30) NOT NULL,
  valor_anterior VARCHAR(250) NOT NULL DEFAULT '',
  valor_nuevo VARCHAR(250) NOT NULL DEFAULT '',
  motivo VARCHAR(200) NOT NULL,
  usuario VARCHAR(12) NOT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_numero_fecha (numero, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
