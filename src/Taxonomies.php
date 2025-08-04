<?php
// src/Taxonomies.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Taxonomies
{
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_tipo_garantia'], 10);
        add_action('init', [__CLASS__, 'register_nivel_garantia'], 10);
        add_action('init', [__CLASS__, 'register_tipo_vehiculo'], 10);
        add_action('admin_menu', [__CLASS__, 'add_taxonomy_submenus'], 20);
    }

    public static function register_tipo_garantia(): void
    {
        $labels = [
            'name'                       => __('Tipos de garantía', 'garantias-online-360vo'),
            'singular_name'              => __('Tipo de garantía', 'garantias-online-360vo'),
            'menu_name'                  => __('Tipos de garantía', 'garantias-online-360vo'),
            'all_items'                  => __('Todos los tipos de garantía', 'garantias-online-360vo'),
            'edit_item'                  => __('Editar tipo de garantía', 'garantias-online-360vo'),
            'view_item'                  => __('Ver tipo de garantía', 'garantias-online-360vo'),
            'update_item'                => __('Actualizar tipo de garantía', 'garantias-online-360vo'),
            'add_new_item'               => __('Añadir tipo de garantía', 'garantias-online-360vo'),
            'new_item_name'              => __('Nuevo tipo de garantía', 'garantias-online-360vo'),
            'search_items'               => __('Buscar tipos de garantía', 'garantias-online-360vo'),
            'popular_items'              => __('Tipos más usados', 'garantias-online-360vo'),
            'not_found'                  => __('No se han encontrado tipos.', 'garantias-online-360vo'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu' => false,
            'show_in_rest'      => false,
            'rewrite'           => false,
            'meta_box_cb'       => false,
        ];

        register_taxonomy(
            'tipo_garantia',
            [ModalidadesGarantiasCPT::POST_TYPE, GuaranteeCPT::POST_TYPE],
            $args
        );
    }

    public static function register_nivel_garantia(): void
    {
        $labels = [
            'name'                  => __('Niveles de garantía', 'garantias-online-360vo'),
            'singular_name'         => __('Nivel de garantía', 'garantias-online-360vo'),
            'menu_name'             => __('Niveles de garantía', 'garantias-online-360vo'),
            'all_items'             => __('Todos los niveles', 'garantias-online-360vo'),
            'edit_item'             => __('Editar nivel', 'garantias-online-360vo'),
            'view_item'             => __('Ver nivel', 'garantias-online-360vo'),
            'update_item'           => __('Actualizar nivel', 'garantias-online-360vo'),
            'add_new_item'          => __('Añadir nivel', 'garantias-online-360vo'),
            'new_item_name'         => __('Nuevo nivel', 'garantias-online-360vo'),
            'search_items'          => __('Buscar niveles', 'garantias-online-360vo'),
            'popular_items'         => __('Niveles más usados', 'garantias-online-360vo'),
            'not_found'             => __('No se han encontrado niveles.', 'garantias-online-360vo'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu' => false,
            'show_in_rest'      => false,
            'rewrite'           => false,
            'meta_box_cb'       => false,
        ];

        register_taxonomy(
            'nivel_garantia',
            [ModalidadesGarantiasCPT::POST_TYPE, GuaranteeCPT::POST_TYPE],
            $args
        );
    }

    public static function register_tipo_vehiculo(): void
    {
        $labels = [
            'name'                  => __('Tipos de vehículo', 'garantias-online-360vo'),
            'singular_name'         => __('Tipo de vehículo', 'garantias-online-360vo'),
            'menu_name'             => __('Tipos de vehículo', 'garantias-online-360vo'),
            'all_items'             => __('Todos los tipos de vehículo', 'garantias-online-360vo'),
            'edit_item'             => __('Editar tipo de vehículo', 'garantias-online-360vo'),
            'view_item'             => __('Ver tipo de vehículo', 'garantias-online-360vo'),
            'update_item'           => __('Actualizar tipo de vehículo', 'garantias-online-360vo'),
            'add_new_item'          => __('Añadir tipo de vehículo', 'garantias-online-360vo'),
            'new_item_name'         => __('Nuevo tipo de vehículo', 'garantias-online-360vo'),
            'search_items'          => __('Buscar tipos de vehículo', 'garantias-online-360vo'),
            'popular_items'         => __('Tipos más usados', 'garantias-online-360vo'),
            'not_found'             => __('No se han encontrado tipos.', 'garantias-online-360vo'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'show_in_menu' => false,
            'rewrite'           => false,
            'meta_box_cb'       => false,
        ];

        register_taxonomy(
            'tipo_vehiculo',
            [ModalidadesGarantiasCPT::POST_TYPE, GuaranteeCPT::POST_TYPE],
            $args
        );
    }

    public static function add_taxonomy_submenus(): void
    {
        $parent = 'edit.php?post_type=' . ModalidadesGarantiasCPT::POST_TYPE;

        add_submenu_page(
            $parent,
            __('Tipos de garantía', 'garantias-online-360vo'),
            __('Tipos de garantía', 'garantias-online-360vo'),
            'manage_categories',
            'edit-tags.php?taxonomy=tipo_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE
        );

        add_submenu_page(
            $parent,
            __('Niveles de garantía', 'garantias-online-360vo'),
            __('Niveles de garantía', 'garantias-online-360vo'),
            'manage_categories',
            'edit-tags.php?taxonomy=nivel_garantia&post_type=' . ModalidadesGarantiasCPT::POST_TYPE
        );

        add_submenu_page(
            $parent,
            __('Tipos de vehículo', 'garantias-online-360vo'),
            __('Tipos de vehículo', 'garantias-online-360vo'),
            'manage_categories',
            'edit-tags.php?taxonomy=tipo_vehiculo&post_type=' . ModalidadesGarantiasCPT::POST_TYPE
        );
    }
}
