# Configuración para levantar el sistema en otro equipo

Copiar la carpeta del proyecto **no es suficiente**: el `.env` no viaja (está en
`.gitignore`) y la base de datos vive en un volumen de Docker, no en el código.
Esta es la lista de lo que hay que revisar/reproducir en el equipo nuevo.

## 1. Arquitectura de referencia (este equipo)

| Contenedor | Imagen | Puerto host | Rol |
|---|---|---|---|
| `php_app` | `php:8.2-apache` | `8080` → 80 | Sirve la app (`/var/www/html` = carpeta `php-app/`) |
| `mysql_db` | `mysql:8.0` | `3307` → 3306 | Base de datos `cetecpro` |
| `phpmyadmin_app` | `phpmyadmin/phpmyadmin` | `8081` → 80 | Administración de BD |

Los tres están en la misma red Docker (`workspace_workspace`), y `mysql_db`
responde al hostname **`mysql`** dentro de esa red (alias definido por
docker-compose). Eso es lo que permite que `DB_HOST=mysql` funcione desde
`php_app` sin usar IP.

En el equipo nuevo los nombres de contenedor/red pueden ser distintos —
lo que importa es que el contenedor PHP y el de MySQL compartan red y que
`DB_HOST` apunte al hostname correcto ahí.

## 2. Archivo `www/.env` (obligatorio, no se copia con git)

Crear `www/.env` con el contenido real de la BD del equipo nuevo:

```
DB_HOST=mysql
DB_NAME=cetecpro
DB_USER=root
DB_PASS=<clave de root de MySQL en ESE equipo>
```

- `DB_HOST` debe ser el hostname/alias por el que el contenedor de PHP
  alcanza al contenedor de MySQL en ese equipo (revisar su
  `docker-compose.yml` o hacer `docker network inspect <red>`).
- Si no existe `.env`, `Conexion.php` muere con
  `"Error de conexión: falta configurar DB_PASS"` — pero si `display_errors`
  está en `STDOUT` (ver punto 5), eso no se ve en el navegador, solo en
  `docker logs`.
- Hay una plantilla de referencia en `www/.env.example`.

## 3. Base de datos: exportar aquí, importar allá

Copiar archivos NO copia los datos. Hay que migrar la BD completa:

```powershell
# En ESTE equipo (exportar todo: esquema + datos)
docker exec mysql_db mysqldump -uroot -pCLAVE12345+ cetecpro > cetecpro_dump.sql

# Copiar cetecpro_dump.sql al equipo nuevo, luego ahí (importar):
docker exec -i <contenedor_mysql_del_equipo_nuevo> mysql -uroot -p<clave_ahi> cetecpro < cetecpro_dump.sql
```

Si la base `cetecpro` no existe todavía en el equipo nuevo, crearla primero:
```powershell
docker exec -i <contenedor_mysql_del_equipo_nuevo> mysql -uroot -p<clave_ahi> -e "CREATE DATABASE IF NOT EXISTS cetecpro;"
```

Los archivos `www/database/usuarios.sql` y
`www/database/recibospfs_anulacion.sql` son fragmentos de esquema, no un
dump completo — no confiar en ellos como única fuente para reconstruir la BD.

## 4. Verificar la red Docker en el equipo nuevo

```powershell
docker network inspect <nombre_de_su_red>
```

Confirmar que tanto el contenedor de PHP como el de MySQL aparecen listados
ahí, y que el de MySQL tiene como alias el mismo valor que pusiste en
`DB_HOST` (por defecto en este proyecto: `mysql`).

## 5. Ver el error real (no la pantalla en blanco)

`display_errors` está configurado como `STDOUT` en la imagen PHP — los
errores van al log del contenedor, no al navegador. Para diagnosticar:

```powershell
docker logs <contenedor_php_del_equipo_nuevo> --tail 50
```

Ahí va a aparecer la excepción real (conexión rechazada, tabla no existe,
credenciales inválidas, etc.).

## 6. Checklist rápido

- [ ] `www/.env` existe en el equipo nuevo con las credenciales correctas
- [ ] `DB_HOST` coincide con el hostname real del contenedor MySQL en esa red
- [ ] La base `cetecpro` fue importada completa (dump con datos, no solo
      esquema)
- [ ] `docker exec <php> php -m` incluye `pdo_mysql`
- [ ] `docker logs <php> --tail 50` no muestra errores de conexión al
      recargar una página que use la BD
- [ ] Login (`www/login.php`) funciona con un usuario real de la tabla
      `usuarios` migrada
