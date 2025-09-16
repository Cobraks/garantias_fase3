<?php
// src/AdminMenu.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
    /** @var string|null */
    private static $activeTab;

    public static function init(): void
    {
        // 1) Submenú “Modalidades” bajo “Garantías”
        add_action('admin_menu', [__CLASS__, 'add_modalidades_submenu'], 20);

        // 2) Resaltar siempre Garantías > Modalidades en lateral
        add_action('current_screen', [__CLASS__, 'maybe_highlight_modalidades'], 20);

        // 3) Pintar el menú horizontal en todas las pantallas de Modalidades
        add_action('load-edit.php',      [__CLASS__, 'maybe_paint_hz_menu'], 0);
        add_action('load-post.php',      [__CLASS__, 'maybe_paint_hz_menu'], 0);
        add_action('load-post-new.php',  [__CLASS__, 'maybe_paint_hz_menu'], 0);
        add_action('load-edit-tags.php', [__CLASS__, 'maybe_paint_hz_menu'], 0);
        add_action('load-' . GuaranteeCPT::POST_TYPE . '_page_' . SettingsPage::SUBMENU_SLUG, [__CLASS__, 'maybe_paint_hz_menu'], 0);
    }

    public static function add_modalidades_submenu(): void
    {
        $parent = 'edit.php?post_type=' . GuaranteeCPT::POST_TYPE;
        add_submenu_page(
            $parent,
            __('Modalidades', 'garantias-online-360vo'),
            __('Modalidades', 'garantias-online-360vo'),
            'manage_options',
            'edit.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE
        );
    }

    public static function maybe_highlight_modalidades(\WP_Screen $screen): void
    {
        $pt  = ModalidadesGarantiasCPT::POST_TYPE;
        $tax = ['tipo_garantia', 'nivel_garantia', 'tipo_vehiculo'];

        $is_modalidades_cpt  = in_array($screen->post_type, [$pt], true);
        $is_modalidades_tax  = $screen->base === 'edit-tags' && in_array($screen->taxonomy, $tax, true);

        if (! $is_modalidades_cpt && ! $is_modalidades_tax) {
            return;
        }

        // Lateral: Garantías > Modalidades
        add_filter('parent_file',  function () {
            return 'edit.php?post_type=' . GuaranteeCPT::POST_TYPE;
        });
        add_filter('submenu_file', function () {
            return 'edit.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE;
        });
    }

    public static function maybe_paint_hz_menu(): void
    {
        $screen = get_current_screen();
        if (! $screen) {
            return;
        }

        $pt         = ModalidadesGarantiasCPT::POST_TYPE;
        $taxes      = ['tipo_garantia', 'nivel_garantia', 'tipo_vehiculo'];

        $is_cpt     = in_array($screen->base, ['edit', 'post', 'post-new'], true)
            && $screen->post_type === $pt;
        $is_tax     = $screen->base === 'edit-tags'
            && in_array($screen->taxonomy, $taxes, true)
            && $screen->post_type === $pt;
        $requested_page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        $is_settings = $screen->base === GuaranteeCPT::POST_TYPE . '_page_' . SettingsPage::SUBMENU_SLUG
            || $requested_page === SettingsPage::SUBMENU_SLUG;

        if (! ($is_cpt || $is_tax || $is_settings)) {
            return;
        }

        if ($is_settings) {
            self::$activeTab = 'settings';
        } elseif ($is_tax) {
            $map = [
                'tipo_garantia' => 'tipo_garantia',
                'nivel_garantia' => 'nivel_garantia',
                'tipo_vehiculo' => 'tipo_vehiculo',
            ];
            self::$activeTab = $map[$screen->taxonomy] ?? 'modalidades';
        } else {
            self::$activeTab = 'modalidades';
        }

        add_action('admin_notices', [__CLASS__, 'paint_hz_menu'], 0);
    }

    public static function paint_hz_menu(): void
    {
        $items = [
            'modalidades'    => [
                'label' => __('Todas las modalidades', 'garantias-online-360vo'),
                'url'   => admin_url('edit.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            ],
            'tipo_garantia'  => [
                'label' => __('Tipos de garantía', 'garantias-online-360vo'),
                'url'   => admin_url('edit-tags.php?taxonomy=tipo_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            ],
            'nivel_garantia' => [
                'label' => __('Niveles de garantía', 'garantias-online-360vo'),
                'url'   => admin_url('edit-tags.php?taxonomy=nivel_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            ],
            'tipo_vehiculo'  => [
                'label' => __('Tipos de vehículo', 'garantias-online-360vo'),
                'url'   => admin_url('edit-tags.php?taxonomy=tipo_vehiculo&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            ],
            'settings'       => [
                'label' => __('Personalización garantías', 'garantias-online-360vo'),
                'url'   => admin_url('admin.php?page=' . SettingsPage::SUBMENU_SLUG),
            ],
        ];

        echo '<div class="notice inline go360-hz-menu" style="margin:0;padding-bottom:8px;border:none;">';
        echo '<ul style="list-style:none;margin:0;padding:0;display:flex;gap:1rem;">';
        $active = self::$activeTab ?? 'modalidades';
        foreach ($items as $key => $item) {
            $style = ($key === $active)
                ? 'font-weight:600;text-decoration:underline;'
                : '';
            printf(
                '<li><a href="%s" style="padding:4px 8px;%s">%s</a></li>',
                esc_url($item['url']),
                esc_attr($style),
                esc_html($item['label'])
            );
        }
        echo '</ul></div>';
    }
}
