<?php

namespace GarantiasOnline360VO\Auth;

use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class PasswordResetMailer
{
    public static function init(): void
    {
        add_filter('retrieve_password_notification_email', [__CLASS__, 'filter_notification_email'], 10, 4);
    }

    /**
     * @param array<string,mixed> $email
     * @param string $key
     * @param string $user_login
     * @param WP_User $user
     * @return array<string,mixed>
     */
    public static function filter_notification_email(array $email, string $key, string $user_login, WP_User $user): array
    {
        $user_login = $user->user_login ?? $user_login;
        $user_login = is_string($user_login) ? $user_login : '';
        $key        = trim($key);

        if ($user_login === '' || $key === '') {
            return $email;
        }

        $reset_url = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user_login),
            'login'
        );

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject   = sprintf(__('[%s] Restablece tu contraseña', 'garantias-online-360vo'), $site_name);

        $renderer = new TemplateRenderer();
        $message  = $renderer->render('password-reset', [
            'reset_url'   => $reset_url,
            'site_name'   => $site_name,
            'user_login'  => $user_login,
            'support_url' => home_url('/garantias-online/'),
        ]);

        if ($message === '') {
            return $email;
        }

        $headers = $email['headers'] ?? [];
        if (! is_array($headers)) {
            $headers = $headers !== '' ? preg_split('/\r?\n/', (string) $headers) : [];
        }

        $headers = is_array($headers) ? array_filter(array_map('trim', $headers)) : [];
        $headers = array_values(array_filter(
            $headers,
            static fn($header) => stripos((string) $header, 'content-type:') === false
        ));
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        $email['subject'] = $subject;
        $email['message'] = $message;
        $email['headers'] = $headers;

        return $email;
    }
}
