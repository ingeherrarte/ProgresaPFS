-- Hasta ahora estados_cuenta solo guardaba CRÉDITOS (depósitos), porque el
-- importador descartaba la columna de débitos del archivo del banco — era
-- suficiente para conciliar contra recibospfs.deposito, pero no permite ver
-- egresos ni totalizar el estado de cuenta completo (ver "Comparativo
-- Mensual" y "Gastos" en ConciliacionController).
--
-- `tipo` por defecto 'credito' preserva el significado de todo lo ya
-- importado (siempre fueron créditos) sin tocar una sola fila existente.
-- `saldo` es el saldo de la cuenta después de esa línea, tal como lo trae
-- el estado de cuenta — no se usa para sumar nada, solo para consulta.
--
-- La UNIQUE KEY anterior (cuenta, fecha, monto, referencia) se reemplaza
-- por una que incluye `tipo`: antes era imposible que un crédito y un
-- débito del mismo día/monto/referencia coexistieran en la tabla (no se
-- guardaban débitos), ahora sí, y deben poder convivir como dos líneas
-- distintas en vez de que la segunda se descarte como "duplicada".
ALTER TABLE estados_cuenta
  ADD COLUMN tipo ENUM('credito','debito') NOT NULL DEFAULT 'credito' AFTER monto,
  ADD COLUMN saldo DECIMAL(10,2) NULL AFTER descripcion,
  DROP INDEX uq_linea_banco,
  ADD UNIQUE KEY uq_linea_banco (cuenta, fecha, monto, tipo, referencia);
