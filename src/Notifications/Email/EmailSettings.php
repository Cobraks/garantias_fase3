<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\SettingsPage;

if (! defined('ABSPATH')) {
    exit;
}

class EmailSettings
{
    /** @var array<string,mixed>|null */
    private static $cache = null;

    /**
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (! function_exists('get_field')) {
            self::$cache = [];
            return self::$cache;
        }

        $root_settings = get_field('notificaciones', SettingsPage::SUBMENU_SLUG);
        if (! is_array($root_settings)) {
            $root_settings = [];
        }

        $notifications_group = $root_settings['notificaciones_email'] ?? get_field('notificaciones_email', SettingsPage::SUBMENU_SLUG);
        if (! is_array($notifications_group)) {
            $notifications_group = [];
        }

        $content_group = $root_settings['contenido_correos_electronicos'] ?? get_field('contenido_correos_electronicos', SettingsPage::SUBMENU_SLUG);
        if (! is_array($content_group)) {
            $content_group = [];
        }

        if (empty($root_settings['direcciones_correo']) && isset($notifications_group['direcciones_correo'])) {
            $root_settings['direcciones_correo'] = $notifications_group['direcciones_correo'];
        }

        if (empty($root_settings['direccion_respuesta']) && isset($notifications_group['direccion_respuesta'])) {
            $root_settings['direccion_respuesta'] = $notifications_group['direccion_respuesta'];
        }

        if (empty($root_settings['firma']) && isset($content_group['firma'])) {
            $root_settings['firma'] = $content_group['firma'];
        }

        self::$cache = array_merge(
            $root_settings,
            [
                'notificaciones_email'        => $notifications_group,
                'contenido_correos_electronicos' => $content_group,
            ]
        );

        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    public static function getSignature(): string
    {
        $content = self::getContentGroup();
        $signature = $content['firma'] ?? '';

        if (! is_string($signature)) {
            return '';
        }

        return trim(wp_kses_post($signature));
    }

    /**
     * @return array<string,mixed>
     */
    public static function getContentGroup(): array
    {
        $settings = self::all();
        $content = $settings['contenido_correos_electronicos'] ?? [];

        return is_array($content) ? $content : [];
    }

    public static function getReplyTo(): string
    {
        $settings = self::all();
        $reply_to = '';

        if (isset($settings['direccion_respuesta'])) {
            $reply_to = (string) $settings['direccion_respuesta'];
        }

        if ($reply_to === '' && isset($settings['notificaciones_email']['direccion_respuesta'])) {
            $reply_to = (string) $settings['notificaciones_email']['direccion_respuesta'];
        }

        $reply_to = apply_filters('go360/email/reply_to', $reply_to, $settings);

        return sanitize_email($reply_to);
    }

    public static function resolveSenderEmail(string $context, string $fallback = ''): string
    {
        $email = $fallback !== '' ? $fallback : sanitize_email(get_option('admin_email'));
        $settings = self::all();

        return sanitize_email(
            apply_filters('go360/email/sender_email', $email, $context, $settings)
        );
    }

    public static function buildFromHeader(string $context, string $fallback = '', ?string $name = null): string
    {
        $email = self::resolveSenderEmail($context, $fallback);
        if ($email === '') {
            return '';
        }

        $display_name = $name ?? __('Garantías 360VO', 'garantias-online-360vo');
        $display_name = wp_strip_all_tags($display_name);

        return sprintf('From: %s <%s>', $display_name !== '' ? $display_name : 'Garantías 360VO', $email);
    }
}
