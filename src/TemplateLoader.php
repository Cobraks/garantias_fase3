<?php

/**
 * Carga las plantillas del plugin Garantías Online 360VO
 *
 * @package GarantiasOnline360VO
 */

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class TemplateLoader
{
    /**
     * Carga una plantilla completa (sin usar get_header()/get_footer()).
     *
     * @param string $slug Nombre de la plantilla en /templates/ (sin .php).
     * @param array  $vars Variables a pasar a la plantilla.
     */
    public static function load(string $slug, array $vars = []): void
    {
        if (! empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        $file = plugin_dir_path(GARANTIAS360VO__FILE__) . "templates/{$slug}.php";

        if (file_exists($file)) {
            include $file;
        } else {
            status_header(404);
            echo '<h1>Plantilla no encontrada</h1>';
        }
    }

    /**
     * Carga un fragmento de plantilla (header, footer, etc.) desde /templates/parts/.
     *
     * @param string $part Nombre de la parte (sin .php).
     * @param array  $vars Variables a pasar al fragmento.
     */
    public static function load_part(string $part, array $vars = []): void
    {
        if (! empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        $file = plugin_dir_path(GARANTIAS360VO__FILE__) . "templates/parts/{$part}.php";

        if (file_exists($file)) {
            include $file;
        }
    }
}
