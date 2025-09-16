<?php
// src/SettingsPage.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    public const SUBMENU_SLUG = 'go-guarantee-settings';
    public const CAPABILITY  = 'manage_options';

    /** @var string|null */
    private static $currentPage;

    /** Hook setup */
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_page'], 30);
        add_action('acf/init', [__CLASS__, 'register_acf_integration']);
        add_action(
            'load-' . GuaranteeCPT::POST_TYPE . '_page_' . self::SUBMENU_SLUG,
            [__CLASS__, 'prepare_screen'],
            0
        );
    }

    /** Registers submenu page under Garantías */
    public static function register_page(): void
    {
        $parent = 'edit.php?post_type=' . GuaranteeCPT::POST_TYPE;

        add_submenu_page(
            $parent,
            __('Personalización garantías', 'garantias-online-360vo'),
            __('Personalización garantías', 'garantias-online-360vo'),
            self::CAPABILITY,
            self::SUBMENU_SLUG,
            [__CLASS__, 'render']
        );
    }

    /** Prepares screen for ACF form output */
    public static function prepare_screen(): void
    {
        if (function_exists('acf_form_head')) {
            acf_form_head();
        }
    }

    /** Adds the page to ACF location rules */
    public static function register_acf_integration(): void
    {
        add_filter('acf/location/rule_values/options_page', [__CLASS__, 'add_location_rule']);
        add_filter('acf/location/rule_match/options_page', [__CLASS__, 'match_location_rule'], 10, 4);
    }

    /** Adds our slug to the Options Page selector */
    public static function add_location_rule($choices)
    {
        if (! is_array($choices)) {
            $choices = [];
        }

        $choices[self::SUBMENU_SLUG] = __('Personalización garantías', 'garantias-online-360vo');

        return $choices;
    }

    /** Matches the ACF location rule when our page is loaded */
    public static function match_location_rule($match, $rule, $options, $field_group)
    {
        if (! isset($rule['value']) || $rule['value'] !== self::SUBMENU_SLUG) {
            return $match;
        }

        $current = $options['options_page'] ?? self::get_current_page();

        if ($rule['operator'] === '==') {
            return $current === self::SUBMENU_SLUG;
        }

        if ($rule['operator'] === '!=') {
            return $current !== self::SUBMENU_SLUG;
        }

        return $match;
    }

    /** Renders the settings page */
    public static function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos.', 'garantias-online-360vo'));
        }

        $title = __('Personalización garantías', 'garantias-online-360vo');

        echo '<div class="wrap go360-settings-page">';
        echo '<h1>' . esc_html($title) . '</h1>';
        do_action('go360_settings_top');

        if (function_exists('acf_form')) {
            acf_form([
                'post_id'         => self::SUBMENU_SLUG,
                'form'            => true,
                'form_attributes' => [
                    'id'    => self::SUBMENU_SLUG . '-form',
                    'class' => 'acf-form',
                ],
                'submit_value'    => __('Guardar cambios', 'garantias-online-360vo'),
                'updated_message' => __('Opciones actualizadas.', 'garantias-online-360vo'),
            ]);
        } else {
            echo '<p>' . esc_html__('Advanced Custom Fields no está activo.', 'garantias-online-360vo') . '</p>';
        }

        echo '</div>';
    }

    private static function get_current_page(): string
    {
        if (self::$currentPage !== null) {
            return self::$currentPage;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        self::$currentPage = $page;

        return self::$currentPage;
    }
}
