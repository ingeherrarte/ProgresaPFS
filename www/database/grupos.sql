-- Base de Asistencia y Notas: un "grupo" es la lista real de estudiantes
-- de una combinación Diplomado (codcurso) + Plan/día (1=Sábado,
-- 2=Domingo, 3=Diario) + Jornada (1=Matutina, 2=Vespertina).
--
-- No se construye a partir de estudiantespfs.activo a propósito: ese campo
-- no se mantiene de forma confiable (2026-10-23: 3,646 marcados como
-- activos, cuando el real por jornada son decenas, no cientos). La lista
-- real se arma a mano (ver grupos_estudiantes.sql) y vive independiente de
-- ese campo.
CREATE TABLE IF NOT EXISTS grupos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codcurso INT NOT NULL,
  plan INT NOT NULL,
  jornada INT NOT NULL,
  creado_por VARCHAR(30) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grupo (codcurso, plan, jornada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
