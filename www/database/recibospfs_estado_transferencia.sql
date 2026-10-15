-- Conciliación de recibos pagados por depósito/transferencia contra el
-- estado de cuenta del banco. Reutiliza el mismo campo `deposito` que ya
-- existe (ver recibospfs_foto.sql: no distingue depósito físico de
-- transferencia, ambos usan nodeposito/fechadep/banco).
--
-- Todo recibo con deposito > 0 nace en estado 'pendiente' y solo un
-- administrador puede pasarlo a 'verificado' (ver ConciliacionController).
-- Los recibos 100% en efectivo/cheque quedan en 'pendiente' por default
-- pero no se muestran en la UI de conciliación (se filtra por deposito > 0).
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE recibospfs
  ADD COLUMN estado_transferencia ENUM('pendiente','verificado') NOT NULL DEFAULT 'pendiente',
  ADD COLUMN verificado_por VARCHAR(30) NULL,
  ADD COLUMN fecha_verificacion DATETIME NULL;
