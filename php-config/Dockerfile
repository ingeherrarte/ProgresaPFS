FROM php:8.2-apache

# pdo_mysql no viene habilitado en la imagen base; si se instala a mano con
# `docker exec` se pierde cada vez que se recrea el contenedor (pasó el
# 2026-08-12 y tumbó el login de todo el sistema). Se compila aquí para que
# quede parte de la imagen.
RUN docker-php-ext-install pdo_mysql
