-- Evita duplicar una línea del banco si el mismo estado de cuenta se sube
-- dos veces (pasó en vivo el 2026-10-20: el mismo archivo se subió antes y
-- después de agregar la columna "descripcion", duplicando 51 líneas reales
-- sin que el sistema avisara). MySQL no cuenta dos NULL como iguales en un
-- índice único, así que las líneas sin número de referencia (algunos
-- bancos no lo traen) NUNCA chocan entre sí por esta restricción — solo
-- protege contra reimportar la MISMA línea con la MISMA referencia.
ALTER TABLE estados_cuenta
  ADD UNIQUE KEY uq_linea_banco (cuenta, fecha, monto, referencia);
