FROM php:8.2-apache

# pdo_mysql no viene habilitado en la imagen base; si se instala a mano con
# `docker exec` se pierde cada vez que se recrea el contenedor (pasó el
# 2026-08-12 y tumbó el login de todo el sistema). Se compila aquí para que
# quede parte de la imagen.
RUN docker-php-ext-install pdo_mysql

# mysqldump: usado por BackupModel para generar el respaldo de la base de
# datos desde el panel de Administración (exec() invocando el binario, no
# una librería PHP) — la imagen base no lo trae.
#
# rclone: usado por BackupModel para subir el respaldo ya generado
# directo a Dropbox (cuenta configurada en rclone/rclone.conf, montado por
# volumen). Reemplaza el intento inicial de copiarlo por SMB a una PC de la
# LAN: el contenedor no puede alcanzar otros equipos de la red local (solo
# al propio host), y además esa PC no siempre está encendida. Subir por
# internet directo a la nube evita ambos problemas.
RUN apt-get update && apt-get install -y --no-install-recommends default-mysql-client rclone \
    && rm -rf /var/lib/apt/lists/*
