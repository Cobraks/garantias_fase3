<?php

namespace GarantiasOnline360VO\Support;

if (! defined('ABSPATH')) {
    exit;
}

class NotificationEmailResolver
{
    public static function resolve(int $user_id): string
    {
        return self::resolve_with_details($user_id)['email'];
    }

    public static function resolve_with_details(int $user_id): array
    {
        $details = [
            'email'              => '',
            'source'             => 'registration',
            'uses_registration'  => true,
            'default_email'      => '',
        ];

        if ($user_id <= 0) {
            return $details;
        }

        $user = get_user_by('id', $user_id);
        if ($user && $user->user_email) {
            $details['default_email'] = sanitize_email($user->user_email);
        }

        $resolved             = $details['default_email'];
        $resolved_source       = 'registration';
        $same_as_registration  = null;
        $candidates            = [];

        if (function_exists('get_field')) {
            $settings = get_field('ajustes_de_notificaciones', 'user_' . $user_id);
            if (is_array($settings)) {
                if (array_key_exists('misma_direccion_registro', $settings)) {
                    $same_as_registration = (bool) $settings['misma_direccion_registro'];
                }

                $candidates[] = [
                    'value'  => $settings['correo_electronico_notificaciones'] ?? '',
                    'source' => 'notifications',
                ];
                $candidates[] = [
                    'value'  => $settings['correo_electronico'] ?? '',
                    'source' => 'notifications',
                ];
            }

            $profile_group = get_field('datos_usuario', 'user_' . $user_id);
            if (is_array($profile_group)) {
                $candidates[] = [
                    'value'  => $profile_group['correo_electronico'] ?? '',
                    'source' => 'notifications',
                ];
            }
        }

        $meta_keys = [
            'ajustes_de_notificaciones_correo_electronico_notificaciones' => 'notifications',
            'ajustes_de_notificaciones_correo_electronico' => 'notifications',
            'datos_usuario_correo_electronico' => 'notifications',
        ];

        foreach ($meta_keys as $meta_key => $source) {
            $candidates[] = [
                'value'  => get_user_meta($user_id, $meta_key, true),
                'source' => $source,
            ];
        }

        foreach ($candidates as $candidate) {
            $email = sanitize_email((string) ($candidate['value'] ?? ''));
            if ($email === '') {
                continue;
            }

            if ($same_as_registration === true) {
                break;
            }

            $resolved = $email;
            $resolved_source = $candidate['source'] ?? 'notifications';
            break;
        }

        if ($resolved === '' || $same_as_registration === true) {
            $resolved = $details['default_email'];
            $resolved_source = 'registration';
        }

        if ($resolved === '') {
            $resolved_source = 'registration';
        }

        $details['email'] = $resolved;
        $details['source'] = $resolved_source;
        $details['uses_registration'] = ($resolved_source === 'registration');

        return $details;
    }
}
