-- Cuál de las dos cuentas propias del colegio (ver DepositosModel::$cuentas)
-- recibió la transferencia/depósito de este recibo. Hasta ahora recibospfs
-- solo registraba `banco` (de dónde vino el dinero, banco del pagador);
-- esta columna es la contraparte -- a cuál cuenta NUESTRA entró.
--
-- Guarda el número de cuenta (ej. '3099203897'), igual que ya hacen
-- depositos.cuenta y estados_cuenta.cuenta -- el nombre del banco se deriva
-- con DepositosModel::bancoDeCuenta(), no se duplica aquí.
--
-- NULL para todo el histórico anterior a este cambio y para recibos sin
-- depósito (100% efectivo/cheque): ConciliacionModel trata NULL como
-- "cuenta desconocida" y sigue buscando coincidencias por fecha+monto en
-- cualquier cuenta, igual que se comportaba antes de esta columna existir.
SET SESSION sql_mode = 'ALLOW_INVALID_DATES';

ALTER TABLE recibospfs
  ADD COLUMN cuenta_destino VARCHAR(30) NULL;
