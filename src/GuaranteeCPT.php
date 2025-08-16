<?php

/**
 * Registra el Custom Post Type "Garantía"
 *
 * @package GarantiasOnline360VO
 */

namespace GarantiasOnline360VO;

// Evita acceso directo
if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeCPT
{
    const POST_TYPE = 'garantia';
    /**
     * Lista de estados personalizados permitidos para las garantías.
     */
    public const STATUSES = [
        'draft',          // Borrador
        'pendiente_pago', // Pendiente de pago
        'activada',       // Activada
        'expira_pronto',  // Expira pronto
        'expirada',       // Expirada
    ];

    /**
     * Devuelve el mapa slug => etiqueta de los estados.
     */
    public static function get_status_labels(): array
    {
        return [
            'draft'          => __('Borrador', 'garantias-online-360vo'),
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
        ];
    }

    /**
     * Inicializa los hooks necesarios
     */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_post_statuses']);
        add_filter('post_updated_messages', [__CLASS__, 'updated_messages']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'handle_save'], 10, 3);
        add_action('transition_post_status', [__CLASS__, 'log_status_transition'], 10, 3);
        add_filter('display_post_states', [__CLASS__, 'display_post_states'], 10, 2);
    }

    /**
     * Registra el post type 'garantia'
     */
    public static function register_post_type(): void
    {
        $labels = [
            'name'                  => __('Garantías', 'garantias-online-360vo'),
            'singular_name'         => __('Garantía', 'garantias-online-360vo'),
            'menu_name'             => __('Garantías', 'garantias-online-360vo'),
            'name_admin_bar'        => __('Garantía', 'garantias-online-360vo'),
            'add_new'               => __('Añadir Garantía', 'garantias-online-360vo'),
            'add_new_item'          => __('Añadir nueva garantía', 'garantias-online-360vo'),
            'new_item'              => __('Nueva garantía', 'garantias-online-360vo'),
            'edit_item'             => __('Editar garantía', 'garantias-online-360vo'),
            'view_item'             => __('Ver garantía', 'garantias-online-360vo'),
            'all_items'             => __('Todas las garantías', 'garantias-online-360vo'),
            'search_items'          => __('Buscar garantías', 'garantias-online-360vo'),
            'not_found'             => __('No se han encontrado garantías.', 'garantias-online-360vo'),
            'not_found_in_trash'    => __('No hay garantías en la papelera.', 'garantias-online-360vo'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'capability_type'    => 'garantia',
            'map_meta_cap'       => true,
            'capabilities'       => [
                'edit_post'          => 'edit_garantia',
                'read_post'          => 'read_garantia',
                'delete_post'        => 'delete_garantia',
                'edit_posts'         => 'edit_garantias',
                'edit_others_posts'  => 'edit_others_garantias',
                'publish_posts'      => 'publish_garantias',
                'read_private_posts' => 'read_private_garantias',
                'delete_posts'       => 'delete_garantias',
                'delete_others_posts'=> 'delete_others_garantias',
                'create_posts'       => 'create_garantias',
            ],
            'supports'           => ['title','custom-fields'],
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-awards',
            'has_archive'        => false,
            'show_in_rest'       => false,
            'rewrite'            => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Registra los estados personalizados de las garantías.
     */
    public static function register_post_statuses(): void
    {
        $statuses = self::get_status_labels();
        unset($statuses['draft']);

        foreach ($statuses as $status => $label) {
            register_post_status($status, [
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop(
                    "$label <span class='count'>(%s)</span>",
                    "$label <span class='count'>(%s)</span>",
                    'garantias-online-360vo'
                ),
            ]);
        }
    }

    /**
     * Personaliza los mensajes tras guardar/
     * actualizar el CPT.
     *
     * @param array $messages Array de mensajes por tipo de post.
     * @return array
     */
    public static function updated_messages(array $messages): array
    {
        global $post;
        $permalink = get_permalink($post);

        $messages['garantia'] = [
            0  => '',
            1  => __('Garantía actualizada.', 'garantias-online-360vo'),
            2  => __('Campo personalizado guardado.', 'garantias-online-360vo'),
            3  => __('Campo personalizado eliminado.', 'garantias-online-360vo'),
            4  => __('Garantía actualizada.', 'garantias-online-360vo'),
            5  => isset($_GET['revision']) ? sprintf(__('Garantía restaurada a la revisión de %s', 'garantias-online-360vo'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6  => __('Garantía creada.', 'garantias-online-360vo'),
            7  => __('Garantía guardada.', 'garantias-online-360vo'),
            8  => __('Garantía publicada.', 'garantias-online-360vo'),
            9  => sprintf(__('Garantía programada para: <strong>%1$s</strong>.', 'garantias-online-360vo'), date_i18n(__('M j, Y @ G:i', 'garantias-online-360vo'), strtotime($post->post_date))),
            10 => __('Borrador de garantía guardado.', 'garantias-online-360vo'),

        ];

        return $messages;
    }

    public static function handle_save($post_id, $post, $update): void
    {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        $user = get_current_user_id();
        if (! $update) {
            GuaranteeLogger::log($user, $post_id, 'created');
        }
    }

    public static function log_status_transition($new_status, $old_status, $post): void
    {
        if ($post->post_type !== self::POST_TYPE || $new_status === $old_status) {
            return;
        }
        $user    = get_current_user_id();
        $details = sprintf('De %s a %s', $old_status, $new_status);
        GuaranteeLogger::log($user, $post->ID, 'status_changed', $details);
    }

    /**
     * Muestra etiquetas legibles en el listado de WP-Admin.
     */
    public static function display_post_states($states, $post)
    {
        if ($post->post_type !== self::POST_TYPE) {
            return $states;
        }
        $map = self::get_status_labels();
        $status = get_post_status($post);
        if (isset($map[$status])) {
            $states[$status] = $map[$status];
        }
        return $states;
    }
}
