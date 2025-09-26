<?php

namespace GarantiasOnline360VO\Register;

if (! defined('ABSPATH')) {
    exit;
}

class RegistrationMeta
{
    public const STATUS          = 'go_email_verification_status';
    public const HASH            = 'go_email_verification_hash';
    public const EXPIRES         = 'go_email_verification_expires';
    public const TOKEN           = 'go_email_verification_token';
    public const ATTEMPTS        = 'go_email_verification_attempts';
    public const LOCKED_UNTIL    = 'go_email_verification_locked_until';
    public const RESEND_COUNT    = 'go_email_verification_resend_count';
    public const RESEND_LAST     = 'go_email_verification_last_sent';
    public const RESEND_WINDOW   = 'go_email_verification_resend_window';
    public const REQUIRED        = 'go_email_verification_required';
    public const VERIFIED_AT     = 'go_email_verification_verified_at';
    public const REGISTRATION_IP = 'go_email_registration_ip';

    public static function is_verification_required(int $user_id): bool
    {
        $required = get_user_meta($user_id, self::REQUIRED, true);
        if ((int) $required === 1 || $required === '1' || $required === true) {
            return true;
        }

        $status = get_user_meta($user_id, self::STATUS, true);
        if (is_string($status) && strtolower($status) === 'pending') {
            return true;
        }

        return false;
    }

    public static function mark_required(int $user_id, bool $required): void
    {
        if ($required) {
            update_user_meta($user_id, self::REQUIRED, 1);
            update_user_meta($user_id, self::STATUS, 'pending');
        } else {
            delete_user_meta($user_id, self::REQUIRED);
            delete_user_meta($user_id, self::STATUS);
        }
    }

    public static function get_token_owner(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        $users = get_users([
            'fields'     => 'ids',
            'number'     => 1,
            'meta_key'   => self::TOKEN,
            'meta_value' => sanitize_text_field($token),
        ]);

        if (empty($users)) {
            return null;
        }

        return (int) $users[0];
    }
}
