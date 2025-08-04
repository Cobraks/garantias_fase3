<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Router
{

    /** Arranca el router; lo llamas en Plugin::init_hooks() */
    public static function init(): void
    {
        add_action('template_redirect', [__CLASS__, 'dispatch']);
    }

    /** Decide qué hacer según el endpoint */
    public static function dispatch(): void
    {
        $endpoint = get_query_var(Rewrite::VAR_ENDPOINT);

        if (! $endpoint) {
            return;
        }

/*
ARREGLAR. NO TIENE SENTIDO EL 'HOME' EN ESE ARRAY


*/

        // si no está logueado y no viene a home o register, fuerza raíz:
        if (! is_user_logged_in() && ! in_array($endpoint, ['home', 'register'], true)) {
            wp_safe_redirect(home_url('/garantias-online/'));
            exit;
        }

        status_header(200);

        switch ($endpoint) {
            case 'add':
                TemplateLoader::load('add-guarantee');
                break;
            case 'list':
                TemplateLoader::load('list-guarantees');
                break;
            case 'averias':
                TemplateLoader::load('averias');
                break;
            case 'login':
                TemplateLoader::load('login');
                break;
            case 'register':
                TemplateLoader::load('register');
                break;
            case 'dashboard':
            default:
                TemplateLoader::load('dashboard');
                break;
        }
        exit;
    }


    
}
