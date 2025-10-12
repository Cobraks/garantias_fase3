<?php

namespace GarantiasOnline360VO;

use GarantiasOnline360VO\Account\AccountViewModel;

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
        if (! is_user_logged_in() && ! in_array($endpoint, ['home', 'register', 'login', 'lostpassword', 'resetpassword'], true)) {
            wp_safe_redirect(home_url('/garantias-online/'));
            exit;
        }

        $is_admin_user = current_user_can('manage_options');

        if (is_user_logged_in() && in_array($endpoint, ['login', 'lostpassword', 'resetpassword'], true)) {
            $destination = $is_admin_user
                ? home_url('/garantias-online/')
                : home_url('/garantias-online/mis-garantias/');
            wp_safe_redirect($destination);
            exit;
        }

        if (is_user_logged_in() && ! $is_admin_user && in_array($endpoint, ['dashboard', 'home'], true)) {
            wp_safe_redirect(home_url('/garantias-online/mis-garantias/'));
            exit;
        }

        if (in_array($endpoint, ['averias', 'averia', 'clientes'], true) && ! $is_admin_user) {
            wp_safe_redirect(home_url('/garantias-online/mis-garantias/'));
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
            case 'averia':
                $license_plate = get_query_var('license_plate');
                $license_plate = is_string($license_plate)
                    ? sanitize_text_field(wp_unslash($license_plate))
                    : '';

                TemplateLoader::load('averia', [
                    'license_plate' => $license_plate,
                ]);
                break;
            case 'clientes':
                TemplateLoader::load('clientes');
                break;
            case 'login':
                TemplateLoader::load('login');
                break;
            case 'lostpassword':
                TemplateLoader::load('lost-password');
                break;
            case 'resetpassword':
                TemplateLoader::load('reset-password');
                break;
            case 'register':
                TemplateLoader::load('register');
                break;
            case 'account':
                $account_view = AccountViewModel::for_current_user();
                TemplateLoader::load('account', [
                    'account'          => $account_view,
                    'is_account_page'  => true,
                ]);
                break;
            case 'dashboard':
            default:
                TemplateLoader::load('dashboard');
                break;
        }
        exit;
    }


    
}
