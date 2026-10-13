<?php
// Respaldo automático programado (cron en el host, ver docker-compose.yml
// y la línea de crontab correspondiente). Vive fuera de www/ a propósito:
// si quedara ahí, sería un endpoint web sin autenticación que cualquiera
// podría golpear para disparar un backup.
//
// Mismo flujo que el botón manual del panel (BackupModel::ejecutar), solo
// que con origen='automatico' y sin usuario (nadie con sesión lo disparó).

// Rutas absolutas del contenedor, no relativas a __DIR__: aquí cli/ y www/
// no son carpetas hermanas como en el host — cada una se monta por su
// cuenta (ver docker-compose.yml), así que "../www" no resolvería a nada.
require_once "/var/www/html/config/Conexion.php";
require_once "/var/www/html/models/BackupModel.php";

$resultado = BackupModel::ejecutar('automatico', null);

if ($resultado['ok']) {
    echo "[" . date('Y-m-d H:i:s') . "] Backup automático generado y subido correctamente.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Backup automático falló: {$resultado['error']}\n";
exit(1);
?>
