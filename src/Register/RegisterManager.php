<?php

namespace GarantiasOnline360VO\Register;

use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class RegisterManager
{
    private const CLEANUP_HOOK = 'go360/register/cleanup';

    public static function init(): void
    {
        add_filter('wp_send_new_user_notification_to_admin', '__return_false');
        add_filter('wp_send_new_user_notification_to_user', '__return_false');

        add_filter('authenticate', [__CLASS__, 'maybe_block_unverified'], 30, 3);

        add_action('init', [__CLASS__, 'schedule_cleanup']);
        add_action(self::CLEANUP_HOOK, [__CLASS__, 'run_cleanup']);
    }

    /**
     * Impide el acceso a cuentas pendientes de verificación.
     *
     * @param WP_User|WP_Error|null $user
     * @param string                $username
     * @param string                $password
     *
     * @return WP_User|WP_Error|null
     */
    public static function maybe_block_unverified($user, $username, $password)
    {
        if (! $user instanceof WP_User) {
            return $user;
        }

        if (user_can($user, 'manage_options')) {
            return $user;
        }

        if (! RegistrationMeta::is_verification_required((int) $user->ID)) {
            return $user;
        }

        $verified_at = get_user_meta($user->ID, RegistrationMeta::VERIFIED_AT, true);
        if ($verified_at !== '' && $verified_at !== false) {
            RegistrationMeta::mark_required((int) $user->ID, false);
            return $user;
        }

        $error = new WP_Error(
            'go_email_not_verified',
            __(
                'Tu cuenta está pendiente de verificación. Introduce el código enviado a tu correo para activarla o solicita uno nuevo.',
                'garantias-online-360vo'
            )
        );

        return $error;
    }

    public static function schedule_cleanup(): void
    {
        if (wp_next_scheduled(self::CLEANUP_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CLEANUP_HOOK);
    }

    public static function clear_schedule(): void
    {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
            $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        }
    }

    public static function run_cleanup(): void
    {
        RegistrationService::cleanup_stale_records();
    }
}
