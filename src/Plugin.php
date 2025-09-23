<?php
// src/Plugin.php

namespace GarantiasOnline360VO;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\ActivityLog\ActivitySubscribers;
use GarantiasOnline360VO\Auth\AuthController;
use GarantiasOnline360VO\Docs\PrivateDocsManager;
use GarantiasOnline360VO\Notifications\Email\EmailNotificationService;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private static $instance;

    /** Singleton */
    public static function run(): void
    {
        if (! self::$instance) {
            self::$instance = new self();
            self::$instance->init_hooks();
        }
    }

    /** Registra todos los hooks globales */
    private function init_hooks(): void
    {
        // 1) Reglas y endpoints
        Rewrite::init();
        Router::init();
        AuthController::init();

        // 2) CPTs y taxonomías (necesario antes de sembrar)
        ModalidadesGarantiasCPT::init();
        GuaranteeCPT::init();
        Taxonomies::init();

        // 3) Seeder: dispara seed_terms() y luego seed_modalidades()
        Seeder::init();

        // 4) Assets (minificado), REST, Admin, etc.
        add_action('init', [AssetCompiler::class, 'ensure_minified'], 1);
        add_action('init', [ActivityLogger::class, 'ensure_table']);
        add_action('init', [ActivitySubscribers::class, 'init']);
        add_action('init', [PrivateDocsManager::class, 'ensure_directory']);
        add_action('rest_api_init', [\GarantiasOnline360VO\Rest\GuaranteeRestController::class, 'register_routes']);

        add_action('rest_api_init', [\GarantiasOnline360VO\Rest\UserRestController::class, 'register_routes']);

        add_action('rest_api_init', [\GarantiasOnline360VO\Rest\OfertasRestController::class, 'register_routes']);

        add_action('rest_api_init', [\GarantiasOnline360VO\Rest\ActivityLogRestController::class, 'register_routes']);



        add_action('rest_api_init', function () {
            $controller = new \GarantiasOnline360VO\Rest\ModalidadesRestController();
            $controller->register_routes();
        });


    


        AdminBar::init();
        if (is_admin()) {
            AdminMenu::init();
            Admin\GuaranteeColumns::init();
        }
        ProfileAvatar::init();
        SettingsPage::init();
        EmailNotificationService::init();

        // 5) Cargar los grupos de campos ACF (solo si ACF está activo)
        add_action('acf/init', function () {
            $acf_file = dirname(__DIR__) . '/acf-fields/acf-fields.php';
            if (file_exists($acf_file)) {
                require_once $acf_file;
            }
        });
    }

    /**
     * Al activar el plugin:
     * 1) Minifica assets
     * 2) Añade roles
     * 3) Marca "pending" para que en el primer init se haga el seed
     */
    public static function activate(): void
    {
        AssetCompiler::ensure_minified();
        Roles::add_roles();
        update_option(Seeder::OPTION_STATUS, 'pending');
        ActivityLogger::create_table();
        PrivateDocsManager::ensure_directory();
        update_option(Rewrite::OPTION_RULES_VERSION, Rewrite::RULES_VERSION);
    }

    /**
     * Al desactivar el plugin:
     * Borra CPT, términos y elimina bandera de seed
     */
    public static function deactivate(): void
    {
        Seeder::clean();
        delete_option(Seeder::OPTION_STATUS);
    }
}

// --- Hooks de activación / desactivación ---
register_activation_hook(GARANTIAS360VO__FILE__,   [Plugin::class, 'activate']);
register_deactivation_hook(GARANTIAS360VO__FILE__, [Plugin::class, 'deactivate']);
