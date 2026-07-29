-- Esquema completo de las tablas que usa la app moderna (MVC) de ProgresaPFS/CETECPRO.
-- Pensado para levantar la base de datos en un equipo nuevo desde cero (ver
-- CONFIGURACION_OTRO_EQUIPO.md). Usa CREATE TABLE IF NOT EXISTS, así que
-- correrlo aquí (donde las tablas ya existen con datos) no hace nada.
--
-- No reemplaza un mysqldump completo si necesitas los DATOS (estudiantes,
-- recibos, depósitos ya cargados) — para eso sigue el punto 3 de
-- CONFIGURACION_OTRO_EQUIPO.md. Este archivo solo reconstruye la ESTRUCTURA.
--
-- recibospfs, estudiantespfs y depositos son tablas heredadas del sistema
-- anterior (de ahí el MyISAM/latin1/utf8mb3 en vez de InnoDB/utf8mb4):
-- se documentan aquí tal cual están hoy en producción, sin "corregirlas",
-- porque cambiar el engine/charset de una tabla con datos reales es
-- arriesgado y no es lo que se pidió.

-- ── usuarios ────────────────────────────────────────────────────────────
-- Login del sistema. `rol` controla permisos (usuario/editor/administrador).
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(30) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  rol ENUM('usuario', 'editor', 'administrador') NOT NULL DEFAULT 'editor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── recibospfs ──────────────────────────────────────────────────────────
-- Recibos de pago de estudiantes PFS. `numero` lo asigna la app (no es
-- AUTO_INCREMENT); anulado/motivo_anulacion/anulado_por/fecha_anulacion
-- se agregaron en la modernización para soportar anulación de recibos.
CREATE TABLE IF NOT EXISTS recibospfs (
  numero INT NOT NULL,
  carne VARCHAR(8) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  fechadelpago DATE NOT NULL,
  primero DOUBLE NOT NULL,
  segundo DOUBLE NOT NULL,
  tercero DOUBLE NOT NULL,
  cuarto DOUBLE NOT NULL,
  mesquepaga INT NOT NULL,
  mensualidad DOUBLE NOT NULL,
  inscripcion DOUBLE NOT NULL,
  otro DOUBLE NOT NULL,
  detalle VARCHAR(90) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  efectivo DOUBLE NOT NULL,
  deposito DOUBLE NOT NULL,
  nodeposito BIGINT NOT NULL,
  fechadep DATE NOT NULL,
  cheque DOUBLE NOT NULL,
  nocheque BIGINT NOT NULL,
  banco VARCHAR(40) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  usuario VARCHAR(12) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  horaregistro DATETIME NOT NULL,
  aleatorio INT NOT NULL,
  anulado TINYINT(1) NOT NULL DEFAULT 0,
  motivo_anulacion VARCHAR(200) COLLATE latin1_spanish_ci NOT NULL DEFAULT '',
  anulado_por VARCHAR(12) COLLATE latin1_spanish_ci NOT NULL DEFAULT '',
  fecha_anulacion DATETIME NULL,
  PRIMARY KEY (numero)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_spanish_ci;

-- ── estudiantespfs ──────────────────────────────────────────────────────
-- Ficha completa del estudiante (datos personales, de padres/madre, curso).
CREATE TABLE IF NOT EXISTS estudiantespfs (
  idestudiante INT NOT NULL,
  nombre VARCHAR(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  apellidos VARCHAR(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  nacimiento DATE NOT NULL,
  codcurso INT NOT NULL,
  plan INT NOT NULL,
  jornada INT NOT NULL,
  dpi VARCHAR(13) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  cedula VARCHAR(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  direccion VARCHAR(70) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  email VARCHAR(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  telefonomovil VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  telefonocasa VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  telefonotrabajo VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  ultimoanio YEAR NOT NULL,
  establecimiento VARCHAR(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  pnombre VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  papellidos VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  pcedula VARCHAR(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  ptelefono VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  ptrabajo VARCHAR(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  ptelefonotrabajo VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  pdirecciont VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mnombre VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mapellidos VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mcedula VARCHAR(15) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mtelefono VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mtrabajo VARCHAR(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mtelefonotrabajo VARCHAR(8) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  mdirecciont VARCHAR(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  activo VARCHAR(2) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  fechainscripcion DATE NOT NULL,
  horaregistro DATETIME NOT NULL,
  usuario VARCHAR(12) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  enteradopor VARCHAR(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  observacion TEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_spanish2_ci NOT NULL,
  PRIMARY KEY (idestudiante),
  KEY nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_spanish2_ci;

-- ── depositos ───────────────────────────────────────────────────────────
-- Depósitos bancarios registrados por el módulo de Depósitos.
CREATE TABLE IF NOT EXISTS depositos (
  nodeposito VARCHAR(11) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  fechadep DATE NOT NULL,
  cuenta VARCHAR(30) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  banco VARCHAR(30) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  correspondiente DATE NOT NULL,
  efectivo DOUBLE NOT NULL,
  chpropio DOUBLE NOT NULL,
  chotrobanco DOUBLE NOT NULL,
  responsable VARCHAR(30) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  usuario VARCHAR(8) CHARACTER SET latin1 COLLATE latin1_spanish_ci NOT NULL,
  horaregistro DATETIME NOT NULL,
  UNIQUE KEY nodeposito (nodeposito)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_spanish_ci;

-- ── accesos ─────────────────────────────────────────────────────────────
-- Historial de ingreso/salida de cada usuario (login/logout y expiración
-- por inactividad). Depende de `usuarios`, por eso va al final.
CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  usuario VARCHAR(30) NOT NULL,
  tipo ENUM('ingreso', 'salida') NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY usuario_id (usuario_id),
  CONSTRAINT accesos_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
