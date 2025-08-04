<?php

/**
 * Encola CSS y JS globales para Garantías Online 360VO
 *
 * @package GarantiasOnline360VO
 */

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class AssetLoader
{

    /**
     * Registra el hook de encolado en el frontend.
     */
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Encola el CSS y JS global.
     */
    public static function enqueue_assets(): void
    {
        $base_path = plugin_dir_path(GARANTIAS360VO__FILE__);

        // Encolar CSS global minificado con versionado por fecha de modificación
        $css_rel  = 'assets/css/global.min.css';
        $css_file = $base_path . $css_rel;
        wp_enqueue_style(
            'go360-global',
            plugins_url($css_rel, GARANTIAS360VO__FILE__),
            [],
            file_exists($css_file) ? filemtime($css_file) : false
        );

        // Encolar JS global minificado en el footer con versionado
        $js_rel  = 'assets/js/global.min.js';
        $js_file = $base_path . $js_rel;
        wp_enqueue_script(
            'go360-global',
            plugins_url($js_rel, GARANTIAS360VO__FILE__),
            [],
            file_exists($js_file) ? filemtime($js_file) : false,
            true
        );
    }
}
