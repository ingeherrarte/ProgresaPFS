-- Miembros de un grupo (ver grupos.sql). `activo` permite quitar un
-- estudiante del grupo sin perder el historial de asistencia/notas que ya
-- tenga registrado con ese grupo (soft-remove, nunca se borra la fila).
CREATE TABLE IF NOT EXISTS grupos_estudiantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grupo_id INT NOT NULL,
  idestudiante INT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  agregado_por VARCHAR(30) NOT NULL,
  agregado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  quitado_por VARCHAR(30) NULL,
  quitado_en DATETIME NULL,
  UNIQUE KEY uq_miembro (grupo_id, idestudiante),
  KEY idx_grupo_activo (grupo_id, activo),
  CONSTRAINT fk_grupoest_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
