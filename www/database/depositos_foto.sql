-- Permite adjuntar una foto de la boleta física a cada depósito.
--
-- Nota: la tabla tiene filas legacy con fechadep = '0000-00-00' (igual que
-- recibospfs); con NO_ZERO_DATE en el sql_mode el ALTER falla al
-- reconstruir la tabla. El SET SESSION es solo para esta conexión.
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE depositos
  ADD COLUMN foto_boleta VARCHAR(255) NULL;
