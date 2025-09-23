<?php

namespace GarantiasOnline360VO\Auth;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\ActivityLog\ActivitySubscribers;
use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class AuthController
{
    private const LOGIN_ACTION          = 'go_login_action';
    private const LOST_PASSWORD_ACTION  = 'go_lost_password_action';
    private const LOGIN_STATE_PREFIX    = 'go_login_state_';
    private const LOST_STATE_PREFIX     = 'go_lost_state_';
    private const STATE_TTL             = 5 * MINUTE_IN_SECONDS;

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_handle_login']);
        add_action('init', [__CLASS__, 'maybe_handle_lost_password']);
        add_filter('lostpassword_url', [__CLASS__, 'filter_lostpassword_url'], 10, 2);
        PasswordResetMailer::init();
    }

    public static function maybe_handle_login(): void
    {
        if (! self::is_post_request() || ! self::is_action('login')) {
            return;
        }

        $nonce = isset($_POST['go_login_nonce']) ? sanitize_text_field(wp_unslash($_POST['go_login_nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::LOGIN_ACTION)) {
            self::redirect_to_login(['error' => 'generic']);
        }

        $login_input = isset($_POST['log']) ? wp_unslash($_POST['log']) : '';
        $password    = isset($_POST['pwd']) ? wp_unslash($_POST['pwd']) : '';
        $remember    = ! empty($_POST['rememberme']);
        $redirect_to = isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '';

        $credentials = [
            'user_login'    => $login_input,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $user = wp_signon($credentials, false);

        if (! $user instanceof WP_Error) {
            $redirect = self::validate_redirect($redirect_to);
            wp_safe_redirect($redirect);
            exit;
        }

        $email_for_query = self::prepare_email_for_query($login_input);
        $error_code      = $user->get_error_code();
        $error_key       = self::map_login_error($error_code);

        self::log_login_failure($error_key, $error_code, $login_input);

        $state = [
            'error'        => $error_key,
            'source_error' => $error_code,
            'email'        => $email_for_query,
            'remember'     => $remember ? '1' : '',
        ];

        if ($redirect_to !== '') {
            $state['redirect_to'] = self::validate_redirect($redirect_to);
        }

        $token = self::persist_state(self::LOGIN_STATE_PREFIX, $state);

        if ($token !== null) {
            self::redirect_to_login(['state' => $token]);
        }

        $args = ['error' => $error_key];

        if ($email_for_query !== '') {
            $args['email'] = $email_for_query;
        }

        if ($remember) {
            $args['remember'] = '1';
        }

        if ($redirect_to !== '') {
            $args['redirect_to'] = self::validate_redirect($redirect_to);
        }

        self::redirect_to_login($args);
    }

    public static function maybe_handle_lost_password(): void
    {
        if (! self::is_post_request() || ! self::is_action('lostpassword')) {
            return;
        }

        $nonce = isset($_POST['go_lost_nonce']) ? sanitize_text_field(wp_unslash($_POST['go_lost_nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::LOST_PASSWORD_ACTION)) {
            self::redirect_to_lost_password(['error' => 'generic']);
        }

        $login = isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';
        $redirect_to_raw = isset($_POST['redirect_to']) ? wp_unslash($_POST['redirect_to']) : '';
        $redirect_for_query = $redirect_to_raw !== ''
            ? self::validate_redirect($redirect_to_raw, home_url('/garantias-online/login/'))
            : '';

        if ($login === '') {
            $args = ['error' => 'email'];
            if ($redirect_for_query !== '') {
                $args['redirect_to'] = $redirect_for_query;
            }
            self::redirect_to_lost_password($args);
        }

        $result = retrieve_password($login);

        if ($result instanceof WP_Error) {
            $error_code = $result->get_error_code();
            $error_key  = in_array($error_code, ['invalid_email', 'invalidcombo', 'notexists', 'empty_username'], true)
                ? 'email'
                : 'generic';
            if ($error_key === 'email') {
                self::log_password_request_failure($login);
            }
            $state = [
                'error'        => $error_key,
                'source_error' => $error_code,
            ];

            if ($error_key === 'email') {
                $email_for_query = self::prepare_email_for_query($login);
                if ($email_for_query !== '') {
                    $state['email'] = $email_for_query;
                }
            }

            if ($redirect_for_query !== '') {
                $state['redirect_to'] = $redirect_for_query;
            }

            $token = self::persist_state(self::LOST_STATE_PREFIX, $state);

            if ($token !== null) {
                self::redirect_to_lost_password(['state' => $token]);
            }

            $args = ['error' => $error_key];
            if (! empty($state['email'])) {
                $args['email'] = $state['email'];
            }
            if ($redirect_for_query !== '') {
                $args['redirect_to'] = $redirect_for_query;
            }

            self::redirect_to_lost_password($args);
        }

        $state = ['sent' => '1'];
        if ($redirect_for_query !== '') {
            $state['redirect_to'] = $redirect_for_query;
        }

        $token = self::persist_state(self::LOST_STATE_PREFIX, $state);

        if ($token !== null) {
            self::redirect_to_lost_password(['state' => $token]);
        }

        $args = ['sent' => '1'];
        if ($redirect_for_query !== '') {
            $args['redirect_to'] = $redirect_for_query;
        }

        self::redirect_to_lost_password($args);
    }

    public static function filter_lostpassword_url(string $url, string $redirect = ''): string
    {
        $custom = self::get_lost_password_url();

        if ($redirect !== '') {
            $custom = add_query_arg('redirect_to', self::validate_redirect($redirect, $custom), $custom);
        }

        return $custom;
    }

    private static function is_post_request(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST';
    }

    private static function is_action(string $action): bool
    {
        if (! isset($_POST['go_auth_action'])) {
            return false;
        }

        $value = sanitize_key(wp_unslash($_POST['go_auth_action']));
        return $value === $action;
    }

    private static function validate_redirect(string $redirect_to, ?string $default = null): string
    {
        $default = $default ?? home_url('/garantias-online/mis-garantias/');
        return wp_validate_redirect($redirect_to, $default);
    }

    private static function map_login_error(string $code): string
    {
        if (in_array($code, ['invalid_email', 'invalid_username'], true)) {
            return 'email';
        }

        if ($code === 'incorrect_password') {
            return 'password';
        }

        if (in_array($code, ['empty_username', 'empty_password'], true)) {
            return 'missing';
        }

        return 'generic';
    }

    private static function prepare_email_for_query(string $login_input): string
    {
        $email = sanitize_email($login_input);
        if ($email !== '') {
            return $email;
        }

        $sanitized = sanitize_text_field($login_input);
        return $sanitized !== '' ? $sanitized : '';
    }

    private static function redirect_to_login(array $args = []): void
    {
        $url = self::get_login_url();

        if (! empty($args)) {
            $url = add_query_arg($args, $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    private static function redirect_to_lost_password(array $args = []): void
    {
        $url = self::get_lost_password_url();

        if (! empty($args)) {
            $url = add_query_arg($args, $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    private static function get_login_url(): string
    {
        return home_url('/garantias-online/login/');
    }

    private static function get_lost_password_url(): string
    {
        return home_url('/garantias-online/restablecer-clave/');
    }

    public static function consume_login_state(string $token): array
    {
        $state = self::consume_state(self::LOGIN_STATE_PREFIX, $token);

        if (empty($state)) {
            return [];
        }

        $default_redirect = home_url('/garantias-online/mis-garantias/');

        return [
            'error'        => isset($state['error']) ? sanitize_key((string) $state['error']) : '',
            'source_error' => isset($state['source_error']) ? sanitize_key((string) $state['source_error']) : '',
            'email'        => isset($state['email']) ? sanitize_text_field((string) $state['email']) : '',
            'remember'     => ! empty($state['remember']),
            'redirect_to'  => isset($state['redirect_to'])
                ? wp_validate_redirect((string) $state['redirect_to'], $default_redirect)
                : $default_redirect,
        ];
    }

    public static function consume_lost_password_state(string $token): array
    {
        $state = self::consume_state(self::LOST_STATE_PREFIX, $token);

        if (empty($state)) {
            return [];
        }

        $default_redirect = home_url('/garantias-online/login/');

        return [
            'sent'         => ! empty($state['sent']),
            'error'        => isset($state['error']) ? sanitize_key((string) $state['error']) : '',
            'source_error' => isset($state['source_error']) ? sanitize_key((string) $state['source_error']) : '',
            'email'        => isset($state['email']) ? sanitize_text_field((string) $state['email']) : '',
            'redirect_to'  => isset($state['redirect_to'])
                ? wp_validate_redirect((string) $state['redirect_to'], $default_redirect)
                : $default_redirect,
        ];
    }

    private static function persist_state(string $prefix, array $state): ?string
    {
        $token = wp_generate_uuid4();
        if (! $token) {
            return null;
        }

        $key = $prefix . $token;
        $stored = set_transient($key, $state, self::STATE_TTL);

        if (! $stored) {
            return null;
        }

        return $token;
    }

    private static function consume_state(string $prefix, string $token): array
    {
        $token = self::sanitize_state_token($token);
        if ($token === '') {
            return [];
        }

        $key   = $prefix . $token;
        $state = get_transient($key);
        delete_transient($key);

        return is_array($state) ? $state : [];
    }

    private static function sanitize_state_token(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        return preg_match('/^[A-Fa-f0-9-]{8,}$/', $token) ? $token : '';
    }

    private static function log_login_failure(string $error_key, string $source_error, string $login_input): void
    {
        $identifier = sanitize_text_field($login_input);
        $user       = self::find_user($login_input);

        switch ($error_key) {
            case 'password':
                if ($user instanceof WP_User) {
                    ActivityLogger::log('auth.login_failed_invalid_password', [
                        'target_id'    => $user->ID,
                        'actor_email'  => $user->user_email,
                        'context'      => [
                            'username'        => $user->user_login,
                            'user_email'      => $user->user_email,
                            'attempted_login' => $identifier,
                        ],
                        'level'        => 'warning',
                    ]);
                    ActivitySubscribers::mark_login_failure_handled();
                    return;
                }
                // If we cannot resolve the user fall back to unknown user logging
                ActivityLogger::log('auth.login_failed_unknown_user', [
                    'context' => [
                        'username'        => $identifier,
                        'source_error'    => $source_error,
                    ],
                    'level'   => 'warning',
                ]);
                ActivitySubscribers::mark_login_failure_handled();
                return;

            case 'email':
                ActivityLogger::log('auth.login_failed_unknown_user', [
                    'context' => [
                        'username'     => $identifier,
                        'source_error' => $source_error,
                    ],
                    'level'   => 'warning',
                ]);
                ActivitySubscribers::mark_login_failure_handled();
                return;

            case 'missing':
            case 'generic':
            default:
                ActivityLogger::log('auth.login_failed', [
                    'context' => [
                        'username'     => $identifier,
                        'source_error' => $source_error,
                    ],
                    'level'   => 'warning',
                ]);
                ActivitySubscribers::mark_login_failure_handled();
                return;
        }
    }

    private static function log_password_request_failure(string $login_input): void
    {
        $identifier = sanitize_text_field($login_input);

        ActivityLogger::log('auth.password_recovery.invalid_user', [
            'context' => [
                'username' => $identifier,
            ],
            'level'   => 'warning',
        ]);
    }

    private static function find_user(string $login_input): ?WP_User
    {
        $login_input = trim($login_input);
        if ($login_input === '') {
            return null;
        }

        if (is_email($login_input)) {
            $user = get_user_by('email', $login_input);
            if ($user instanceof WP_User) {
                return $user;
            }
        }

        $user = get_user_by('login', $login_input);
        return $user instanceof WP_User ? $user : null;
    }
}
