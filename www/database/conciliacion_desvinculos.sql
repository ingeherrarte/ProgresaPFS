-- Bitácora de desvinculaciones (Conciliación de Transferencias).
--
-- Desvincular un recibo BORRA su fila de conciliacion_vinculos (la
-- restricción uq_recibo obliga a eso para poder volver a vincularlo con
-- otra línea), así que sin esta tabla se perdería el rastro de qué recibo
-- estuvo unido a qué línea, quién lo había vinculado y por qué se deshizo.
-- Caso real que lo motivó (2026-11-10): recibos 42785/42786 quedaron
-- cruzados entre dos líneas de Q250 del mismo día en BANRURAL, y el 42787
-- (cuenta BANTRAB) quedó vinculado a una línea de BANRURAL — sin forma de
-- corregirlo desde la pantalla.
--
-- mecanismo: 'manual' = venía de conciliacion_vinculos; 'automatico' =
-- venía de estados_cuenta.recibo_numero (flujo viejo, hoy sin uso pero se
-- soporta por si queda algún histórico).
CREATE TABLE conciliacion_desvinculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recibo_numero INT NOT NULL,
  estado_cuenta_id INT NOT NULL,
  monto_asignado DECIMAL(10,2) NOT NULL,
  mecanismo ENUM('manual','automatico') NOT NULL,
  vinculado_por VARCHAR(30) DEFAULT NULL,
  vinculado_en DATETIME DEFAULT NULL,
  desvinculado_por VARCHAR(30) NOT NULL,
  desvinculado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  motivo VARCHAR(200) NOT NULL,
  INDEX idx_recibo (recibo_numero),
  INDEX idx_estado_cuenta (estado_cuenta_id),
  CONSTRAINT fk_desvinculo_estado_cuenta FOREIGN KEY (estado_cuenta_id) REFERENCES estados_cuenta(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
