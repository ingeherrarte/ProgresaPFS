-- Vínculo manual entre una línea del estado de cuenta y uno o más recibos.
-- El cruce automático (ConciliacionModel::verificarGrupo) solo funciona
-- cuando un recibo y una línea comparten fecha (±2 días por ACH) y EXACTO
-- el mismo monto, usando estados_cuenta.recibo_numero/conciliado (1 línea =
-- 1 recibo). Esto no alcanza para dos casos reales:
--   - Una transferencia de un período muy anterior al que se está
--     conciliando, donde el admin busca y vincula a mano.
--   - Un solo depósito que cubre varios recibos (ej. una empresa paga
--     Q650 por 2 estudiantes, Q325 cada uno): una línea necesita poder
--     quedar ligada a VARIOS recibos, cosa que la columna escalar
--     recibo_numero no puede representar.
--
-- Una línea con recibo_numero YA fijado (vía el flujo automático) no debe
-- usar esta tabla — ya está completamente explicada por ese mecanismo. Esta
-- tabla es exclusiva del flujo de vinculación MANUAL (ConciliacionModel::
-- vincularManual), para líneas con recibo_numero IS NULL.
CREATE TABLE conciliacion_vinculos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estado_cuenta_id INT NOT NULL,
  recibo_numero INT NOT NULL,
  monto_asignado DECIMAL(10,2) NOT NULL,
  vinculado_por VARCHAR(30) NOT NULL,
  vinculado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_recibo (recibo_numero),
  INDEX idx_estado_cuenta (estado_cuenta_id),
  CONSTRAINT fk_vinculo_estado_cuenta FOREIGN KEY (estado_cuenta_id) REFERENCES estados_cuenta(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
