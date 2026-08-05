-- Permite adjuntar una foto de la boleta/comprobante cuando el pago del
-- recibo se hace por depósito o transferencia bancaria (mismo campo
-- `deposito`: la UI no distingue entre ambos, ambos usan nodeposito/
-- fechadep/banco).
--
-- Nota: la tabla tiene filas legacy con fechadelpago/fechadep = '0000-00-00'
-- (ver recibospfs_anulacion.sql); con NO_ZERO_DATE en el sql_mode el ALTER
-- falla al reconstruir la tabla. El SET SESSION es solo para esta conexión.
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE recibospfs
  ADD COLUMN foto_deposito VARCHAR(255) NULL;
