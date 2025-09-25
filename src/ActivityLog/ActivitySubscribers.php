<?php

namespace GarantiasOnline360VO\ActivityLog;

use GarantiasOnline360VO\Support\UserProfileResolver;

if (! defined('ABSPATH')) {
    exit;
}

class ActivitySubscribers
{
    private static bool $login_failure_handled = false;

    public static function init(): void
    {
        add_action('wp_login', [__CLASS__, 'on_login'], 10, 2);
        add_action('wp_login_failed', [__CLASS__, 'on_login_failed']);
        add_action('wp_logout', [__CLASS__, 'on_logout']);
        add_action('user_register', [__CLASS__, 'on_user_register'], 10, 1);
        add_action('profile_update', [__CLASS__, 'on_profile_update'], 10, 2);
        add_action('set_user_role', [__CLASS__, 'on_role_changed'], 10, 3);
        add_action('password_reset', [__CLASS__, 'on_password_reset'], 10, 2);
        add_action('retrieve_password_key', [__CLASS__, 'on_password_requested'], 10, 2);
        add_action('updated_option', [__CLASS__, 'on_option_updated'], 10, 3);
        add_action('go360/activity/log', [ActivityLogger::class, 'log'], 10, 2);
    }

    public static function on_login(string $user_login, \WP_User $user): void
    {
        ActivityLogger::log('auth.login_success', [
            'actor_id' => $user->ID,
            'actor_name' => UserProfileResolver::get_personal_name($user),
            'actor_email' => $user->user_email,
            'actor_role' => implode(',', $user->roles ?? []),
            'context' => [
                'username' => $user_login,
            ],
        ]);
    }

    public static function on_login_failed(string $username): void
    {
        if (self::$login_failure_handled) {
            self::$login_failure_handled = false;
            return;
        }

        ActivityLogger::log('auth.login_failed', [
            'context' => [
                'username' => $username,
            ],
            'level' => 'warning',
        ]);
    }

    public static function mark_login_failure_handled(): void
    {
        self::$login_failure_handled = true;
    }

    public static function on_logout(): void
    {
        $user_id = get_current_user_id();
        if (! $user_id) {
            return;
        }
        $user = get_userdata($user_id);
        ActivityLogger::log('auth.logout', [
            'actor_id' => $user_id,
            'actor_name' => $user ? UserProfileResolver::get_personal_name($user) : '',
        ]);
    }

    public static function on_user_register(int $user_id): void
    {
        $user = get_userdata($user_id);
        ActivityLogger::log('user.registered', [
            'actor_id' => get_current_user_id(),
            'context'  => [
                'user_id'    => $user_id,
                'user_email' => $user ? $user->user_email : '',
                'user_role'  => $user && ! empty($user->roles) ? implode(',', $user->roles) : '',
            ],
        ]);
    }

    public static function on_profile_update(int $user_id, \WP_User $old_user): void
    {
        $user = get_userdata($user_id);
        ActivityLogger::log('user.profile_updated', [
            'actor_id' => get_current_user_id() ?: $user_id,
            'context'  => [
                'user_id'    => $user_id,
                'user_email' => $user ? $user->user_email : $old_user->user_email,
            ],
        ]);
    }

    public static function on_role_changed(int $user_id, string $role, array $old_roles): void
    {
        $user = get_userdata($user_id);
        ActivityLogger::log('user.role_changed', [
            'actor_id' => get_current_user_id(),
            'context'  => [
                'user_id'   => $user_id,
                'user_email'=> $user ? $user->user_email : '',
                'new_role'  => $role,
                'old_roles' => implode(',', $old_roles),
            ],
        ]);
    }

    public static function on_password_reset(\WP_User $user, string $new_password): void
    {
        ActivityLogger::log('user.password_reset', [
            'actor_id' => get_current_user_id() ?: $user->ID,
            'context'  => [
                'user_id'    => $user->ID,
                'user_email' => $user->user_email,
            ],
            'level' => 'warning',
        ]);
    }

    public static function on_password_requested(?string $user_login, ?string $key): void
    {
        if (! $user_login) {
            return;
        }
        $user = get_user_by('login', $user_login);
        if (! $user) {
            $user = get_user_by('email', $user_login);
        }
        ActivityLogger::log('user.password_requested', [
            'context' => [
                'user_email' => $user ? $user->user_email : $user_login,
                'request'    => 'requested',
            ],
            'level' => 'warning',
        ]);
    }

    public static function on_option_updated(string $option, $old_value, $value): void
    {
        if (strpos($option, 'go360') === false && strpos($option, 'garantia') === false) {
            return;
        }
        $changes = self::diff_values($old_value, $value);
        ActivityLogger::log('settings.updated', [
            'actor_id' => get_current_user_id(),
            'context'  => [
                'setting_key' => $option,
                'changes'     => wp_json_encode($changes),
            ],
        ]);
    }

    private static function diff_values($old, $new): array
    {
        if (is_array($old) && is_array($new)) {
            return array_diff_assoc($new, $old);
        }
        return ['from' => maybe_serialize($old), 'to' => maybe_serialize($new)];
    }
}
