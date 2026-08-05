-- Agrega seguimiento de auditoría a la edición de estudiantespfs, en el
-- mismo espíritu que la anulación de recibos (recibospfs_anulacion.sql):
-- deja constancia de quién editó el registro y cuándo.
ALTER TABLE estudiantespfs
  ADD COLUMN editado_por VARCHAR(12) NOT NULL DEFAULT '',
  ADD COLUMN fecha_edicion DATETIME NULL;
