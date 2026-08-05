<?php
// Carga variables de entorno desde un archivo .env (formato CLAVE=valor) sin
// dependencias externas. Las variables ya definidas en el entorno (ej. por
// Docker/Apache) tienen prioridad y no se sobrescriben.
function cargarEnv(string $ruta): void {
    if (!is_readable($ruta)) {
        return;
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim(trim($valor), "\"'");
        if ($clave !== '' && getenv($clave) === false) {
            putenv("$clave=$valor");
        }
    }
}
?>
