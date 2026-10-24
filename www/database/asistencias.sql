-- Asistencia de un estudiante de un grupo (ver grupos.sql) en una fecha
-- calendario específica. UNIQUE sobre (grupo_id, idestudiante, fecha): si
-- se vuelve a guardar la asistencia del mismo día, se actualiza en vez de
-- duplicar (ver AsistenciaModel::guardar).
CREATE TABLE IF NOT EXISTS asistencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  idestudiante INT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('presente','ausente') NOT NULL,
  registrado_por VARCHAR(30) NOT NULL,
  registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_asistencia (grupo_id, idestudiante, fecha),
  KEY idx_grupo_fecha (grupo_id, fecha),
  CONSTRAINT fk_asistencia_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
