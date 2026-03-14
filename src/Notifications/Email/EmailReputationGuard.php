<?php

namespace GarantiasOnline360VO\Notifications\Email;

if (! defined('ABSPATH')) {
    exit;
}

class EmailReputationGuard
{
    private const SAFE_MODE_OPTION = 'go360_email_safe_mode';

    public static function is_safe_mode_enabled(): bool
    {
        $enabled = (bool) get_option(self::SAFE_MODE_OPTION, false);

        return (bool) apply_filters('go360/email/safe_mode_enabled', $enabled);
    }

    public static function should_send_event(string $event_slug, array $context = []): bool
    {
        $event_slug = sanitize_key($event_slug);
        if ($event_slug === '') {
            return false;
        }

        $allowed = true;
        if (self::is_safe_mode_enabled()) {
            $allowed_in_safe_mode = [
                'register_verification',
                'password_reset',
                'password_change_user',
            ];
            $allowed = in_array($event_slug, $allowed_in_safe_mode, true);
        }

        return (bool) apply_filters('go360/email/guard_should_send', $allowed, $event_slug, $context);
    }

    public static function should_include_internal_bcc(string $event_slug = ''): bool
    {
        $include = ! self::is_safe_mode_enabled();

        return (bool) apply_filters('go360/email/include_internal_bcc', $include, sanitize_key($event_slug));
    }
}
