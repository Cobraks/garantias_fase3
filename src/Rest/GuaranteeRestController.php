<?php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Server;
use WP_Query;
use WP_REST_Response;
use WP_Error;

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
            '/' . self::BASE . '/check-plate',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'check_plate'],
                    'permission_callback' => [__CLASS__, 'can_edit'],
                    'args'                => [
                        'matricula' => [
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'exclude'   => [
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
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
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)/mark-paid',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'mark_paid'],
                    'permission_callback' => function () {
                        if (current_user_can('manage_options')) {
                            return true;
                        }
                        $user = wp_get_current_user();
                        return in_array('go_garantias', (array) $user->roles, true)
                            || in_array('go_comercial', (array) $user->roles, true);
                    },
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

        $current_user = wp_get_current_user();
        $uid = $current_user->ID;

        // Admin o roles internos pueden ver todo
        if (
            current_user_can('manage_options') ||
            in_array('go_garantias', (array) $current_user->roles, true) ||
            in_array('go_comercial', (array) $current_user->roles, true)
        ) {
            return true;
        }

        $post_id = (int) $request['id'];

        // Obtener el usuario propietario profesional
        $profesional = get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $profesional_id = is_array($profesional) && isset($profesional['ID']) ? $profesional['ID'] : $profesional;

        if ((int)$uid === (int)$profesional_id) {
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
            if (in_array((int)$uid, $comercial_ids, true)) {
                return true;
            }
        }

        return false;
    }

    public static function can_edit($request)
    {
        return is_user_logged_in();
    }

    /**
     * Normalize a price string to use dot as decimal separator.
     */
    private static function normalize_decimal($value)
    {
        if (!is_numeric($value)) {
            $value     = preg_replace('/[^0-9.,]/', '', (string) $value);
            $lastComma = strrpos($value, ',');
            $lastDot   = strrpos($value, '.');
            $sep       = $lastComma > $lastDot ? ',' : '.';
            $parts     = explode($sep, $value);
            $intPart   = preg_replace('/[^0-9]/', '', $parts[0]);
            $decPart   = isset($parts[1]) ? preg_replace('/[^0-9]/', '', $parts[1]) : '';
            $value     = $decPart !== '' ? $intPart . '.' . $decPart : $intPart;
        }

        if ($value === '' || $value === null) {
            return '';
        }

        $float = (float) $value;
        $str   = (string) $float;
        return strpos($str, '.') !== false ? rtrim(rtrim($str, '0'), '.') : $str;
    }

    public static function autosave($request)
    {
        $post_id = isset($request['id']) ? absint($request['id']) : 0;
        $uuid    = isset($request['uuid']) ? sanitize_text_field($request['uuid']) : '';
        $data    = isset($request['data']) && is_array($request['data']) ? $request['data'] : [];

        error_log('[AUTOSAVE] Incoming: ' . wp_json_encode(['id' => $post_id, 'uuid' => $uuid, 'data' => $data]));

        $current_user = wp_get_current_user();
        if (in_array('go_profesional', (array) $current_user->roles, true)) {
            if (!isset($data['garantia_contratada']) || !is_array($data['garantia_contratada'])) {
                $data['garantia_contratada'] = [];
            }
            if (!isset($data['garantia_contratada']['canal_venta'])) {
                $data['garantia_contratada']['canal_venta'] = 'profesional';
            }
            if (!isset($data['garantia_contratada']['concesionario_empresa_profesional'])) {
                $data['garantia_contratada']['concesionario_empresa_profesional'] = $current_user->ID;
            }
        }

        $matricula = '';
        if (isset($data['matricula'])) {
            $matricula = sanitize_text_field($data['matricula']);
        } elseif (isset($data['datos_vehiculo']['matricula'])) {
            $matricula = sanitize_text_field($data['datos_vehiculo']['matricula']);
        }

        if ($post_id > 0) {
            $post = get_post($post_id);
            $stored_uuid = get_post_meta($post_id, 'estado_garantia_uuid', true);
            if (!$post || $post->post_type !== \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE || !$uuid || $uuid !== $stored_uuid) {
                $post_id = 0;
            }
        } elseif ($uuid) {
            $found = get_posts([
                'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
                'post_status'    => ['draft', 'publish', 'pending', 'future'],
                'meta_key'       => 'estado_garantia_uuid',
                'meta_value'     => $uuid,
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ]);
            if (!empty($found)) {
                $post_id = (int) $found[0];
            }
        }

        if ($matricula) {
            $args = [
                'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
                'post_status'    => ['draft', 'publish', 'pending', 'future'],
                'meta_key'       => 'datos_vehiculo_matricula',
                'meta_value'     => $matricula,
                'fields'         => 'ids',
                'posts_per_page' => 1,
            ];
            if ($post_id) {
                $args['post__not_in'] = [$post_id];
            }
            $existing = get_posts($args);
            if (!empty($existing)) {
                return new WP_Error('duplicate_plate', __('Ya existe una garantía para este vehículo', 'garantias-online-360vo'), ['status' => 409]);
            }
        }

        if ($post_id === 0) {
            $title   = $matricula ? sprintf(__('Garantía %s', 'garantias-online-360vo'), $matricula) : __('Borrador de garantía', 'garantias-online-360vo');
            $post_id = wp_insert_post([
                'post_type'   => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
                'post_status' => 'draft',
                'post_title'  => $title,
                'post_author' => get_current_user_id(),
            ]);
            $uuid = wp_generate_uuid4();
            update_post_meta($post_id, 'estado_garantia_uuid', $uuid);
            update_post_meta($post_id, 'estado_garantia_estado_contratacion', 'sin_finalizar');
            error_log('[AUTOSAVE] Created draft guarantee ID ' . $post_id);
        } elseif ($matricula) {
            wp_update_post([
                'ID'         => $post_id,
                'post_title' => sprintf(__('Garantía %s', 'garantias-online-360vo'), $matricula),
            ]);
            error_log('[AUTOSAVE] Updated title for ID ' . $post_id);
            if (!$uuid) {
                $uuid = get_post_meta($post_id, 'estado_garantia_uuid', true);
            }
        }

        if (isset($data['datos_vehiculo']) && is_array($data['datos_vehiculo'])) {
            $vehiculo       = [];
            $numeric_fields = ['kilometros', 'potencia', 'potencia_kw', 'cilindrada'];
            $decimal_fields = ['precio_venta'];
            foreach ($data['datos_vehiculo'] as $k => $v) {
                if (in_array($k, $numeric_fields, true)) {
                    $v            = str_replace(['.', ','], '', $v);
                    $vehiculo[$k] = is_numeric($v) ? $v : '';
                } elseif (in_array($k, $decimal_fields, true)) {
                    $v            = self::normalize_decimal($v);
                    $vehiculo[$k] = is_numeric($v) ? $v : '';
                } else {
                    $vehiculo[$k] = sanitize_text_field($v);
                }
            }
            if (isset($vehiculo['fecha_primera_matriculacion'])) {
                $vehiculo['primera_matriculacion'] = $vehiculo['fecha_primera_matriculacion'];
                unset($vehiculo['fecha_primera_matriculacion']);
            }
            if (isset($vehiculo['tipo_vehiculo'])) {
                $term = get_term_by('slug', $vehiculo['tipo_vehiculo'], 'tipo_vehiculo');
                if ($term) {
                    $vehiculo['tipo_vehiculo'] = (int) $term->term_id;
                }
            }

            if (
                isset($vehiculo['combustible']) &&
                $vehiculo['combustible'] === 'electrico'
            ) {
                $kw = null;
                if (isset($vehiculo['potencia_kw']) && $vehiculo['potencia_kw'] !== '') {
                    $kw = (float) $vehiculo['potencia_kw'];
                } elseif (isset($vehiculo['potencia']) && $vehiculo['potencia'] !== '') {
                    $kw = (float) $vehiculo['potencia'];
                    $vehiculo['potencia_kw'] = (string) $kw;
                }
                if ($kw !== null) {
                    $vehiculo['potencia'] = (string) round($kw * 1.3596);
                }
            }
            if (function_exists('update_field')) {
                update_field('datos_vehiculo', $vehiculo, $post_id);
            } else {
                foreach ($vehiculo as $k => $v) {
                    update_post_meta($post_id, 'datos_vehiculo_' . $k, $v);
                }
            }
            error_log('[AUTOSAVE] Saved datos_vehiculo for ID ' . $post_id . ': ' . wp_json_encode($vehiculo));
            unset($data['datos_vehiculo']);
        }

        if (isset($data['datos_cliente']) && is_array($data['datos_cliente'])) {
            $cliente = [];
            foreach ($data['datos_cliente'] as $k => $v) {
                switch ($k) {
                    case 'email':
                        $cliente[$k] = sanitize_email($v);
                        break;
                    case 'codigo_postal':
                        $cliente[$k] = sanitize_text_field($v);
                        break;
                    default:
                        $cliente[$k] = sanitize_text_field($v);
                        break;
                }
            }
            if (function_exists('update_field')) {
                update_field('datos_cliente', $cliente, $post_id);
            } else {
                foreach ($cliente as $k => $v) {
                    update_post_meta($post_id, 'datos_cliente_' . $k, $v);
                }
            }
            error_log('[AUTOSAVE] Saved datos_cliente for ID ' . $post_id . ': ' . wp_json_encode($cliente));
            unset($data['datos_cliente']);
        }

        $meses_contratados = 0;
        $gc = [];
        $is_publishing = isset($data['post_status']) && sanitize_text_field($data['post_status']) === 'publish';
        if (isset($data['garantia_contratada']) && is_array($data['garantia_contratada'])) {
            $gc   = [];
            foreach ($data['garantia_contratada'] as $k => $v) {
                switch ($k) {
                    case 'garantia':
                    case 'tipo_garantia':
                    case 'nivel_garantia':
                    case 'meses_contratados':
                    case 'concesionario_empresa_profesional':
                        $gc[$k] = absint($v);
                        if ($k === 'meses_contratados') {
                            $meses_contratados = (int) $gc[$k];
                        }
                        break;
                    case 'precio':
                        $v      = self::normalize_decimal($v);
                        $gc[$k] = is_numeric($v) ? $v : '';
                        break;
                    case 'metodo_pago':
                    case 'canal_venta':
                        $gc[$k] = sanitize_text_field($v);
                        break;
                    case 'descuentos_y_recargos':
                        if (is_array($v)) {
                            $dr = [];
                            if (isset($v['precio_base'])) {
                                $base               = self::normalize_decimal($v['precio_base']);
                                $dr['precio_base'] = is_numeric($base) ? $base : '';
                            }
                            if (!empty($v['listado_descuentos_recargos']) && is_array($v['listado_descuentos_recargos'])) {
                                $list = [];
                                foreach ($v['listado_descuentos_recargos'] as $row) {
                                    $tipo = sanitize_text_field($row['tipo'] ?? '');
                                    $por  = self::normalize_decimal($row['porcentaje'] ?? '');
                                    $raz  = sanitize_text_field($row['razon'] ?? '');
                                    $list[] = [
                                        'tipo'       => $tipo,
                                        'porcentaje' => is_numeric($por) ? $por : '',
                                        'razon'      => $raz,
                                    ];
                                }
                                if ($list) {
                                    $dr['listado_descuentos_recargos'] = array_values($list);
                                }
                            }
                            $gc['descuentos_y_recargos'] = $dr;
                        }
                        break;
                    default:
                        $gc[$k] = sanitize_text_field($v);
                        break;
                }
            }
            if (isset($gc['garantia'])) {
                $tipo_terms = wp_get_post_terms($gc['garantia'], 'tipo_garantia', ['fields' => 'ids']);
                if (!is_wp_error($tipo_terms) && !empty($tipo_terms)) {
                    $gc['tipo_garantia'] = (int) $tipo_terms[0];
                }
                $nivel_terms = wp_get_post_terms($gc['garantia'], 'nivel_garantia', ['fields' => 'ids']);
                if (!is_wp_error($nivel_terms) && !empty($nivel_terms)) {
                    $gc['nivel_garantia'] = (int) $nivel_terms[0];
                }
            }
            if (isset($gc['tipo_garantia'])) {
                wp_set_post_terms($post_id, [(int) $gc['tipo_garantia']], 'tipo_garantia');
            }
            if (isset($gc['nivel_garantia'])) {
                wp_set_post_terms($post_id, [(int) $gc['nivel_garantia']], 'nivel_garantia');
            }
            foreach ($gc as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $subk => $subv) {
                        update_post_meta($post_id, 'garantia_contratada_' . $k . '_' . $subk, $subv);
                    }
                } else {
                    update_post_meta($post_id, 'garantia_contratada_' . $k, $v);
                }
            }
            if (($gc['metodo_pago'] ?? '') === 'domiciliacion_bancaria') {
                update_post_meta($post_id, 'garantia_contratada_estado_cobro_cobro_realizado', 0);
                update_post_meta($post_id, 'garantia_contratada_estado_cobro_fecha_cobro', '');
            }
            error_log('[AUTOSAVE] Saved garantia_contratada for ID ' . $post_id . ': ' . wp_json_encode($gc));
            unset($data['garantia_contratada']);
        }

        if (isset($data['estado_garantia']) && is_array($data['estado_garantia'])) {
            $estado = [];
            if (isset($data['estado_garantia']['inicio'])) {
                $inicio        = sanitize_text_field($data['estado_garantia']['inicio']);
                $estado['inicio'] = $inicio;
                if ($inicio && $meses_contratados > 0) {
                    $end = date_create($inicio);
                    if ($end) {
                        $end->modify("+{$meses_contratados} months");
                        $end->modify('-1 day');
                        $estado['finalizacion'] = $end->format('Y-m-d');
                    }
                }
            }
            if (isset($data['estado_garantia']['finalizacion']) && empty($estado['finalizacion'])) {
                $estado['finalizacion'] = sanitize_text_field($data['estado_garantia']['finalizacion']);
            }
            if ($is_publishing && ($gc['metodo_pago'] ?? '') === 'domiciliacion_bancaria') {
                $estado['estado_contratacion'] = 'activada';
            } elseif (isset($data['estado_garantia']['estado_contratacion'])) {
                $ec = sanitize_text_field($data['estado_garantia']['estado_contratacion']);
                $valid = ['pendiente_pago', 'sin_finalizar', 'activada', 'expirada', 'expira_pronto'];
                if (in_array($ec, $valid, true)) {
                    $estado['estado_contratacion'] = $ec;
                }
            }
            if ($estado) {
                foreach ($estado as $k => $v) {
                    update_post_meta($post_id, 'estado_garantia_' . $k, $v);
                }
                error_log('[AUTOSAVE] Saved estado_garantia for ID ' . $post_id . ': ' . wp_json_encode($estado));
            }
            unset($data['estado_garantia']);
        }

        if (isset($data['post_status'])) {
            $ps = sanitize_text_field($data['post_status']);
            if ($ps === 'publish') {
                wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            }
            unset($data['post_status']);
        }

        foreach ($data as $key => $value) {
            $meta_key = sanitize_key($key);
            $meta_val = is_scalar($value) ? sanitize_text_field($value) : wp_json_encode($value);
            update_post_meta($post_id, $meta_key, $meta_val);
        }

        error_log('[AUTOSAVE] Completed for ID ' . $post_id);

        // Clear cached list and detail responses so subsequent fetches reflect the update.
        self::clear_list_transients($post_id, null, true);

        return new WP_REST_Response(['id' => $post_id, 'uuid' => $uuid]);
    }

    public static function check_plate($request)
    {
        $matricula = isset($request['matricula']) ? sanitize_text_field($request['matricula']) : '';
        if ($matricula === '') {
            return new WP_Error('invalid_plate', __('Matrícula requerida', 'garantias-online-360vo'), ['status' => 400]);
        }

        $exclude = isset($request['exclude']) ? sanitize_text_field($request['exclude']) : '';
        $post_id = 0;
        if ($exclude) {
            if (ctype_digit((string) $exclude)) {
                $post_id = (int) $exclude;
            } else {
                $found = get_posts([
                    'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
                    'post_status'    => ['draft', 'publish', 'pending', 'future'],
                    'meta_key'       => 'estado_garantia_uuid',
                    'meta_value'     => $exclude,
                    'fields'         => 'ids',
                    'posts_per_page' => 1,
                ]);
                if (!empty($found)) {
                    $post_id = (int) $found[0];
                }
            }
        }

        $args = [
            'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
            'post_status'    => ['draft', 'publish', 'pending', 'future'],
            'meta_key'       => 'datos_vehiculo_matricula',
            'meta_value'     => $matricula,
            'fields'         => 'ids',
            'posts_per_page' => 1,
        ];
        if ($post_id) {
            $args['post__not_in'] = [$post_id];
        }
        $existing = get_posts($args);
        if (!empty($existing)) {
            return new WP_Error('duplicate_plate', __('Ya existe una garantía para este vehículo', 'garantias-online-360vo'), ['status' => 409]);
        }

        return rest_ensure_response(['exists' => false]);
    }

    /**
     * Collect all detail fields for a guarantee post.
     */
    private static function get_detail_data($id)
    {
        $id = (int) $id;

        $matricula = get_post_meta($id, 'datos_vehiculo_matricula', true);
        $marca = get_post_meta($id, 'datos_vehiculo_marca', true);
        $modelo = get_post_meta($id, 'datos_vehiculo_modelo', true);
        $marca_modelo = trim($marca . ' ' . $modelo);
        $tipo_id = get_post_meta($id, 'datos_vehiculo_tipo_vehiculo', true);
        $tipo_term = $tipo_id ? get_term($tipo_id, 'tipo_vehiculo') : null;
        $tipo = ($tipo_term && !is_wp_error($tipo_term)) ? $tipo_term->name : $tipo_id;
        $tipo_slug = ($tipo_term && !is_wp_error($tipo_term)) ? $tipo_term->slug : '';
        $kilometros = get_post_meta($id, 'datos_vehiculo_kilometros', true);
        $primera_matriculacion = get_post_meta($id, 'datos_vehiculo_primera_matriculacion', true);
        $bastidor = get_post_meta($id, 'datos_vehiculo_numero_bastidor', true);
        $precio_venta = get_post_meta($id, 'datos_vehiculo_precio_venta', true);

        $combustible_raw = function_exists('get_field') ? get_field('datos_vehiculo_combustible', $id) : get_post_meta($id, 'datos_vehiculo_combustible', true);
        $combustible_label = is_array($combustible_raw)
            ? ($combustible_raw['label'] ?? $combustible_raw['value'] ?? '')
            : $combustible_raw;
        $combustible_value = is_array($combustible_raw)
            ? ($combustible_raw['value'] ?? $combustible_raw['label'] ?? '')
            : $combustible_raw;

        $cambio_raw = function_exists('get_field') ? get_field('datos_vehiculo_cambio', $id) : get_post_meta($id, 'datos_vehiculo_cambio', true);
        $cambio_label = is_array($cambio_raw)
            ? ($cambio_raw['label'] ?? $cambio_raw['value'] ?? '')
            : $cambio_raw;
        $cambio_value = is_array($cambio_raw)
            ? ($cambio_raw['value'] ?? $cambio_raw['label'] ?? '')
            : $cambio_raw;

        $traccion = get_post_meta($id, 'datos_vehiculo_traccion', true);
        $traccion_camion = get_post_meta($id, 'datos_vehiculo_traccion_camion', true);
        $potencia = get_post_meta($id, 'datos_vehiculo_potencia', true);
        $potencia_kw = get_post_meta($id, 'datos_vehiculo_potencia_kw', true);
        $cilindrada = get_post_meta($id, 'datos_vehiculo_cilindrada', true);

        $plan_id = get_post_meta($id, 'garantia_contratada_garantia', true);
        if ($plan_id) {
            $custom_plan = function_exists('get_field')
                ? get_field('detalles_modalidad_nombre_mostrar', $plan_id)
                : '';
            $plan = $custom_plan ?: get_the_title($plan_id);
        } else {
            $plan = '';
        }
        $precio  = get_post_meta($id, 'garantia_contratada_precio', true);
        $metodo_pago = get_post_meta($id, 'garantia_contratada_metodo_pago', true);
        $cobro_realizado = (bool) get_post_meta($id, 'garantia_contratada_estado_cobro_cobro_realizado', true);
        $fecha_cobro_raw = get_post_meta($id, 'garantia_contratada_estado_cobro_fecha_cobro', true);
        $fecha_cobro = $fecha_cobro_raw ? date_i18n('d/m/Y', strtotime($fecha_cobro_raw)) : '';
        $desde   = get_post_meta($id, 'estado_garantia_inicio', true);
        $hasta   = get_post_meta($id, 'estado_garantia_finalizacion', true);
        $estado  = get_post_meta($id, 'estado_garantia_estado_contratacion', true);
        $estado_labels = [
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'sin_finalizar'  => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
        ];
        $estado_label = $estado_labels[$estado] ?? $estado;

        $vendor_id = get_post_meta($id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $user      = $vendor_id ? get_user_by('id', $vendor_id) : false;
        $concesionario = $user ? $user->display_name : '';
        $iban = '';
        if ($vendor_id) {
            $viewer = wp_get_current_user();
            if (
                current_user_can('manage_options') ||
                in_array('go_garantias', (array) $viewer->roles, true) ||
                in_array('go_comercial', (array) $viewer->roles, true)
            ) {
                $iban = get_user_meta($vendor_id, 'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta', true);
            }
        }

        $canal_venta_raw = get_post_meta($id, 'garantia_contratada_canal_venta', true);
        $canal_venta_value = is_array($canal_venta_raw) && isset($canal_venta_raw['value'])
            ? $canal_venta_raw['value']
            : (is_string($canal_venta_raw) ? $canal_venta_raw : '');
        if (is_array($canal_venta_raw) && isset($canal_venta_raw['label'])) {
            $canal_venta = $canal_venta_raw['label'];
        } else {
            $lookup = preg_replace('/^go_/i', '', $canal_venta_value);
            $canal_choices = [
                'profesional' => __('Profesional', 'garantias-online-360vo'),
                'particular'  => __('Particular', 'garantias-online-360vo'),
                'gestoria'    => __('Gestoría', 'garantias-online-360vo'),
            ];
            $canal_venta = $canal_choices[$lookup] ?? ucfirst($lookup);
        }

        $telefono_vendedor = $vendor_id
            ? get_user_meta($vendor_id, 'datos_usuario_telefono', true)
            : '';
        $email_vendedor = $vendor_id
            ? get_user_meta($vendor_id, 'datos_usuario_correo_electronico', true)
            : '';
        $avatar_vendedor = $vendor_id ? get_avatar_url($vendor_id, ['size' => 96]) : '';
        $vendedor_url   = $vendor_id ? get_edit_user_link($vendor_id) : '#';

        $contrato_url = get_post_meta($id, 'docs_url_contrato', true) ?: '#';
        $condicionado_url = get_post_meta($id, 'docs_url_condicionado', true) ?: '#';
        $cobertura_url = get_post_meta($id, 'docs_url_cobertura', true) ?: '#';
        $factura_url = get_post_meta($id, 'docs_url_factura', true) ?: '#';

        $nombre_comprador = get_post_meta($id, 'datos_cliente_nombre_y_apellidos', true);
        $dni_comprador = get_post_meta($id, 'datos_cliente_dni', true);
        $telefono_comprador = get_post_meta($id, 'datos_cliente_telefono', true);
        $email_comprador = get_post_meta($id, 'datos_cliente_email', true);
        $direccion_comprador = get_post_meta($id, 'datos_cliente_direccion', true);
        $localidad_comprador = get_post_meta($id, 'datos_cliente_localidad', true);
        $provincia_comprador = get_post_meta($id, 'datos_cliente_provincia', true);
        $codigo_postal_comprador = get_post_meta($id, 'datos_cliente_codigo_postal', true);

        $uuid = get_post_meta($id, 'estado_garantia_uuid', true);

        return [
            'id' => $id,
            'uuid' => $uuid,
            'matricula' => $matricula ?: '-',
            'marca_modelo' => $marca_modelo ?: '-',
            'marca' => $marca ?: '',
            'modelo' => $modelo ?: '',
            'tipo' => $tipo ?: '-',
            'tipo_value' => $tipo_slug,
            'kilometros' => $kilometros ?: '-',
            'primera_matriculacion' => $primera_matriculacion ?: '-',
            'bastidor' => $bastidor ?: '-',
            'precio_venta' => $precio_venta ?: '-',
            'combustible' => $combustible_label ?: '-',
            'combustible_value' => $combustible_value ?: '',
            'cambio' => $cambio_label ?: '-',
            'cambio_value' => $cambio_value ?: '',
            'traccion' => $traccion ?: '',
            'traccion_camion' => $traccion_camion ?: '',
            'potencia' => $potencia ?: '-',
            'potencia_kw' => $potencia_kw ?: '',
            'cilindrada' => $cilindrada ?: '-',
            'plan' => $plan,
            'precio' => $precio,
            'metodo_pago' => $metodo_pago ?: '',
            'cobro_realizado' => $cobro_realizado,
            'fecha_cobro' => $fecha_cobro,
            'iban' => $iban,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => [
                'value' => $estado,
                'label' => $estado_label,
            ],
            'concesionario' => $concesionario ?: '-',
            'canal_venta' => $canal_venta ?: '-',
            'canal_venta_value' => $canal_venta_value,
            'telefono_vendedor' => $telefono_vendedor ?: '',
            'email_vendedor' => $email_vendedor ?: '',
            'avatar_vendedor' => $avatar_vendedor ?: '',
            'vendedor_url' => $vendedor_url,
            'contrato_url' => $contrato_url,
            'condicionado_url' => $condicionado_url,
            'cobertura_url' => $cobertura_url,
            'factura_url' => $factura_url,
            'nombre_comprador' => $nombre_comprador ?: '-',
            'dni_comprador' => $dni_comprador ?: '-',
            'telefono_comprador' => $telefono_comprador ?: '-',
            'email_comprador' => $email_comprador ?: '-',
            'direccion_comprador' => $direccion_comprador ?: '-',
            'localidad_comprador' => $localidad_comprador ?: '-',
            'provincia_comprador' => $provincia_comprador ?: '-',
            'codigo_postal_comprador' => $codigo_postal_comprador ?: '-',
        ];
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
            'post_status'    => ['draft', 'publish', 'pending', 'future'],
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
            $detail = get_transient('go_gdetail_' . $post_id);
            if ($detail === false) {
                $detail = self::get_detail_data($post_id);
                set_transient('go_gdetail_' . $post_id, $detail, 300);
            }

            $data[] = [
                'id'         => $post_id,
                'mat'        => $detail['matricula'],
                'marca'      => $detail['marca_modelo'],
                'desde'      => $detail['desde'],
                'hasta'      => $detail['hasta'],
                'plan'       => $detail['plan'],
                'precio'     => $detail['precio'],
                'estado'     => $detail['estado'],
                'vendedor'   => $detail['concesionario'],
                'canal_venta' => [
                    'value' => $detail['canal_venta_value'] ?? '',
                    'label' => $detail['canal_venta'],
                ],
                'detail'     => $detail,
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

        set_transient($cache_key, $response, 300); // 5 minutos de cache

        return $response;
    }

    /**
     * Detalle de una garantía — DEVOLVIENDO TODOS LOS DATOS PLANOS
     */
    public static function get_item($request)
    {
        $id = (int) $request['id'];
        $cache_key = 'go_gdetail_' . $id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }
        $data = self::get_detail_data($id);

        set_transient($cache_key, $data, 300);

        return rest_ensure_response($data);
    }

    public static function mark_paid($request)
    {
        $id = (int) $request['id'];
        if (!$id) {
            return new WP_Error('invalid_id', __('ID inválido', 'garantias-online-360vo'), ['status' => 400]);
        }
        update_post_meta($id, 'garantia_contratada_estado_cobro_cobro_realizado', 1);
        $today = current_time('Y-m-d');
        update_post_meta($id, 'garantia_contratada_estado_cobro_fecha_cobro', $today);
        $vendor_id = (int) get_post_meta($id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $vendor    = $vendor_id ? get_userdata($vendor_id) : null;
        $plan_id   = get_post_meta($id, 'garantia_contratada_garantia', true);
        $plan      = $plan_id ? get_the_title($plan_id) : '';
        $price     = get_post_meta($id, 'garantia_contratada_precio', true);
        $concepto  = 'Garantía ' . get_post_meta($id, 'datos_vehiculo_matricula', true);
        $current   = wp_get_current_user();
        $detail    = wp_json_encode([
            'user'     => $current->display_name,
            'vendor'   => $vendor ? $vendor->display_name : '',
            'concepto' => $concepto,
            'fecha'    => current_time('mysql'),
            'plan'     => $plan,
            'precio'   => $price,
        ]);
        \GarantiasOnline360VO\GuaranteeLogger::log($current->ID, $id, 'cobro', $detail);
        self::clear_list_transients($id, null, true);
        return rest_ensure_response(['success' => true]);
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
        $estado_labels = [
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'sin_finalizar'  => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
        ];
        $estados = array_map(function ($e) use ($estado_labels) {
            return [
                'value' => $e,
                'label' => $estado_labels[$e] ?? $e,
            ];
        }, $estados);

        $planes = [];
        $plan_ids = array_unique(array_filter($plan_ids));
        foreach ($plan_ids as $pid) {
            $custom_title = function_exists('get_field')
                ? get_field('detalles_modalidad_nombre_mostrar', $pid)
                : '';
            $title = $custom_title ?: get_the_title($pid);
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

        set_transient($cache_key, $response, 300);

        return $response;
    }

    /**
     * Limpia todos los transients del listado al guardar una garantía.
     */
    public static function clear_list_transients($post_id, $post, $update)
    {
        global $wpdb;
        $patterns = ['_transient_go_glist_%', '_transient_go_gfilters_%', '_transient_go_gdetail_%'];
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
