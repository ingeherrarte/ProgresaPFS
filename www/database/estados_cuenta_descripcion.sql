-- Agrega la columna de descripción del banco (ej. "DEBITO IFT BL",
-- "CTA. DESTINO:2870011711|CETECPRO DIRECCION BE") a estados_cuenta.
-- Hasta ahora solo se guardaba fecha/monto/referencia del estado de
-- cuenta — la descripción ayuda al admin a reconocer de un vistazo qué
-- tipo de movimiento es una línea candidata al elegir cuál usar para
-- verificar un recibo (ver ConciliacionController::PRESETS).
ALTER TABLE estados_cuenta
  ADD COLUMN descripcion VARCHAR(255) NULL AFTER referencia;
