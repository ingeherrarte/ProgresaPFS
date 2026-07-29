-- Agrega roles a usuarios:
--   usuario       -> acceso básico, NO puede anular recibos.
--   editor        -> acceso actual (sin cambios de comportamiento).
--   administrador -> además de lo de editor, puede gestionar usuarios.
-- DEFAULT 'editor' para que las cuentas existentes sigan funcionando igual
-- que hoy sin necesidad de reasignarles rol manualmente.
ALTER TABLE usuarios
  ADD COLUMN rol ENUM('usuario', 'editor', 'administrador') NOT NULL DEFAULT 'editor';
