<?php

/**
 * Compila/minifica CSS y JS del plugin.
 *
 * Uso:
 *   AssetCompiler::ensure_minified();
 * Se puede llamar en cada carga para regenerar si el .min está anticuado.
 */

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class AssetCompiler
{

    /**
     * Archivos fuente que queremos vigilar.
     *  – Pon **siempre** la versión sin “.min”.
     */
    private const FILES = [
        // globales
        'assets/css/global.css',
        'assets/js/global.js',

        // DASHBOARD
        'assets/css/dashboard.css',
        'assets/js/dashboard.js',

        // vista “nueva garantía”
        'assets/css/nueva_garantia.css',
        'assets/js/nueva_garantia.js',

        'assets/css/auth.css',

        // MIS GARANTÍAS
        'assets/css/mis_garantias.css',
        'assets/js/mis_garantias.js',
    ];

    /** Llama a esto en un hook (init o activación). */
    public static function ensure_minified(): void
    {
        $base = plugin_dir_path(GARANTIAS360VO__FILE__);

        foreach (self::FILES as $rel) {
            $src = $base . $rel;
            if (! file_exists($src)) {
                continue;
            }

            $dest = preg_replace(['/\.css$/', '/\.js$/'], ['.min.css', '.min.js'], $src);

            if (! file_exists($dest) || filemtime($dest) < filemtime($src)) {
                str_ends_with($src, '.css')
                    ? self::compile_css($src, $dest)
                    : self::compile_js($src, $dest);
            }
        }
    }

    /* --- minificadores muy simples --- */

    private static function compile_css(string $src, string $dest): void
    {
        $css = file_get_contents($src);
        $min = preg_replace(['!/\*.*?\*/!s', '/\s+/'], ['', ' '], $css);
        $min = str_replace([': ', '; ', ' {', '} '], [':', ';', '{', '}'], $min);
        file_put_contents($dest, trim($min));
    }

    private static function compile_js(string $src, string $dest): void
    {
        $js = file_get_contents($src);
        if ($js === false) {
            return;
        }

        $min = preg_replace('!/\*.*?\*/!s', '', $js);
        if ($min === null) {
            $min = $js;
        }

        $lines = preg_split('/\r\n|\r|\n/', $min);
        if (is_array($lines)) {
            $lines = array_map('rtrim', $lines);
            $min   = implode("\n", $lines);
        }

        $min = trim($min) . "\n";

        file_put_contents($dest, $min);
    }
}
