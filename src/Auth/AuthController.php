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
    private const RESET_PASSWORD_ACTION = 'go_reset_password_action';
    private const LOGIN_STATE_PREFIX    = 'go_login_state_';
    private const LOST_STATE_PREFIX     = 'go_lost_state_';
    private const RESET_STATE_PREFIX    = 'go_reset_state_';
    private const STATE_TTL             = 5 * MINUTE_IN_SECONDS;

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_handle_login']);
        add_action('init', [__CLASS__, 'maybe_handle_lost_password']);
        add_action('init', [__CLASS__, 'maybe_handle_reset_password']);
        add_filter('lostpassword_url', [__CLASS__, 'filter_lostpassword_url'], 10, 2);
        PasswordResetMailer::init();
        WPLoginStyler::init();
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

    public static function maybe_handle_reset_password(): void
    {
        if (! self::is_post_request() || ! self::is_action('resetpassword')) {
            return;
        }

        $nonce = isset($_POST['go_reset_nonce']) ? sanitize_text_field(wp_unslash($_POST['go_reset_nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::RESET_PASSWORD_ACTION)) {
            self::redirect_to_reset_password(['error' => 'generic']);
        }

        $login = isset($_POST['user_login']) ? wp_unslash($_POST['user_login']) : '';
        $key   = isset($_POST['rp_key']) ? wp_unslash($_POST['rp_key']) : '';
        $pass1 = isset($_POST['pass1']) ? wp_unslash($_POST['pass1']) : '';
        $pass2 = isset($_POST['pass2']) ? wp_unslash($_POST['pass2']) : '';

        $login = is_scalar($login) ? trim((string) $login) : '';
        $key   = is_scalar($key) ? trim((string) $key) : '';
        $pass1 = is_scalar($pass1) ? (string) $pass1 : '';
        $pass2 = is_scalar($pass2) ? (string) $pass2 : '';

        $query_args = self::build_reset_query_args($login, $key);

        $user_or_error = self::validate_reset_key($key, $login);
        if ($user_or_error instanceof WP_Error) {
            $state = [
                'global_error' => self::describe_reset_key_error($user_or_error),
                'login'        => $login,
                'status'       => 'invalid',
            ];

            $token = self::persist_state(self::RESET_STATE_PREFIX, $state);

            if ($token !== null) {
                self::redirect_to_reset_password(['state' => $token], $query_args);
            }

            $query_args['error'] = 'invalid';
            self::redirect_to_reset_password($query_args);
        }

        $user   = $user_or_error;
        $errors = new WP_Error();

        if ($pass1 === '' || $pass2 === '') {
            $errors->add('password_reset_empty', __('Introduce y confirma tu nueva contraseña.', 'garantias-online-360vo'));
        }

        if ($pass1 !== '' && $pass2 !== '' && $pass1 !== $pass2) {
            $errors->add('password_reset_mismatch', __('Las contraseñas no coinciden.', 'garantias-online-360vo'));
        }

        /** @var WP_Error $errors */
        $errors = apply_filters('validate_password_reset', $errors, $user);

        if ($errors instanceof WP_Error && $errors->has_errors()) {
            $mapped = self::map_reset_error_messages($errors);
            $state  = array_merge($mapped, [
                'login'  => $login,
                'key'    => $key,
                'status' => 'validation',
            ]);

            $token = self::persist_state(self::RESET_STATE_PREFIX, $state);

            if ($token !== null) {
                self::redirect_to_reset_password(['state' => $token], $query_args);
            }

            $query_args['error'] = 'validation';
            self::redirect_to_reset_password($query_args);
        }

        reset_password($user, $pass1);

        $email_for_login = $user->user_email ?? self::prepare_email_for_query($login);
        $state           = [
            'notice' => 'password_reset',
        ];

        if ($email_for_login !== '') {
            $state['email'] = $email_for_login;
        }

        $token = self::persist_state(self::LOGIN_STATE_PREFIX, $state);

        if ($token !== null) {
            self::redirect_to_login(['state' => $token]);
        }

        self::redirect_to_login(['reset' => '1']);
    }

    public static function filter_lostpassword_url(string $url, string $redirect = ''): string
    {
        $custom = self::get_lost_password_url();

        if ($redirect !== '') {
            $custom = add_query_arg('redirect_to', self::validate_redirect($redirect, $custom), $custom);
        }

        return $custom;
    }

    public static function redirect_native_reset(): void
    {
        $login = isset($_REQUEST['login']) ? wp_unslash($_REQUEST['login']) : '';
        $key   = isset($_REQUEST['key']) ? wp_unslash($_REQUEST['key']) : '';

        $login = is_scalar($login) ? (string) $login : '';
        $key   = is_scalar($key) ? (string) $key : '';

        $url = self::get_reset_password_url($login, $key);

        wp_safe_redirect($url);
        exit;
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

    private static function build_reset_query_args(string $login, string $key): array
    {
        $query = [];

        $login = trim($login);
        if ($login !== '') {
            $query['login'] = $login;
        }

        $key = trim($key);
        if ($key !== '') {
            $query['key'] = $key;
        }

        return self::sanitize_reset_query_args($query);
    }

    private static function sanitize_reset_query_args(array $query): array
    {
        $sanitized = [];

        if (isset($query['login']) && $query['login'] !== '') {
            $sanitized['login'] = sanitize_text_field((string) $query['login']);
        }

        if (isset($query['key']) && $query['key'] !== '') {
            $sanitized['key'] = sanitize_text_field((string) $query['key']);
        }

        return $sanitized;
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

    /**
     * @return WP_User|WP_Error
     */
    public static function validate_reset_key(string $key, string $login)
    {
        $key   = trim($key);
        $login = trim($login);

        if ($key === '' || $login === '') {
            return new WP_Error('invalid_key');
        }

        return check_password_reset_key($key, $login);
    }

    public static function describe_reset_key_error(WP_Error $error): string
    {
        $code = $error->get_error_code();

        switch ($code) {
            case 'expired_key':
                return __('Este enlace ha caducado. Solicita un nuevo correo para restablecer tu contraseña.', 'garantias-online-360vo');
            case 'invalid_key':
                return __('Este enlace no es válido. Genera uno nuevo para continuar.', 'garantias-online-360vo');
            case 'invalid_user':
                return __('No hemos podido identificar la cuenta asociada. Solicita un nuevo restablecimiento.', 'garantias-online-360vo');
        }

        $message = $error->get_error_message($code);
        if ($message !== '') {
            return $message;
        }

        $messages = $error->get_error_messages();
        if (! empty($messages)) {
            return (string) reset($messages);
        }

        return __('No hemos podido validar el enlace de restablecimiento. Solicita uno nuevo.', 'garantias-online-360vo');
    }

    private static function map_reset_error_messages(WP_Error $errors): array
    {
        $global = '';
        $pass1  = '';
        $pass2  = '';

        foreach ($errors->get_error_codes() as $code) {
            foreach ($errors->get_error_messages($code) as $message) {
                $translated = self::translate_reset_error_message($code, $message);

                switch ($code) {
                    case 'password_reset_empty':
                        if ($pass1 === '') {
                            $pass1 = $translated;
                        }
                        if ($pass2 === '') {
                            $pass2 = $translated;
                        }
                        if ($global === '') {
                            $global = $translated;
                        }
                        break;
                    case 'password_reset_mismatch':
                        if ($pass2 === '') {
                            $pass2 = $translated;
                        }
                        if ($global === '') {
                            $global = $translated;
                        }
                        break;
                    case 'invalid_password':
                    case 'password_too_short':
                    case 'password_too_common':
                    case 'password_too_similar':
                        if ($pass1 === '') {
                            $pass1 = $translated;
                        }
                        if ($global === '') {
                            $global = $translated;
                        }
                        break;
                    default:
                        if ($global === '') {
                            $global = $translated;
                        }
                        break;
                }
            }
        }

        if ($global === '') {
            $global = __('No hemos podido restablecer la contraseña. Inténtalo de nuevo.', 'garantias-online-360vo');
        }

        return [
            'global_error' => $global,
            'pass1_error'  => $pass1,
            'pass2_error'  => $pass2,
        ];
    }

    private static function translate_reset_error_message(string $code, string $message): string
    {
        switch ($code) {
            case 'password_reset_empty':
                return __('Introduce y confirma tu nueva contraseña.', 'garantias-online-360vo');
            case 'password_reset_mismatch':
                return __('Las contraseñas no coinciden.', 'garantias-online-360vo');
            case 'invalid_password':
            case 'password_too_short':
            case 'password_too_common':
            case 'password_too_similar':
                return __('La contraseña elegida no es válida. Prueba con otra diferente.', 'garantias-online-360vo');
        }

        $message = trim($message);

        return $message !== ''
            ? $message
            : __('No hemos podido restablecer la contraseña. Inténtalo de nuevo.', 'garantias-online-360vo');
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

    private static function redirect_to_reset_password(array $args = [], array $query = []): void
    {
        $url = self::get_reset_password_url();

        if (! empty($query)) {
            $sanitized = self::sanitize_reset_query_args($query);
            if (! empty($sanitized)) {
                $url = add_query_arg($sanitized, $url);
            }
        }

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

    public static function get_reset_password_url(string $login = '', string $key = ''): string
    {
        $url   = network_site_url('wp-login.php', 'login');
        $query = self::build_reset_query_args($login, $key);

        $url = add_query_arg('action', 'rp', $url);

        if (! empty($query)) {
            $url = add_query_arg($query, $url);
        }

        return $url;
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
            'notice'       => isset($state['notice']) ? sanitize_key((string) $state['notice']) : '',
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

    public static function consume_reset_password_state(string $token): array
    {
        $state = self::consume_state(self::RESET_STATE_PREFIX, $token);

        if (empty($state)) {
            return [];
        }

        return [
            'global_error' => isset($state['global_error']) ? sanitize_text_field((string) $state['global_error']) : '',
            'pass1_error'  => isset($state['pass1_error']) ? sanitize_text_field((string) $state['pass1_error']) : '',
            'pass2_error'  => isset($state['pass2_error']) ? sanitize_text_field((string) $state['pass2_error']) : '',
            'login'        => isset($state['login']) ? sanitize_text_field((string) $state['login']) : '',
            'key'          => isset($state['key']) ? sanitize_text_field((string) $state['key']) : '',
            'status'       => isset($state['status']) ? sanitize_key((string) $state['status']) : '',
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
