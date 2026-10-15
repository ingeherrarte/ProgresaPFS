<?php

// Sistema de tema oscuro/claro compartido por todas las vistas. Un solo
// localStorage key ('cetecpro_tema') y un solo data-theme en <html>, así
// la preferencia es una sola en todo el sitio, no por página.
class Tema {

    // Script temprano: se incluye en <head>, ANTES de los <style> de la
    // página, para fijar data-theme desde localStorage antes del primer
    // pintado (evita el parpadeo de un tema para luego cambiar al otro).
    public static function scriptInicial(): void {
        ?>
        <script>
            try {
                const temaGuardado = localStorage.getItem('cetecpro_tema');
                if (temaGuardado === 'dark' || temaGuardado === 'light') {
                    document.documentElement.setAttribute('data-theme', temaGuardado);
                }
            } catch (e) {}
        </script>
        <?php
    }

    // Tokens base de "chrome" genérico (fondo, tarjeta, texto, acento,
    // bordes, tablas) — van primero dentro del <style> de cada página.
    // Las vistas con necesidades propias (p.ej. Dashboard con tokens de
    // gráficas/KPI) agregan su propio bloque :root después de este, solo
    // con sus tokens extra.
    public static function tokensBase(): void {
        ?>
        :root {
            --bg-pagina: #f0f2f5; --bg-tarjeta: #fff; --texto: #333; --texto-muted: #555;
            --acento: #1a237e; --borde-input: #ccc; --sombra-tarjeta: rgba(0,0,0,.1);
            --tabla-borde: #ddd; --tabla-header-bg: #1a237e; --tabla-header-texto: #fff;
            --sin-datos: #888; --fila-alterna: #f5f7ff;
        }
        <?php self::bloqueOscuro(); ?>
        <?php
    }

    // Mismo mecanismo de cascada que Dashboard: oscuro automático por
    // preferencia de sistema, salvo que el toggle manual (data-theme) ya
    // haya decidido explícitamente — y data-theme="dark" gana siempre.
    private static function bloqueOscuro(): void {
        ?>
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { <?php self::tokensOscuros(); ?> }
        }
        :root[data-theme="dark"] { <?php self::tokensOscuros(); ?> }
        <?php
    }

    private static function tokensOscuros(): void {
        ?>
        --bg-pagina: #15171c; --bg-tarjeta: #1f232b; --texto: #d7d9de; --texto-muted: #9aa0ab;
        --acento: #8c9eff; --borde-input: #3a3f4b; --sombra-tarjeta: rgba(0,0,0,.4);
        --tabla-borde: #3a3f4b; --tabla-header-bg: #2b3350; --tabla-header-texto: #e8e9ec;
        --sin-datos: #8a909b; --fila-alterna: #262b38;
        <?php
    }

    // CSS del botón — se agrega una vez dentro del <style> de cada página.
    public static function estiloBoton(): void {
        ?>
        .tema-toggle {
            background: none; border: 1px solid var(--borde-input); border-radius: 4px;
            color: var(--texto); font-size: 12.5px; padding: 5px 10px; margin-left: 16px;
            cursor: pointer; font-family: inherit;
        }
        <?php
    }

    // Botón — se coloca junto a los demás links de la barra superior/nav
    // de cada página (o, si la página no tiene barra como Login, flotante).
    public static function boton(): void {
        ?>
        <button type="button" class="tema-toggle" onclick="alternarTema()">🌓 Tema</button>
        <?php
    }

    // Función JS de toggle — una vez por página, junto al resto del <script>.
    public static function script(): void {
        ?>
        <script>
            function alternarTema() {
                const raiz = document.documentElement;
                const prefiereOscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const actual = raiz.getAttribute('data-theme') || (prefiereOscuro ? 'dark' : 'light');
                const nuevo = actual === 'dark' ? 'light' : 'dark';
                raiz.setAttribute('data-theme', nuevo);
                try { localStorage.setItem('cetecpro_tema', nuevo); } catch (e) {}
            }
        </script>
        <?php
    }
}
?>
