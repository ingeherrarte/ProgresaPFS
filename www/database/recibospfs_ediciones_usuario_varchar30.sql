-- recibospfs_ediciones.usuario quedó en VARCHAR(12) desde el diseño inicial
-- (recibospfs_ediciones.sql), copiando la convención legacy de
-- recibospfs.usuario/anulado_por, pero usuarios.usuario permite hasta 100
-- caracteres. Cualquier usuario con nombre de más de 12 caracteres no podía
-- guardar NINGUNA edición de recibo: el INSERT a la bitácora fallaba con
-- "Data too long for column 'usuario'" (detectado con un usuario de prueba
-- de 14 caracteres, ningún usuario real llegaba todavía a ese largo).
ALTER TABLE recibospfs_ediciones
  MODIFY usuario VARCHAR(30) NOT NULL;
