<?php

class BackupModel {

    // Fuera de www/ a propósito (ver docker-compose.yml): esa carpeta la
    // sirve Apache directo, y un backup ahí sería descargable por cualquiera
    // con el link, sin pasar por sesión.
    private const CARPETA_DESTINO = '/var/backups/cetecpro';

    // mysqldump en vez de una implementación propia en PHP: es lo más
    // probado para volcar exactamente lo que hay, incluidas las tablas
    // MyISAM legacy (recibospfs, estudiantespfs) sin reinventar su manejo
    // de tipos y collation.
    public static function generar(): array {
        $host = getenv('DB_HOST') ?: 'mysql';
        $usuario = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS');
        $baseDatos = getenv('DB_NAME') ?: 'cetecpro';

        if (!is_dir(self::CARPETA_DESTINO) && !mkdir(self::CARPETA_DESTINO, 0770, true) && !is_dir(self::CARPETA_DESTINO)) {
            return ['ok' => false, 'error' => 'No se pudo crear la carpeta de respaldos en el servidor.'];
        }

        $archivo = self::CARPETA_DESTINO . '/cetecpro_' . date('Ymd_His') . '.sql';
        $archivoErrores = $archivo . '.err';

        // El stderr de mysqldump (incluida la advertencia normal de "Using a
        // password on the command line") va a un archivo aparte: si se
        // mezclara con 2>&1 en el mismo destino, corrompería el .sql aunque
        // el respaldo haya salido bien.
        // --skip-ssl: el cliente instalado en la imagen (mariadb-client, no
        // el mysqldump de Oracle) rechaza por defecto el certificado
        // autofirmado que MySQL genera solo. No hace falta TLS aquí: el
        // tráfico va entre contenedores dentro de la red interna de Docker,
        // nunca sale a una red pública.
        $comando = 'mysqldump -h ' . escapeshellarg($host)
            . ' -u ' . escapeshellarg($usuario)
            . ' -p' . escapeshellarg($password)
            . ' --skip-ssl'
            . ' ' . escapeshellarg($baseDatos)
            . ' > ' . escapeshellarg($archivo)
            . ' 2>' . escapeshellarg($archivoErrores);

        exec($comando, $salidaNoUsada, $codigo);

        $errorTecnico = trim((string)@file_get_contents($archivoErrores));
        @unlink($archivoErrores);

        if ($codigo !== 0 || !file_exists($archivo) || filesize($archivo) === 0) {
            if (file_exists($archivo)) {
                @unlink($archivo);
            }
            if ($errorTecnico !== '') {
                error_log("Error al generar backup de BD: $errorTecnico");
            }
            return ['ok' => false, 'error' => 'No se pudo generar el respaldo. Intente de nuevo o avise al administrador.'];
        }

        return ['ok' => true, 'archivo' => $archivo];
    }

    // Sube el respaldo ya generado directo a Dropbox con rclone.
    //
    // Se intentó primero copiarlo por SMB a una PC de la LAN (más parecido
    // al script viejo), pero el contenedor no puede alcanzar otros equipos
    // de la red local (solo al propio host — confirmado con fsockopen),
    // y de todas formas esa PC no siempre está encendida. Subir por
    // internet directo a la nube no depende de ninguna PC ni de la red
    // local: solo necesita salida a internet, que el contenedor sí tiene.
    //
    // La cuenta de Dropbox ya está autorizada de antemano (OAuth) en
    // rclone/rclone.conf, montado por volumen — nada de eso se hace aquí.
    public static function subirANube(string $archivoLocal): array {
        $remoto = getenv('RCLONE_REMOTE');
        $destino = getenv('RCLONE_DESTINO');

        if (!$remoto || !$destino) {
            return ['ok' => false, 'error' => 'El respaldo se generó en el servidor, pero falta configurar el destino en la nube (RCLONE_*).'];
        }

        $archivoErrores = $archivoLocal . '.rcloneerr';

        $comando = 'rclone --config /etc/rclone/rclone.conf copy '
            . escapeshellarg($archivoLocal)
            . ' ' . escapeshellarg("$remoto:$destino")
            . ' > /dev/null 2>' . escapeshellarg($archivoErrores);

        exec($comando, $salidaNoUsada, $codigo);

        $errorTecnico = trim((string)@file_get_contents($archivoErrores));
        @unlink($archivoErrores);

        if ($codigo !== 0) {
            if ($errorTecnico !== '') {
                error_log("Error al subir backup a la nube: $errorTecnico");
            }
            return ['ok' => false, 'error' => 'El respaldo se generó en el servidor, pero no se pudo subir a la nube.'];
        }

        return ['ok' => true];
    }
}
?>
