-- Agrega soporte de anulación a recibospfs. Se usan columnas dedicadas en
-- vez de anotar el motivo en `detalle` para que los cierres de caja
-- (día/mes/año) puedan excluir los recibos anulados de las sumas con un
-- simple WHERE anulado = 0.
--
-- Nota: la tabla tiene filas legacy con fechadelpago/fechadep = '0000-00-00'
-- (bug del sistema anterior). Si el sql_mode del servidor incluye
-- NO_ZERO_DATE, el ALTER falla al reconstruir la tabla porque revalida esas
-- filas. Ejecutar primero el SET SESSION (no afecta a otras conexiones ni
-- es un cambio permanente del servidor).
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE recibospfs
  ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN motivo_anulacion VARCHAR(200) NOT NULL DEFAULT '',
  ADD COLUMN anulado_por VARCHAR(12) NOT NULL DEFAULT '',
  ADD COLUMN fecha_anulacion DATETIME NULL;
