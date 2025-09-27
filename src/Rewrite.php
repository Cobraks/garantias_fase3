<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Rewrite
{

    public const SLUG                 = 'garantias-online';
    public const VAR_ENDPOINT         = 'go_endpoint';
    public const RULES_VERSION        = 4;
    private const OPTION_RULES_VERSION = 'go_rewrite_rules_version';

    /** Hook setup */
    public static function init(): void
    {
        add_filter('query_vars',        [__CLASS__, 'add_query_vars']);
        add_action('init',              [__CLASS__, 'add_rules']);
        add_action('init',              [__CLASS__, 'maybe_flush_rules'], 20);

        // Flush al (des)activar
        register_activation_hook(GARANTIAS360VO__FILE__,   [__CLASS__, 'flush']);
        register_deactivation_hook(GARANTIAS360VO__FILE__, [__CLASS__, 'flush']);
    }

    /** Añade nuestra query var */
    public static function add_query_vars(array $vars): array
    {
        $vars[] = self::VAR_ENDPOINT;
        $vars[] = 'error';
        $vars[] = 'email';
        $vars[] = 'remember';
        $vars[] = 'redirect_to';
        $vars[] = 'sent';
        $vars[] = 'state';
        $vars[] = 'reset';
        $vars[] = 'key';
        $vars[] = 'login';
        return $vars;
    }

    /** Registra reglas legibles */
    public static function add_rules(): void
    {

        $base = '^' . self::SLUG;

        $map = [
            '/?$'                     => 'home',
            '/nueva-garantia/?$'      => 'add',
            '/mis-garantias/?$'       => 'list',
            '/login/?$'               => 'login',
            '/restablecer-clave/?$'   => 'lostpassword',
            '/restablecer-clave/nueva/?$' => 'resetpassword',
            '/registro/?$'            => 'register',
            '/averias/?$'             => 'averias',
            '/mi-cuenta/?$'           => 'account',
            '/clientes/?$'            => 'clientes',
        ];

        foreach ($map as $regex => $endpoint) {
            add_rewrite_rule(
                $base . $regex,
                'index.php?' . self::VAR_ENDPOINT . '=' . $endpoint,
                'top'
            );
        }

        
    }

    /** Flush helper */
    public static function flush(): void
    {
        flush_rewrite_rules();
        update_option(self::OPTION_RULES_VERSION, self::RULES_VERSION);
    }

    public static function maybe_flush_rules(): void
    {
        $stored_version = (int) get_option(self::OPTION_RULES_VERSION, 0);

        if ($stored_version >= self::RULES_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::OPTION_RULES_VERSION, self::RULES_VERSION);
    }
}
