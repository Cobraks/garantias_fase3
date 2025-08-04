<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Añade un link a Garantías Online en la admin bar
 */
class AdminBar
{
    public static function init(): void
    {
        add_action('admin_bar_menu', [__CLASS__, 'add_item'], 100);
    }

    public static function add_item(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        $args = [
            'id'    => 'go360-dashboard',
            'title' => self::get_icon_svg() . ' ' . __('Garantías', 'garantias-online-360vo'),
            'href'  => home_url('/garantias-online/'),
            'meta'  => [
                'class' => 'go360-admin-bar-item',
                'title' => __('Ir a Garantías Online', 'garantias-online-360vo'),
            ],
        ];

        $wp_admin_bar->add_node($args);
    }

    private static function get_icon_svg(): string
    {
        return '<span class="go360-admin-bar-icon" style="display:inline-block;vertical-align:middle;margin-right:.25em;">'
            . '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">'
            .   '<path d="M12 2L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-3z"/>'
            . '</svg>'
            . '</span>';
    }
}
