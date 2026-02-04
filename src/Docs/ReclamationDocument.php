<?php

namespace GarantiasOnline360VO\Docs;

use GarantiasOnline360VO\SettingsPage;

if (! defined('ABSPATH')) {
    exit;
}

class ReclamationDocument
{
    /**
     * Retrieve the configured reclamation procedure PDF URL.
     */
    public static function get_url(): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = '';

        if (! function_exists('get_field')) {
            return $cached;
        }

        $file = get_field('documentacion_procedimiento_de_reclamacion', SettingsPage::SUBMENU_SLUG);

        if (is_array($file)) {
            if (! empty($file['url'])) {
                $cached = esc_url_raw($file['url']);
            } elseif (! empty($file['ID'])) {
                $url = wp_get_attachment_url((int) $file['ID']);
                if ($url) {
                    $cached = esc_url_raw($url);
                }
            }
        } elseif (is_string($file)) {
            $cached = esc_url_raw($file);
        }

        return $cached ?: '';
    }
}
