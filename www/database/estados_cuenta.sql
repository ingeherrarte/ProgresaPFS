-- Líneas del estado de cuenta bancario, importadas semanalmente (CSV) para
-- conciliar contra recibospfs.deposito. `conciliado`/`recibo_numero` quedan
-- marcados cuando un administrador confirma un grupo de coincidencias en la
-- vista de conciliación (no hay match automático, ver ConciliacionModel).
CREATE TABLE estados_cuenta (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cuenta VARCHAR(20) NOT NULL,
  fecha DATE NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  referencia VARCHAR(255) NULL,
  conciliado TINYINT(1) NOT NULL DEFAULT 0,
  recibo_numero INT NULL,
  importado_por VARCHAR(30) NOT NULL,
  importado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fecha_monto_cuenta (fecha, monto, cuenta),
  INDEX idx_conciliado (conciliado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
