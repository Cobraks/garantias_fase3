<?php

namespace GarantiasOnline360VO\Auth;

if (! defined('ABSPATH')) {
    exit;
}

class WPLoginStyler
{
    public static function init(): void
    {
        add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_filter('login_headerurl', [__CLASS__, 'filter_header_url']);
        add_filter('login_headertext', [__CLASS__, 'filter_header_text']);
        add_filter('login_body_class', [__CLASS__, 'filter_body_class']);
        add_filter('login_display_language_dropdown', [__CLASS__, 'maybe_hide_language_dropdown']);
    }

    public static function enqueue_styles(): void
    {
        if (! self::is_reset_flow()) {
            return;
        }

        $logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);

        wp_register_style('go-reset-login', false);
        wp_enqueue_style('go-reset-login');

        $css = self::build_reset_styles($logo_url);
        wp_add_inline_style('go-reset-login', $css);
    }

    public static function filter_header_url(string $url): string
    {
        if (! self::is_reset_flow()) {
            return $url;
        }

        return home_url('/garantias-online/login/');
    }

    public static function filter_header_text(string $text): string
    {
        if (! self::is_reset_flow()) {
            return $text;
        }

        return wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    }

    /**
     * @param string[] $classes
     * @return string[]
     */
    public static function filter_body_class(array $classes): array
    {
        if (self::is_reset_flow()) {
            $classes[] = 'go-reset-screen';
        }

        return $classes;
    }

    public static function maybe_hide_language_dropdown(bool $display): bool
    {
        if (! self::is_reset_flow()) {
            return $display;
        }

        return false;
    }

    private static function is_reset_flow(): bool
    {
        $action = isset($_REQUEST['action']) ? wp_unslash($_REQUEST['action']) : '';
        $action = is_string($action) ? strtolower($action) : '';

        return in_array($action, ['rp', 'resetpass'], true);
    }

    private static function build_reset_styles(string $logo_url): string
    {
        $logo_url = esc_url_raw($logo_url);

        return <<<CSS
body.login.go-reset-screen {
    background: #f1f5f9;
    color: #1f2937;
    font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

body.login.go-reset-screen #login {
    width: min(100%, 420px);
    padding: clamp(2.5rem, 6vw, 3rem);
    border-radius: 22px;
    background: #ffffff;
    box-shadow: 0 22px 45px -24px rgba(15, 23, 42, 0.4);
    margin: clamp(4rem, 10vh, 6rem) auto;
}

body.login.go-reset-screen #login h1 {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

body.login.go-reset-screen #login h1 a {
    background-image: url('{$logo_url}');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    width: 220px;
    height: 48px;
    display: block;
    text-indent: -9999px;
}

body.login.go-reset-screen #resetpassform {
    margin: 0;
    padding: 0;
    border: none;
}

body.login.go-reset-screen #resetpassform p {
    margin: 0;
    font-size: 0.95rem;
}

body.login.go-reset-screen #resetpassform .wp-pwd {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

body.login.go-reset-screen #resetpassform label {
    font-weight: 600;
    color: #0f172a;
    display: block;
    margin-bottom: 0.65rem;
}

body.login.go-reset-screen #resetpassform input[type="password"],
body.login.go-reset-screen #resetpassform input[type="text"] {
    width: 100%;
    border-radius: 14px;
    border: 1px solid #d4ddeb;
    padding: 0.95rem 1rem;
    font-size: 1rem;
    font-weight: 500;
    color: #0f172a;
    background: #ffffff;
    transition: border-color 0.2s ease, color 0.2s ease, background-color 0.2s ease;
    box-shadow: none;
}

body.login.go-reset-screen #resetpassform input[type="password"]:focus,
body.login.go-reset-screen #resetpassform input[type="text"]:focus {
    border-color: #000000;
    outline: none;
}

body.login.go-reset-screen #resetpassform .description {
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.75rem;
}

body.login.go-reset-screen #resetpassform .pw-weak,
body.login.go-reset-screen #resetpassform .pw-strong,
body.login.go-reset-screen #resetpassform .pw-medium {
    margin-top: 0.5rem;
}

body.login.go-reset-screen #resetpassform .button,
body.login.go-reset-screen #resetpassform .button-primary,
body.login.go-reset-screen #resetpassform .button-secondary {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    border-radius: 14px;
    border: none;
    padding: 0.95rem 1rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
}

body.login.go-reset-screen #resetpassform .button-primary {
    background: linear-gradient(135deg, #1f2937, #111827);
    color: #ffffff;
    box-shadow: 0 14px 32px -18px rgba(15, 23, 42, 0.65);
}

body.login.go-reset-screen #resetpassform .button-secondary {
    background: linear-gradient(135deg, #1f2937, #111827);
    color: #ffffff;
    box-shadow: 0 14px 32px -18px rgba(15, 23, 42, 0.65);
}

body.login.go-reset-screen #resetpassform .button-secondary:hover,
body.login.go-reset-screen #resetpassform .button-secondary:focus {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px -18px rgba(15, 23, 42, 0.7);
    background: linear-gradient(135deg, #111827, #0f172a);
}

body.login.go-reset-screen #resetpassform .button-primary:hover,
body.login.go-reset-screen #resetpassform .button-primary:focus {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px -18px rgba(15, 23, 42, 0.7);
    background: linear-gradient(135deg, #111827, #0f172a);
}

body.login.go-reset-screen #login .message,
body.login.go-reset-screen #login .notice {
    border-left: none;
    border-radius: 14px;
    padding: 0.9rem 1rem;
    font-size: 0.95rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
}

body.login.go-reset-screen #login .message:not(.error),
body.login.go-reset-screen #login .notice-success {
    background: rgba(16, 185, 129, 0.14);
    color: #047857;
}

body.login.go-reset-screen #login_error,
body.login.go-reset-screen #login .message.error,
body.login.go-reset-screen #login .notice-error {
    background: rgba(248, 113, 113, 0.16);
    color: #b91c1c;
    border-left: none;
    border-radius: 14px;
    padding: 0.9rem 1rem;
    font-size: 0.95rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
}

body.login.go-reset-screen #login form .submit {
    margin-top: 1.75rem;
}

body.login.go-reset-screen #nav,
body.login.go-reset-screen #backtoblog,
body.login.go-reset-screen .language-switcher {
    display: none !important;
}
CSS;
    }
}
