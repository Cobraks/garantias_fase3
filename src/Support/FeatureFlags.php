<?php

namespace GarantiasOnline360VO\Support;

use GarantiasOnline360VO\SettingsPage;

if (! defined('ABSPATH')) {
    exit;
}

class FeatureFlags
{
    /**
     * Determina si el botón de datos de ejemplo debe estar disponible.
     */
    public static function is_example_data_enabled(): bool
    {
        if (! function_exists('get_field')) {
            return false;
        }

        $scopes = ['option', SettingsPage::SUBMENU_SLUG];

        foreach ($scopes as $scope) {
            $group = get_field('desarrollo', $scope);
            if (is_array($group) && array_key_exists('boton_datos_ejemplo', $group)) {
                return (bool) $group['boton_datos_ejemplo'];
            }

            $value = get_field('boton_datos_ejemplo', $scope);
            if ($value !== null) {
                return (bool) $value;
            }
        }

        return false;
    }
}
