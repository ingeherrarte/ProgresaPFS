-- Tercera vez que aparece este mismo bug (ver recibospfs_ediciones.sql y
-- estados_cuenta.sql / recibospfs_estado_transferencia.sql): recibospfs.usuario
-- y anulado_por quedaron en VARCHAR(12) desde el esquema legacy, pero
-- usuarios.usuario permite hasta 100 caracteres. Detectado con un usuario
-- de prueba de 14 caracteres al intentar GUARDAR un recibo nuevo (no solo
-- editarlo): "Data too long for column 'usuario'". Ningún usuario real
-- actual llega a 12 (el más largo es "aescalante", 10), pero es la misma
-- bomba de tiempo que ya se corrigió en las otras dos tablas.
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE recibospfs
  MODIFY usuario VARCHAR(30) NOT NULL,
  MODIFY anulado_por VARCHAR(30) NOT NULL;
