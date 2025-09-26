<?php

namespace GarantiasOnline360VO\Register;

use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class RegisterManager
{
    public static function init(): void
    {
        add_filter('wp_send_new_user_notification_to_admin', '__return_false');
        add_filter('wp_send_new_user_notification_to_user', '__return_false');

        add_filter('authenticate', [__CLASS__, 'maybe_block_unverified'], 30, 3);
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
}
