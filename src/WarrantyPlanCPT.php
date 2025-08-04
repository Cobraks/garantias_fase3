<?php
//BORRAR; YA USAMOS MODALIDADES GARANTIAS CPT
namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class WarrantyPlanCPT
{

    const POST_TYPE = 'warranty_plan';

    public static function init(): void
    {
        add_action('init',  [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type(): void
    {

        // ‣ NUEVOS TEXTOS
        $labels = [
            'name'               => __('Modalidades',              'garantias-online-360vo'),
            'singular_name'      => __('Modalidad',                'garantias-online-360vo'),
            'add_new'            => __('Añadir Modalidad',         'garantias-online-360vo'),
            'add_new_item'       => __('Añadir nueva modalidad',   'garantias-online-360vo'),
            'edit_item'          => __('Editar modalidad',         'garantias-online-360vo'),
            'new_item'           => __('Nueva modalidad',          'garantias-online-360vo'),
            'view_item'          => __('Ver modalidad',            'garantias-online-360vo'),
            'search_items'       => __('Buscar modalidades',       'garantias-online-360vo'),
            'not_found'          => __('No se han encontrado modalidades.', 'garantias-online-360vo'),
            'menu_name'          => __('Modalidades',              'garantias-online-360vo'),
            'name_admin_bar'     => __('Modalidad',                'garantias-online-360vo'),
        ];

        $args = [
            'labels'         => $labels,
            'public'         => false,
            'show_ui'        => true,
            'show_in_menu'   => 'edit.php?post_type=' . GuaranteeCPT::POST_TYPE,
            // 'show_in_rest'   => true,
            'supports'       => ['title'],
            'menu_icon'      => 'dashicons-clipboard',
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}
