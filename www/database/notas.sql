-- Nota mensual de un estudiante de un grupo (ver grupos.sql): una
-- evaluación por mes de calendario, no por parcial/zona. UNIQUE sobre
-- (grupo_id, idestudiante, anio, mes): volver a guardar la nota del mismo
-- mes actualiza en vez de duplicar (ver NotasModel::guardar).
CREATE TABLE IF NOT EXISTS notas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  idestudiante INT NOT NULL,
  anio SMALLINT NOT NULL,
  mes TINYINT NOT NULL,
  nota DECIMAL(5,2) NOT NULL,
  registrado_por VARCHAR(30) NOT NULL,
  registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_nota (grupo_id, idestudiante, anio, mes),
  KEY idx_grupo_periodo (grupo_id, anio, mes),
  CONSTRAINT fk_nota_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
