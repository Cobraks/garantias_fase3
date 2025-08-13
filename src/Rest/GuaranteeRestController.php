<?php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Server;
use WP_Query;
use WP_REST_Response;

class GuaranteeRestController
{
    const NAMESPACE = 'go/v1';
    const BASE      = 'guarantees';

    public static function register_routes()
    {
        $default_per_page = 12;
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_items'],
                    'permission_callback' => [__CLASS__, 'can_list'],
                    'args'                => [
                        'page'         => [
                            'validate_callback' => 'absint',
                            'default'           => 1,
                        ],
                        'per_page'     => [
                            'validate_callback' => 'absint',
                            'default'           => $default_per_page,
                        ],
                        'search'       => ['sanitize_callback' => 'sanitize_text_field'],
                        'estado'       => ['sanitize_callback' => 'sanitize_text_field'],
                        'plan'         => ['validate_callback' => 'absint'],
                        'canal'        => ['sanitize_callback' => 'sanitize_text_field'],
                        'concesionario'=> ['validate_callback' => 'absint'],
                    ],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/filters',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_filters'],
                    'permission_callback' => [__CLASS__, 'can_list'],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/autosave',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'autosave'],
                    'permission_callback' => [__CLASS__, 'can_edit'],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_item'],
                    'permission_callback' => [__CLASS__, 'can_view'],
                    'args'                => [
                        'id' => ['validate_callback' => 'absint'],
                    ],
                ],
            ]
        );

        // Limpieza de transients al guardar/borrar garantías
        add_action('save_post_' . \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE, [__CLASS__, 'clear_list_transients'], 10, 3);
        add_action('deleted_post', [__CLASS__, 'clear_list_transients_on_delete']);
    }

    public static function can_list($request)
    {
        return is_user_logged_in();
    }

    public static function can_view($request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = get_current_user_id();

        // Admin puede ver todo
        if (current_user_can('manage_options')) {
            return true;
        }

        $post_id = (int) $request['id'];

        // Obtener el usuario propietario profesional
        $profesional = get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $profesional_id = is_array($profesional) && isset($profesional['ID']) ? $profesional['ID'] : $profesional;

        if ((int)$current_user === (int)$profesional_id) {
            return true;
        }

        if ($profesional_id) {
            $comerciales = get_field('ajustes_usuarios_comercial_asignado', 'user_' . $profesional_id);
            $comercial_ids = [];
            if (is_array($comerciales)) {
                foreach ($comerciales as $comercial) {
                    if (is_array($comercial) && isset($comercial['ID'])) {
                        $comercial_ids[] = (int) $comercial['ID'];
                    } else {
                        $comercial_ids[] = (int) $comercial;
                    }
                }
            }
            if (in_array((int)$current_user, $comercial_ids, true)) {
                return true;
            }
        }

        return false;
    }

    public static function can_edit($request)
    {
        return is_user_logged_in();
    }

    public static function autosave($request)
    {
        $post_id = isset($request['id']) ? absint($request['id']) : 0;
        $data    = isset($request['data']) && is_array($request['data']) ? $request['data'] : [];

        if ($post_id > 0) {
            $post = get_post($post_id);
            if (!$post || $post->post_type !== \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
                $post_id = 0;
            }
        }

        if ($post_id === 0) {
            $post_id = wp_insert_post([
                'post_type'   => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
                'post_status' => 'draft',
                'post_title'  => __('Borrador de garantía', 'garantias-online-360vo'),
                'post_author' => get_current_user_id(),
            ]);
        }

        foreach ($data as $key => $value) {
            $meta_key = sanitize_key($key);
            $meta_val = is_scalar($value) ? sanitize_text_field($value) : wp_json_encode($value);
            update_post_meta($post_id, $meta_key, $meta_val);
        }

        return new WP_REST_Response(['id' => $post_id]);
    }

    /**
     * Listado paginado, cacheado por usuario y página.
     */
    public static function get_items($request)
    {
        $current_user  = get_current_user_id();
        $page          = absint($request['page']);
        $per_page      = absint($request['per_page']);
        $search        = isset($request['search']) ? sanitize_text_field($request['search']) : '';
        $estado        = isset($request['estado']) ? sanitize_text_field($request['estado']) : '';
        $plan          = isset($request['plan']) ? absint($request['plan']) : 0;
        $canal         = isset($request['canal']) ? sanitize_text_field($request['canal']) : '';
        $concesionario = isset($request['concesionario']) ? absint($request['concesionario']) : 0;

        // ----- CACHING -----
        // Elimina search del cache_key porque si no el mismo usuario puede buscar cosas distintas y obtiene el cache anterior
        $cache_key = 'go_glist_' . $current_user . "_p{$page}_pp{$per_page}";
        if ($search) {
            $cache_key .= '_s_' . md5($search);
        }
        if ($estado) {
            $cache_key .= '_e_' . md5($estado);
        }
        if ($plan) {
            $cache_key .= '_pl_' . $plan;
        }
        if ($canal) {
            $cache_key .= '_c_' . md5($canal);
        }
        if ($concesionario) {
            $cache_key .= '_v_' . $concesionario;
        }
        $cache = get_transient($cache_key);
        if ($cache !== false) {
            return $cache;
        }

        // ----- QUERY -----
        $args = [
            'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        // Permisos: restringe por profesional/comercial salvo admins
        $meta_query = [];
        if (!current_user_can('manage_options')) {
            $user_profesional_ids = [$current_user];
            $users_asignados = get_users([
                'role'    => 'go_profesional',
                'fields'  => 'ID',
                'meta_query' => [
                    [
                        'key'     => 'ajustes_usuarios_comercial_asignado',
                        'value'   => '"' . $current_user . '"',
                        'compare' => 'LIKE',
                    ]
                ]
            ]);
            if ($users_asignados) {
                $user_profesional_ids = array_unique(array_merge($user_profesional_ids, $users_asignados));
            }
            $meta_query[] = [
                'key'     => 'garantia_contratada_concesionario_empresa_profesional',
                'value'   => $user_profesional_ids,
                'compare' => 'IN',
            ];
        }

        // ---- FILTROS ----
        if ($estado) {
            $meta_query[] = [
                'key'   => 'estado_garantia_estado_contratacion',
                'value' => $estado,
            ];
        }
        if ($plan) {
            $meta_query[] = [
                'key'   => 'garantia_contratada_garantia',
                'value' => $plan,
            ];
        }
        if ($canal) {
            $meta_query[] = [
                'key'     => 'garantia_contratada_canal_venta',
                'value'   => $canal,
                'compare' => 'LIKE',
            ];
        }
        if ($concesionario) {
            $meta_query[] = [
                'key'   => 'garantia_contratada_concesionario_empresa_profesional',
                'value' => $concesionario,
            ];
        }

        // ---- SEARCH ----
        if ($search) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => 'datos_vehiculo_matricula',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'datos_vehiculo_marca',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'datos_vehiculo_modelo',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ];
        }

        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
            } else {
                $args['meta_query'] = $meta_query;
            }
        }

        $q = new WP_Query($args);

        $data = [];
        foreach ($q->posts as $post) {
            $post_id = $post->ID;
            // Usamos get_post_meta, NUNCA get_field aquí
            $mat    = get_post_meta($post_id, 'datos_vehiculo_matricula', true);
            $marca  = get_post_meta($post_id, 'datos_vehiculo_marca', true);
            $modelo = get_post_meta($post_id, 'datos_vehiculo_modelo', true);
            $marca_modelo = trim($marca . ' ' . $modelo);
            $desde  = get_post_meta($post_id, 'estado_garantia_inicio', true);
            $hasta  = get_post_meta($post_id, 'estado_garantia_finalizacion', true);
            $plan_id = get_post_meta($post_id, 'garantia_contratada_garantia', true);
            $plan   = $plan_id ? get_the_title($plan_id) : '';
            $precio = get_post_meta($post_id, 'garantia_contratada_precio', true);
            $estado = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);

            $vendor_id = get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
            $user      = $vendor_id ? get_user_by('id', $vendor_id) : false;
            $vendor_name = $user ? $user->display_name : '';

            // Canal de venta puede ser array (de ACF select) o string
            $canal_venta_raw = get_post_meta($post_id, 'garantia_contratada_canal_venta', true);
            $canal_venta_value = is_array($canal_venta_raw) && isset($canal_venta_raw['value']) ? $canal_venta_raw['value'] : (is_string($canal_venta_raw) ? $canal_venta_raw : '');
            $canal_venta_label = is_array($canal_venta_raw) && isset($canal_venta_raw['label']) ? $canal_venta_raw['label'] : (is_string($canal_venta_raw) ? ucfirst($canal_venta_raw) : '');

            $data[] = [
                'id'         => $post_id,
                'mat'        => $mat,
                'marca'      => $marca_modelo,
                'desde'      => $desde,
                'hasta'      => $hasta,
                'plan'       => $plan,
                'precio'     => $precio,
                'estado'     => $estado,
                'vendedor'   => $vendor_name,
                'canal_venta' => [
                    'value' => $canal_venta_value,
                    'label' => $canal_venta_label
                ],
            ];
        }

        $total_posts = (int) $q->found_posts;
        $total_pages = (int) ceil($total_posts / $per_page);

        $response = new WP_REST_Response([
            'data'         => $data,
            'total'        => $total_posts,
            'per_page'     => $per_page,
            'current_page' => $page,
        ]);
        $response->header('X-WP-Total',      $total_posts);
        $response->header('X-WP-TotalPages', $total_pages);

        set_transient($cache_key, $response, 60); // 60 segundos de cache

        return $response;
    }

    /**
     * Detalle de una garantía — DEVOLVIENDO TODOS LOS DATOS PLANOS
     */
    public static function get_item($request)
    {
        $id = (int) $request['id'];
        // Vehículo
        $matricula = get_post_meta($id, 'datos_vehiculo_matricula', true);
        $marca = get_post_meta($id, 'datos_vehiculo_marca', true);
        $modelo = get_post_meta($id, 'datos_vehiculo_modelo', true);
        $marca_modelo = trim($marca . ' ' . $modelo);
        $tipo = get_post_meta($id, 'datos_vehiculo_tipo_vehiculo', true);
        $kilometros = get_post_meta($id, 'datos_vehiculo_kilometros', true);
        $primera_matriculacion = get_post_meta($id, 'datos_vehiculo_primera_matriculacion', true);
        $bastidor = get_post_meta($id, 'datos_vehiculo_numero_bastidor', true);
        $precio_venta = get_post_meta($id, 'datos_vehiculo_precio_venta', true);
        $combustible = get_post_meta($id, 'datos_vehiculo_combustible', true);
        $cambio = get_post_meta($id, 'datos_vehiculo_cambio', true);
        $potencia = get_post_meta($id, 'datos_vehiculo_potencia', true);
        $cilindrada = get_post_meta($id, 'datos_vehiculo_Cilindrada', true);

        // Plan
        $plan_id = get_post_meta($id, 'garantia_contratada_garantia', true);
        $plan    = $plan_id ? get_the_title($plan_id) : '';
        $precio  = get_post_meta($id, 'garantia_contratada_precio', true);
        $desde   = get_post_meta($id, 'estado_garantia_inicio', true);
        $hasta   = get_post_meta($id, 'estado_garantia_finalizacion', true);
        $estado  = get_post_meta($id, 'estado_garantia_estado_contratacion', true);

        // Vendedor/concesionario
        $vendor_id = get_post_meta($id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $user      = $vendor_id ? get_user_by('id', $vendor_id) : false;
        $concesionario = $user ? $user->display_name : '';

        $canal_venta_raw = get_post_meta($id, 'garantia_contratada_canal_venta', true);
        $canal_venta = is_array($canal_venta_raw) && isset($canal_venta_raw['label']) ? $canal_venta_raw['label'] : (is_string($canal_venta_raw) ? ucfirst($canal_venta_raw) : '');

        // Documentación (rellena las URL si las tienes guardadas)
        $contrato_url = get_post_meta($id, 'docs_url_contrato', true) ?: '#';
        $condicionado_url = get_post_meta($id, 'docs_url_condicionado', true) ?: '#';
        $cobertura_url = get_post_meta($id, 'docs_url_cobertura', true) ?: '#';
        $factura_url = get_post_meta($id, 'docs_url_factura', true) ?: '#';

        // Comprador
        $nombre_comprador = get_post_meta($id, 'datos_cliente_nombre_y_apellidos', true);
        $dni_comprador = get_post_meta($id, 'datos_cliente_dni', true);
        $telefono_comprador = get_post_meta($id, 'datos_cliente_telefono', true);
        $email_comprador = get_post_meta($id, 'datos_cliente_email', true);
        $direccion_comprador = get_post_meta($id, 'datos_cliente_direccion', true);

        $data = [
            'id' => $id,
            'matricula' => $matricula ?: '-',
            'marca_modelo' => $marca_modelo ?: '-',
            'tipo' => $tipo ?: '-',
            'kilometros' => $kilometros ?: '-',
            'primera_matriculacion' => $primera_matriculacion ?: '-',
            'bastidor' => $bastidor ?: '-',
            'precio_venta' => $precio_venta ?: '-',
            'combustible' => $combustible ?: '-',
            'cambio' => $cambio ?: '-',
            'potencia' => $potencia ?: '-',
            'cilindrada' => $cilindrada ?: '-',
            'plan' => $plan ?: '-',
            'precio' => $precio ?: '-',
            'desde' => $desde ?: '-',
            'hasta' => $hasta ?: '-',
            'estado' => $estado ?: '-',
            'concesionario' => $concesionario ?: '-',
            'canal_venta' => $canal_venta ?: '-',
            'contrato_url' => $contrato_url,
            'condicionado_url' => $condicionado_url,
            'cobertura_url' => $cobertura_url,
            'factura_url' => $factura_url,
            'nombre_comprador' => $nombre_comprador ?: '-',
            'dni_comprador' => $dni_comprador ?: '-',
            'telefono_comprador' => $telefono_comprador ?: '-',
            'email_comprador' => $email_comprador ?: '-',
            'direccion_comprador' => $direccion_comprador ?: '-',
        ];

        return rest_ensure_response($data);
    }

    public static function get_filters($request)
    {
        $current_user = get_current_user_id();

        $cache_key = 'go_gfilters_' . $current_user;
        $cache = get_transient($cache_key);
        if ($cache !== false) {
            return $cache;
        }

        $meta_query = [];
        if (!current_user_can('manage_options')) {
            $user_profesional_ids = [$current_user];
            $users_asignados = get_users([
                'role'    => 'go_profesional',
                'fields'  => 'ID',
                'meta_query' => [
                    [
                        'key'     => 'ajustes_usuarios_comercial_asignado',
                        'value'   => '"' . $current_user . '"',
                        'compare' => 'LIKE',
                    ]
                ]
            ]);
            if ($users_asignados) {
                $user_profesional_ids = array_unique(array_merge($user_profesional_ids, $users_asignados));
            }
            $meta_query[] = [
                'key'     => 'garantia_contratada_concesionario_empresa_profesional',
                'value'   => $user_profesional_ids,
                'compare' => 'IN',
            ];
        }

        $args = [
            'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ];
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $args['meta_query'] = array_merge(['relation' => 'AND'], $meta_query);
            } else {
                $args['meta_query'] = $meta_query;
            }
        }

        $q = new WP_Query($args);

        $estados = [];
        $plan_ids = [];
        foreach ($q->posts as $post_id) {
            $e = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
            if ($e) {
                $estados[] = $e;
            }
            $pid = get_post_meta($post_id, 'garantia_contratada_garantia', true);
            if ($pid) {
                $plan_ids[] = $pid;
            }
        }

        $estados = array_values(array_unique(array_filter($estados)));
        sort($estados);

        $planes = [];
        $plan_ids = array_unique(array_filter($plan_ids));
        foreach ($plan_ids as $pid) {
            $title = get_the_title($pid);
            if ($title) {
                $planes[] = [
                    'id'    => (int) $pid,
                    'title' => $title,
                ];
            }
        }

        $users = get_users([
            'role'   => 'go_profesional',
            'fields' => ['ID', 'display_name'],
        ]);
        $concesionarios = [];
        foreach ($users as $u) {
            $concesionarios[] = [
                'id'   => $u->ID,
                'name' => $u->display_name,
            ];
        }

        $response = new WP_REST_Response([
            'estados'        => $estados,
            'planes'         => $planes,
            'concesionarios' => $concesionarios,
        ]);

        set_transient($cache_key, $response, 60);

        return $response;
    }

    /**
     * Limpia todos los transients del listado al guardar una garantía.
     */
    public static function clear_list_transients($post_id, $post, $update)
    {
        global $wpdb;
        $patterns = ['_transient_go_glist_%', '_transient_go_gfilters_%'];
        foreach ($patterns as $pattern) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                $pattern
            ));
        }
    }
    public static function clear_list_transients_on_delete($post_id)
    {
        $post_type = get_post_type($post_id);
        if ($post_type === \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
            self::clear_list_transients($post_id, null, false);
        }
    }
}

// Sustituye estos bloques SOLO si tienes Redis/object-cache activado.

// 1. Para guardar y obtener caché:
// $cache_key = 'go_glist_' . $current_user . "_p{$page}_pp{$per_page}";
// $cache = wp_cache_get($cache_key, 'garantias360vo');
// if ($cache !== false) {
//     return $cache;
// }
// ... (hacer la query) ...
// wp_cache_set($cache_key, $response, 'garantias360vo', 60); // TTL en segundos

// 2. Para borrar caché (cuando guardas/borras una garantía):
// Puedes hacer esto solo si sabes el key, si no, tendrás que limpiar el grupo desde admin Redis
// Ejemplo:
// wp_cache_delete($cache_key, 'garantias360vo');
// Si quieres borrar TODO el grupo, puedes hacerlo desde el panel de administración de Redis/Memcached,
//   pero WP no tiene función para “flush group” directamente.
