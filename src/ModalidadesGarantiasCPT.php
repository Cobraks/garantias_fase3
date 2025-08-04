<?php
// src/ModalidadesGarantiasCPT.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class ModalidadesGarantiasCPT
{
    const POST_TYPE = 'modalidad_garantia';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_post_type'], 10);
        add_filter('post_updated_messages', [__CLASS__, 'updated_messages']);
    }

    public static function register_post_type(): void
    {
        $labels = [
            'name'                  => __('Modalidades', 'garantias-online-360vo'),
            'singular_name'         => __('Modalidad', 'garantias-online-360vo'),
            'menu_name'             => __('Modalidades', 'garantias-online-360vo'),
            'all_items'             => __('Todas las modalidades', 'garantias-online-360vo'),
            'add_new_item'          => __('Añadir modalidad', 'garantias-online-360vo'),
            'new_item'              => __('Nueva modalidad', 'garantias-online-360vo'),
            'edit_item'             => __('Editar modalidad', 'garantias-online-360vo'),
            'view_item'             => __('Ver modalidad', 'garantias-online-360vo'),
            'search_items'          => __('Buscar modalidades', 'garantias-online-360vo'),
            'not_found'             => __('No se han encontrado modalidades.', 'garantias-online-360vo'),
            'not_found_in_trash'    => __('No hay modalidades en la papelera.', 'garantias-online-360vo'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // oculto del menú principal
            'supports'           => ['title', 'custom-fields'],
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
            
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function updated_messages(array $messages): array
    {
        global $post;
        $permalink = get_permalink($post);

        $messages[self::POST_TYPE] = [
            0  => '',
            1  => __('Modalidad actualizada.', 'garantias-online-360vo'),
            2  => __('Campo personalizado guardado.', 'garantias-online-360vo'),
            3  => __('Campo personalizado eliminado.', 'garantias-online-360vo'),
            4  => __('Modalidad actualizada.', 'garantias-online-360vo'),
            5  => isset($_GET['revision'])
                ? sprintf(
                    __('Modalidad restaurada a la revisión de %s', 'garantias-online-360vo'),
                    wp_post_revision_title((int) $_GET['revision'], false)
                )
                : false,
            6  => __('Modalidad creada.', 'garantias-online-360vo'),
            7  => __('Modalidad guardada.', 'garantias-online-360vo'),
            8  => __('Modalidad publicada.', 'garantias-online-360vo'),
            9  => sprintf(
                __('Modalidad programada para: <strong>%1$s</strong>.', 'garantias-online-360vo'),
                date_i18n(__('M j, Y @ G:i', 'garantias-online-360vo'), strtotime($post->post_date))
            ),
            10 => __('Borrador de modalidad guardado.', 'garantias-online-360vo'),
        ];

        return $messages;
    }
}
