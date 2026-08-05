-- Registro compartido de imágenes ya subidas (boletas de depósito y
-- comprobantes de recibos), para impedir que el mismo archivo (por
-- contenido, no por nombre) se adjunte más de una vez en cualquiera de los
-- dos formularios. `hash` es el SHA-256 del contenido del archivo y es la
-- llave primaria: la propia base de datos evita duplicados incluso ante
-- solicitudes concurrentes con la misma imagen.
CREATE TABLE IF NOT EXISTS imagenes_subidas (
  hash CHAR(64) NOT NULL PRIMARY KEY,
  archivo VARCHAR(255) NOT NULL,
  contexto VARCHAR(30) NOT NULL,
  referencia VARCHAR(50) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
