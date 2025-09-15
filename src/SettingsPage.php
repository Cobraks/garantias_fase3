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

    /** Hook setup */
    public static function init(): void
    {
        // Register page under Garantías menu
        add_action('admin_menu', [__CLASS__, 'register_page'], 1000);
        // Register page for ACF options (if available)
        add_action('acf/init', [__CLASS__, 'register_acf_page']);
    }

    /** Registers submenu page */
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

    /** Register page in ACF so fields can be attached */
    public static function register_acf_page(): void
    {
        if (! function_exists('acf_add_options_sub_page')) {
            return;
        }

        acf_add_options_sub_page([
            'page_title'  => __('Personalización garantías', 'garantias-online-360vo'),
            'menu_title'  => __('Personalización garantías', 'garantias-online-360vo'),
            'parent_slug' => 'edit.php?post_type=' . GuaranteeCPT::POST_TYPE,
            'menu_slug'   => self::SUBMENU_SLUG,
            'capability'  => self::CAPABILITY,
            'redirect'    => false,
        ]);
    }

    /** Render page content */
    public static function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos.', 'garantias-online-360vo'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Personalización garantías', 'garantias-online-360vo'); ?></h1>
            <?php do_action('go360_settings_top'); ?>
            <?php if (function_exists('acf_form')) { ?>
                <?php acf_form(['post_id' => self::SUBMENU_SLUG]); ?>
            <?php } else { ?>
                <p><?php esc_html_e('Advanced Custom Fields no está activo.', 'garantias-online-360vo'); ?></p>
            <?php } ?>
        </div>
        <?php
    }
}
