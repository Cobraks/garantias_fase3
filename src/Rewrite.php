<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Rewrite
{

    public const SLUG                 = 'garantias-online';
    public const VAR_ENDPOINT         = 'go_endpoint';
    public const RULES_VERSION        = 6;
    private const OPTION_RULES_VERSION = 'go_rewrite_rules_version';

    /**
     * Marca la versión almacenada como actual sin forzar un flush
     */
    public static function mark_rules_current(): void
    {
        update_option(self::OPTION_RULES_VERSION, self::RULES_VERSION);
    }

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
        $vars[] = 'client_slug';
        $vars[] = 'license_plate';
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

        add_rewrite_rule(
            $base . '/averias/([^/]+)/?$',
            'index.php?' . self::VAR_ENDPOINT . '=averia&license_plate=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            $base . '/clientes/([^/]+)/?$',
            'index.php?' . self::VAR_ENDPOINT . '=clientes&client_slug=$matches[1]',
            'top'
        );

        
    }

    /** Flush helper */
    public static function flush(): void
    {
        flush_rewrite_rules();
        self::mark_rules_current();
    }

    public static function maybe_flush_rules(): void
    {
        $stored_version = (int) get_option(self::OPTION_RULES_VERSION, 0);

        if ($stored_version >= self::RULES_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        self::mark_rules_current();
    }
}
