-- depositos.usuario quedó en VARCHAR(8) desde el esquema legacy, mientras
-- que todas las demás tablas del sistema (recibospfs, estudiantespfs,
-- accesos) usan VARCHAR(12) o más para la misma columna. Cualquier usuario
-- con nombre de más de 8 caracteres (ej. "aescalante", 10) no podía guardar
-- NINGÚN depósito: el INSERT fallaba con "Data too long for column
-- 'usuario'" en todos los intentos.
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE depositos
  MODIFY usuario VARCHAR(12) NOT NULL;
