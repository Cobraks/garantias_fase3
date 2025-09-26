<?php

namespace GarantiasOnline360VO\Auth;

use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use GarantiasOnline360VO\Support\UserProfileResolver;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class PasswordResetMailer
{
    public static function init(): void
    {
        add_filter('retrieve_password_notification_email', [__CLASS__, 'filter_notification_email'], 10, 4);
        add_filter('password_change_admin_email', [__CLASS__, 'filter_admin_password_change_email'], 10, 3);
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

        $reset_url = AuthController::get_reset_password_url($user_login, $key);

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject   = sprintf(__('[%s] Restablece tu contraseña', 'garantias-online-360vo'), $site_name);

        $labels = UserProfileResolver::get_vendor_labels((int) $user->ID);
        $personal_name = $labels['personal_full_name'] ?? '';
        if ($personal_name === '') {
            $personal_name = $labels['personal_name'] ?? '';
        }

        $company = $labels['company'] ?? [];
        $company_name = '';
        if (! empty($company['trade_name'])) {
            $company_name = $company['trade_name'];
        } elseif (! empty($company['legal_name'])) {
            $company_name = $company['legal_name'];
        } elseif (! empty($labels['company_name']) && $labels['company_name'] !== $personal_name) {
            $company_name = $labels['company_name'];
        }

        $renderer = new TemplateRenderer();
        $message  = $renderer->render('password-reset', [
            'reset_url'     => $reset_url,
            'site_name'     => $site_name,
            'user_login'    => $user_login,
            'support_url'   => home_url('/garantias-online/'),
            'personal_name' => $personal_name,
            'company_name'  => $company_name,
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
                && stripos((string) $header, 'from:') === false
        ));

        $admin_email = sanitize_email(get_option('admin_email'));
        if ($admin_email === '') {
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);
            $admin_email = $domain ? 'no-reply@' . ltrim($domain, '.') : 'no-reply@example.com';
        }

        $from_name = '360VO';
        $from      = sprintf('%s <%s>', $from_name, $admin_email);

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $from;

        $email['subject'] = $subject;
        $email['message'] = $message;
        $email['headers'] = $headers;

        return $email;
}

    /**
     * @param array<string,mixed> $email
     * @return array<string,mixed>
     */
    public static function filter_admin_password_change_email(array $email, WP_User $user, string $blogname): array
    {
        $renderer = new TemplateRenderer();

        $site_name = wp_specialchars_decode($blogname ?: get_option('blogname'), ENT_QUOTES);
        $user_login = $user->user_login ?? '';
        $user_email = $user->user_email ?? '';

        $message = $renderer->render('password-change-admin', [
            'site_name'  => $site_name,
            'user_login' => $user_login,
            'user_email' => $user_email,
            'profile_url' => admin_url('user-edit.php?user_id=' . (int) $user->ID),
        ]);

        if ($message === '') {
            return $email;
        }

        $admin_email = isset($email['to']) ? sanitize_email((string) $email['to']) : '';
        if ($admin_email === '') {
            $admin_email = sanitize_email(get_option('admin_email'));
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

        $from_email = sanitize_email(get_option('admin_email'));
        if ($from_email === '') {
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);
            $from_email = $domain ? 'no-reply@' . ltrim($domain, '.') : 'no-reply@example.com';
        }

        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf('From: 360VO <%s>', $from_email);

        $email['to']      = $admin_email;
        $email['subject'] = sprintf(__('[%s] Contraseña de usuario actualizada', 'garantias-online-360vo'), $site_name);
        $email['message'] = $message;
        $email['headers'] = $headers;

        return $email;
    }
}
