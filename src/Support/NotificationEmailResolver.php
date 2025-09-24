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

        $resolved             = $default_email;
        $same_as_registration = null;
        $candidates           = [];

        if (function_exists('get_field')) {
            $settings = get_field('ajustes_de_notificaciones', 'user_' . $user_id);
            if (is_array($settings)) {
                if (array_key_exists('misma_direccion_registro', $settings)) {
                    $same_as_registration = (bool) $settings['misma_direccion_registro'];
                }

                $candidates[] = $settings['correo_electronico_notificaciones'] ?? '';
                $candidates[] = $settings['correo_electronico'] ?? '';
            }

            $profile_group = get_field('datos_usuario', 'user_' . $user_id);
            if (is_array($profile_group)) {
                $candidates[] = $profile_group['correo_electronico'] ?? '';
            }
        }

        $meta_keys = [
            'ajustes_de_notificaciones_correo_electronico_notificaciones',
            'ajustes_de_notificaciones_correo_electronico',
            'datos_usuario_correo_electronico',
        ];

        foreach ($meta_keys as $meta_key) {
            $candidates[] = get_user_meta($user_id, $meta_key, true);
        }

        foreach ($candidates as $candidate) {
            $candidate = sanitize_email((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            if ($same_as_registration === true) {
                break;
            }

            $resolved = $candidate;
            break;
        }

        if ($resolved === '' || $same_as_registration === true) {
            $resolved = $default_email;
        }

        return $resolved;
    }
}
