-- Permite marcar una línea del estado de cuenta como "excluida" (ej. un
-- crédito que el banco aplicó por error y luego retiró) sin borrar ni
-- alterar el monto/fecha/referencia original — esos siguen siendo
-- exactamente lo que dice el banco. Mismo patrón que la anulación de
-- recibos (ver recibospfs_anulacion.sql): columnas dedicadas, nunca se
-- toca el detalle real de la línea.
--
-- `reactivado_por`/`fecha_reactivacion` quedan separados de
-- `excluido_por`/`fecha_exclusion` (no se sobrescriben) para que, si una
-- línea se excluye y luego se reactiva, quede la historia completa de
-- ambos eventos en vez de perder el primero.
--
-- `excluido_por`/`reactivado_por` en VARCHAR(30) desde el inicio (no 12,
-- ver recibospfs_ediciones_usuario_varchar30.sql — usuarios.usuario
-- permite hasta 100 caracteres).
ALTER TABLE estados_cuenta
  ADD COLUMN excluido TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN motivo_exclusion VARCHAR(255) NULL,
  ADD COLUMN excluido_por VARCHAR(30) NULL,
  ADD COLUMN fecha_exclusion DATETIME NULL,
  ADD COLUMN reactivado_por VARCHAR(30) NULL,
  ADD COLUMN fecha_reactivacion DATETIME NULL,
  ADD KEY idx_excluido (excluido);
