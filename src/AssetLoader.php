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
        // Encolar CSS
        wp_enqueue_style(
            'go360-style',
            plugins_url('assets/css/style_garantias.css', GARANTIAS360VO__FILE__)
        );

        // Encolar JS en el footer
        wp_enqueue_script(
            'go360-script',
            plugins_url('assets/js/global.js', GARANTIAS360VO__FILE__),
            [],
            false,
            true
        );
    }
}
