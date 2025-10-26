<?php

namespace GarantiasOnline360VO\Support;

if (! defined('ABSPATH')) {
    exit;
}

class NotificationEmailResolver
{
    public static function resolve(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $default_email = '';
        $user = get_user_by('id', $user_id);
        if ($user && $user->user_email) {
            $default_email = sanitize_email($user->user_email);
        }

        $resolved = $default_email;

        if (function_exists('get_field')) {
            $settings = get_field('ajustes_de_notificaciones', 'user_' . $user_id);
            if (is_array($settings)) {
                $same_as_registration = isset($settings['misma_direccion_registro'])
                    ? (bool) $settings['misma_direccion_registro']
                    : false;

                if (! $same_as_registration) {
                    $custom = sanitize_email($settings['correo_electronico_notificaciones'] ?? '');
                    if ($custom !== '') {
                        $resolved = $custom;
                    }
                }

                if ($same_as_registration) {
                    $resolved = $default_email;
                }
            }
        }

        if ($resolved === '' && $default_email !== '') {
            $resolved = $default_email;
        }

        return $resolved;
    }
}
