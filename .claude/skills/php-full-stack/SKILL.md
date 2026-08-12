---
name: php-full-stack
description: Desarrollo full-stack para este proyecto (ProgresaPFS/CETECPRO) — PHP MVC, MySQL y los contenedores Docker que lo sirven. Úsala para agregar/modificar funcionalidad PHP, tocar esquema o datos de MySQL, o cambiar la configuración de docker-compose/php-config.
---

Actúas como desarrollador full-stack senior de este proyecto específico. Sigue las convenciones ya establecidas en el repo en vez de imponer patrones genéricos.

## Arquitectura del stack (docker-compose.yml en la raíz)

- `php` (contenedor `php`): imagen construida desde `php-config/Dockerfile` (NO uses `image: php:8.2-apache` directo — cualquier extensión o config debe hornearse ahí, no instalarse a mano con `docker exec`, porque se pierde en cada recreación del contenedor. Ya pasó una vez con `pdo_mysql` y tumbó producción).
- `mysql` (contenedor `mysql`): MySQL 8.0, root/root123, BD principal `cetecpro`.
- `phpmyadmin`, `portainer`: administración.
- Config de PHP (`upload_max_filesize`, `post_max_size`, etc.) vive en `php-config/uploads.ini`, montada por volumen — para cambios de *extensiones* usa el Dockerfile; para cambios de *ini* basta el volumen (no requiere rebuild, solo `docker compose up -d php`... pero igual valida que no dependa de algo que solo existe en el contenedor viejo).
- Antes de recrear el contenedor `php` por cualquier motivo, corre `docker exec php php -m` y compara contra el estado esperado — si algo se instaló manualmente y no está en el Dockerfile, se va a perder.

## Código de la app (`www/`)

Patrón MVC simple, sin framework:
- `www/controllers/*Controller.php`: un `handle($action)` con switch por acción (`form`, `guardar`, `buscar`, etc.). Auth vía `Auth::requerirSesion()` / `Auth::requerirSesionJson()` (helper en `www/helpers/Auth.php`) al inicio de cada acción que lo requiera.
- `www/models/*Model.php`: toda la validación y SQL vive aquí (métodos estáticos, PDO con prepared statements siempre — nunca interpolar `$_POST`/`$_GET` en SQL).
- `www/views/*View.php`: HTML embebido en métodos estáticos, `htmlspecialchars()` en cualquier salida de datos de usuario.
- Helpers compartidos en `www/helpers/`: `Auth.php` (sesión, expiración por inactividad de 15 min, registro en tabla `accesos`), `SubidaImagen.php` (subida de fotos con validación de tipo real vía `getimagesize()`, hash SHA-256 para evitar duplicados, nombre generado en servidor — reutilízalo para cualquier nueva subida de imagen, no dupliques la lógica).
- Entry points en `www/*.php` (ej. `depositos.php`, `estudiantespfs.php`) son shims delgados que instancian el controlador correspondiente.

## MySQL / esquema

- BD real de producción: `cetecpro`, corriendo en el contenedor `mysql`. Consulta el esquema real con `docker exec mysql mysql -u root -proot123 cetecpro -e "SHOW CREATE TABLE tabla\G"` — **no confíes ciegamente en los `.sql` de `www/database/`**, históricamente se han desactualizado respecto a lo que corre de verdad (pasó con `usuarios`/`accesos`).
- `www/database/*.sql`: cada archivo es un fragmento de migración documentado (CREATE TABLE o ALTER puntual con comentario explicando el porqué), no un dump completo. Si agregas una columna o tabla nueva en producción, agrega también el `.sql` correspondiente aquí para que quede documentado — y si tocas una tabla existente, revisa si ya hay un `.sql` que describirla y actualízalo en vez de dejarlo desincronizado.
- Tablas legacy (`recibospfs`, `estudiantespfs`) son MyISAM/utf8mb3 con datos históricos con fechas `0000-00-00` — cualquier operación que las toque (ALTER, INSERT masivo) puede necesitar `SET SESSION sql_mode = 'ALLOW_INVALID_DATES';` primero, o va a fallar.
- Antes de cualquier operación destructiva sobre datos reales (TRUNCATE, DROP, sobrescritura masiva), saca un `mysqldump` de respaldo primero y verifica con una query de "qué se perdería" (LEFT JOIN buscando huérfanos) antes de ejecutar.
- Passwords/collations reales del proyecto: ver `www/.env` (gitignored) para las credenciales activas; no asumas los valores por defecto de `docker-compose.yml` sin confirmar cuál `.env` está en uso.

## Flujo de trabajo esperado

1. Antes de cambiar algo, lee el archivo real (controller + model + view) en vez de asumir estructura — cada módulo tiene sus particularidades (ver comentarios explicativos en el código, suelen documentar bugs legacy que ya se corrigieron y por qué se hizo algo de cierta forma).
2. `docker exec php php -l archivo.php` después de cualquier edición PHP, antes de dar por terminado.
3. Si el cambio toca subida de archivos o límites, recuerda que hay DOS capas de límite: `upload_max_filesize`/`post_max_size` de PHP (a nivel de contenedor) y la constante `TAMANO_MAXIMO_*` de la app — deben ir sincronizados o el más bajo gana en silencio.
4. Commits en español, formato imperativo breve ("Agrega...", "Corrige...", "Permite..."), con el cuerpo explicando el *por qué* cuando no sea obvio. Push directo a `master` (no hay ramas de feature en este repo).
