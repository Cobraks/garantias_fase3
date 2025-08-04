<?php
// src/AdminMenu.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class AdminMenu
{
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

        if (! ($is_cpt || $is_tax)) {
            return;
        }

        add_action('admin_notices', [__CLASS__, 'paint_hz_menu'], 0);
    }

    public static function paint_hz_menu(): void
    {
        $uri   = $_SERVER['REQUEST_URI'];
        $items = [
            'Todas las modalidades' => admin_url('edit.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            // 'Añadir modalidad'      => admin_url('post-new.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE), // eliminado
            'Tipos de garantía'     => admin_url('edit-tags.php?taxonomy=tipo_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            'Niveles de garantía'   => admin_url('edit-tags.php?taxonomy=nivel_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
            'Tipos de vehículo'     => admin_url('edit-tags.php?taxonomy=tipo_vehiculo&post_type=' . ModalidadesGarantiasCPT::POST_TYPE),
        ];

        echo '<div class="notice inline go360-hz-menu" style="margin:0;padding-bottom:8px;border:none;">';
        echo '<ul style="list-style:none;margin:0;padding:0;display:flex;gap:1rem;">';
        foreach ($items as $label => $url) {
            $active = (strpos($uri, $url) === 0)
                ? 'font-weight:bold;text-decoration:underline;'
                : '';
            printf(
                '<li><a href="%s" style="padding:4px 8px;%s">%s</a></li>',
                esc_url($url),
                esc_attr($active),
                esc_html__($label, 'garantias-online-360vo')
            );
        }
        echo '</ul></div>';
    }
}
