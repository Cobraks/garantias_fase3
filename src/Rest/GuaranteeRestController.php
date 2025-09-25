<?php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Server;
use WP_Query;
use WP_REST_Response;
use WP_Error;
use GarantiasOnline360VO\Docs\PrivateDocsManager;
use GarantiasOnline360VO\GuaranteeLogger;
use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;

class GuaranteeRestController
{
    const CONTRACT_NOTICE_META = '_go360_pending_contract_notice';
    const CONTRACT_NOTICE_EVENT = 'go360/guarantee/dispatch_contract_notice';
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
            '/' . self::BASE . '/(?P<id>\d+)/document/(?P<type>[a-z_]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'download_document'],
                    'permission_callback' => [__CLASS__, 'can_view'],
                    'args'                => [
                        'id'   => ['validate_callback' => 'absint'],
                        'type' => ['sanitize_callback' => 'sanitize_text_field'],
                    ],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)/certificate',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'upload_certificate'],
                    'permission_callback' => [__CLASS__, 'can_edit'],
                    'args'                => [
                        'id' => ['validate_callback' => 'absint'],
                    ],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)/notify',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'dispatch_notifications'],
                    'permission_callback' => [__CLASS__, 'can_edit'],
                    'args'                => [
                        'id'   => ['validate_callback' => 'absint'],
                        'uuid' => [
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
            ]
        );
        add_filter('rest_pre_serve_request', [__CLASS__, 'serve_document'], 10, 4);

        add_action(self::CONTRACT_NOTICE_EVENT, [__CLASS__, 'handle_scheduled_contract_notice']);

        // Limpieza de transients al guardar/borrar garantías
        add_action('save_post_' . \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE, [__CLASS__, 'clear_list_transients'], 10, 3);
        add_action('deleted_post', [__CLASS__, 'clear_list_transients_on_delete']);
    }

    public static function download_document($request)
    {
        $id   = (int) $request['id'];
        $type = sanitize_key($request['type']);
        $binary = '';
        $filename = '';

        switch ($type) {
            case 'certificado':
                $hash = get_post_meta($id, 'documentacion_certificado_hash', true);
                if (!$hash) {
                    error_log('[download_document] no hash for ' . $id . ' type ' . $type);
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                error_log('[download_document] retrieving ' . $hash);
                $binary = PrivateDocsManager::retrieve($hash, 'pdf');
                if (!$binary) {
                    error_log('[download_document] retrieval failed ' . $hash);
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $info = self::get_plan_info($id);
                $filename = self::normalize_document_filename(sprintf(
                    'Certificado Garantía %s %s.pdf',
                    $info['plan'],
                    $info['matricula']
                ));
                break;
            case 'condicionado':
            case 'cobertura':
                $source = self::get_public_document_source($id, $type);
                if (!$source) {
                    error_log('[download_document] no source for ' . $id . ' type ' . $type);
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $binary = self::fetch_remote_pdf($source);
                if (!$binary) {
                    error_log('[download_document] fetch failed for ' . $source);
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $info = self::get_plan_info($id);
                $label = $type === 'condicionado'
                    ? __('Condicionado Garantía', 'garantias-online-360vo')
                    : __('Cobertura Garantía', 'garantias-online-360vo');
                $filename = self::normalize_document_filename(sprintf(
                    '%s %s %s.pdf',
                    $label,
                    $info['plan'],
                    $info['matricula']
                ));
                break;
            default:
                return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
        }

        GuaranteeLogger::log(get_current_user_id(), $id, 'document_downloaded', $type);
        $response = new WP_REST_Response($binary, 200);
        $response->header('Content-Type', 'application/pdf');
        $force_download = $request->get_param('download');
        $type_header = $force_download ? 'attachment' : 'inline';
        $disposition = sprintf(
            "%s; filename=\"%s\"; filename*=UTF-8''%s",
            $type_header,
            $filename,
            rawurlencode($filename)
        );
        $response->header('Content-Disposition', $disposition);
        return $response;
    }

    private static function get_plan_info($id)
    {
        $plan_id = get_post_meta($id, 'garantia_contratada_garantia', true);
        if ($plan_id) {
            $custom_plan = function_exists('get_field')
                ? get_field('detalles_modalidad_nombre_mostrar', $plan_id)
                : '';
            $plan = $custom_plan ?: get_the_title($plan_id);
        } else {
            $plan = '';
        }
        $matricula = get_post_meta($id, 'datos_vehiculo_matricula', true);

        return [
            'plan'      => is_string($plan) ? $plan : '',
            'matricula' => is_string($matricula) ? $matricula : '',
        ];
    }

    private static function normalize_document_filename($filename)
    {
        $clean = preg_replace('/\s+/', ' ', trim((string) $filename));
        return $clean !== '' ? $clean : 'documento.pdf';
    }

    private static function get_public_document_source($id, $type)
    {
        $meta_key = $type === 'condicionado' ? 'docs_url_condicionado' : 'docs_url_cobertura';
        $stored = get_post_meta($id, $meta_key, true);
        $stored = self::sync_document_meta($id, $meta_key, $stored);
        if ($stored !== '') {
            return $stored;
        }

        $plan_id = (int) get_post_meta($id, 'garantia_contratada_garantia', true);
        if ($plan_id > 0) {
            $field_key = $type === 'condicionado'
                ? 'detalles_modalidad_documentos_condicionado_garantia'
                : 'detalles_modalidad_documentos_coberturas';
            $context = self::build_document_context($id);
            $url = self::get_modalidad_document_url($plan_id, $field_key, $context);

            return self::sync_document_meta($id, $meta_key, $url);
        }

        self::sync_document_meta($id, $meta_key, '');

        return '';
    }

    private static function fetch_remote_pdf($url)
    {
        if (!$url) {
            return false;
        }
        $response = wp_remote_get($url, ['timeout' => 20]);
        if (is_wp_error($response)) {
            return false;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }
        $body = wp_remote_retrieve_body($response);
        if (!is_string($body) || $body === '') {
            return false;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (is_array($content_type)) {
            $content_type = reset($content_type);
        }
        $content_type = is_string($content_type) ? strtolower($content_type) : '';
        $is_pdf_header = $content_type && str_contains($content_type, 'application/pdf');

        if (!$is_pdf_header && !str_starts_with($body, '%PDF')) {
            return false;
        }

        return $body;
    }

    private static function build_document_download_url($id, $type, $with_nonce = true)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return '';
        }

        $type = sanitize_key($type);
        switch ($type) {
            case 'certificado':
                $hash = get_post_meta($id, 'documentacion_certificado_hash', true);
                if (!$hash) {
                    return '';
                }
                break;
            case 'condicionado':
            case 'cobertura':
                $source = self::get_public_document_source($id, $type);
                if (!$source) {
                    return '';
                }
                break;
            default:
                return '';
        }

        $url = rest_url(self::NAMESPACE . '/' . self::BASE . '/' . $id . '/document/' . $type);
        if ($with_nonce) {
            $url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $url);
        }
        $scheme = wp_parse_url(home_url(), PHP_URL_SCHEME);
        return set_url_scheme($url, $scheme);
    }

    private static function hydrate_detail_document_urls(array $detail, $id)
    {
        $detail['condicionado_url'] = self::build_document_download_url($id, 'condicionado');
        $detail['cobertura_url']    = self::build_document_download_url($id, 'cobertura');
        $detail['certificate_url']  = self::build_document_download_url($id, 'certificado');

        return $detail;
    }

    public static function serve_document($served, $result, $request, $server)
    {
        if ($result instanceof WP_REST_Response) {
            $headers = $result->get_headers();
            if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/pdf') {
                foreach ($headers as $k => $v) {
                    header($k . ': ' . $v);
                }
                echo $result->get_data();
                return true;
            }
        }
        return $served;
    }

    public static function upload_certificate($request)
    {
        $id = (int) $request['id'];
        if (!$id || get_post_type($id) !== \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
            return new WP_Error('invalid_id', __('ID de garantía no válido', 'garantias-online-360vo'), ['status' => 400]);
        }
        $binary = $request->get_body();
        if ($binary === '') {
            $binary = file_get_contents('php://input');
        }
        if ($binary === '' || $binary === false) {
            return new WP_Error('empty_pdf', __('PDF no recibido', 'garantias-online-360vo'), ['status' => 400]);
        }
        $signature = '';
        if (isset($_SERVER['HTTP_X_GO360_CERT_SIGNATURE'])) {
            $signature = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_GO360_CERT_SIGNATURE']));
        }
        $hash = PrivateDocsManager::store($binary, 'pdf');
        if (!$hash) {
            return new WP_Error('store_error', __('No se pudo guardar el certificado', 'garantias-online-360vo'), ['status' => 500]);
        }
        update_post_meta($id, 'documentacion_certificado_hash', $hash);
        if ($signature !== '') {
            update_post_meta($id, '_go360_certificate_signature', $signature);
            error_log('[CERTIFICATE] Stored signature for ID ' . $id . ' hash ' . $hash);
        }
        $url = self::build_document_download_url($id, 'certificado');
        GuaranteeLogger::log(get_current_user_id(), $id, 'document_uploaded', 'certificado');
        return new WP_REST_Response(['certificate_url' => $url], 201);
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
        $post = get_post($post_id);

        if ($post && (int) $post->post_author === (int) $uid) {
            return true;
        }

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
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $float = (float) $value;
            $str   = (string) $float;
            return strpos($str, '.') !== false ? rtrim(rtrim($str, '0'), '.') : $str;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(["\xC2\xA0", ' '], '', $value);

        $is_negative = strpos($value, '-') !== false;
        $value       = str_replace('-', '', $value);

        $value = preg_replace('/[^0-9.,]/', '', $value);
        if ($value === '') {
            return '';
        }

        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (strpos($value, '.') !== false) {
            $lastDot = strrpos($value, '.');
            $intPart = substr($value, 0, $lastDot);
            $decPart = substr($value, $lastDot + 1);
            $intPart = preg_replace('/[^0-9]/', '', $intPart);
            $decPart = preg_replace('/[^0-9]/', '', $decPart);
            if ($decPart !== '' && strlen($decPart) <= 2) {
                $value = $intPart . '.' . $decPart;
            } else {
                $value = $intPart . $decPart;
            }
        }

        $value = preg_replace('/[^0-9.]/', '', $value);
        if ($value === '') {
            return '';
        }

        if ($is_negative) {
            $value = '-' . $value;
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
        $template_url        = '';
        $cobertura_source    = '';
        $condicionado_source = '';
        $previous_contract_state = '';
        $new_contract_state      = '';
        $queued_contract_notice  = false;
        $contract_notice_context = [];
        $pending_payment_event   = null;
        $payment_method          = '';
        $previous_cobro          = 0;

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
            } else {
                $previous_contract_state = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
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
            error_log('[AUTOSAVE] guarantee created ' . $post_id);
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

        if ($post_id) {
            $previous_cobro = (int) get_post_meta($post_id, 'garantia_contratada_estado_cobro_cobro_realizado', true);
            $payment_method = (string) get_post_meta($post_id, 'garantia_contratada_metodo_pago', true);
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
                        if ($k === 'metodo_pago') {
                            $payment_method = $gc[$k];
                        }
                        break;
                    case 'estado_cobro':
                        if (is_array($v)) {
                            $ec = [];
                            if (isset($v['cobro_realizado'])) {
                                $ec['cobro_realizado'] = $v['cobro_realizado'] ? 1 : 0;
                                if ($post_id) {
                                    $new_cobro_value = (int) $ec['cobro_realizado'];
                                    if ($previous_cobro !== $new_cobro_value && $new_cobro_value === 1) {
                                        $pending_payment_event = [
                                            'method'     => $payment_method,
                                            'actor_type' => current_user_can('manage_options') ? 'platform' : 'actor',
                                        ];
                                    }
                                    $previous_cobro = $new_cobro_value;
                                }
                            }
                            if (isset($v['fecha_cobro'])) {
                                $ec['fecha_cobro'] = sanitize_text_field($v['fecha_cobro']);
                            }
                            $gc['estado_cobro'] = $ec;
                        }
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
            error_log('[AUTOSAVE] Saved garantia_contratada for ID ' . $post_id . ': ' . wp_json_encode($gc));
            if (isset($gc['garantia'])) {
                $plan_id = (int) $gc['garantia'];
                $context = self::build_document_context(
                    $post_id,
                    [
                        'canal_venta'                     => $gc['canal_venta'] ?? '',
                        'concesionario_empresa_profesional' => $gc['concesionario_empresa_profesional'] ?? 0,
                    ]
                );
                $template_url = self::get_modalidad_document_url(
                    $plan_id,
                    'detalles_modalidad_documentos_certificado_garantia',
                    $context
                );
                $cobertura_source = self::get_modalidad_document_url(
                    $plan_id,
                    'detalles_modalidad_documentos_coberturas',
                    $context
                );
                $condicionado_source = self::get_modalidad_document_url(
                    $plan_id,
                    'detalles_modalidad_documentos_condicionado_garantia',
                    $context
                );

                $cobertura_source = self::sync_document_meta($post_id, 'docs_url_cobertura', $cobertura_source);
                $condicionado_source = self::sync_document_meta($post_id, 'docs_url_condicionado', $condicionado_source);
            }
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
            if ($is_publishing && ($gc['metodo_pago'] ?? '') === 'domiciliacion') {
                $estado['estado_contratacion'] = 'activada';
            } elseif (isset($data['estado_garantia']['estado_contratacion'])) {
                $ec = sanitize_text_field($data['estado_garantia']['estado_contratacion']);
                $valid = ['pendiente_pago', 'sin_finalizar', 'activada', 'expirada', 'expira_pronto'];
                if (in_array($ec, $valid, true)) {
                    $estado['estado_contratacion'] = $ec;
                }
            }
            if ($estado) {
                if (isset($estado['estado_contratacion'])) {
                    $new_contract_state = sanitize_text_field($estado['estado_contratacion']);
                }
                foreach ($estado as $k => $v) {
                    update_post_meta($post_id, 'estado_garantia_' . $k, $v);
                }
                error_log('[AUTOSAVE] Saved estado_garantia for ID ' . $post_id . ': ' . wp_json_encode($estado));
            }
            unset($data['estado_garantia']);
        }

        if ($new_contract_state === '' && $post_id) {
            $current_meta_state = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
            if ($current_meta_state !== '') {
                $new_contract_state = $current_meta_state;
            }
        }

        if (
            in_array($new_contract_state, ['activada', 'pendiente_pago'], true)
            && $new_contract_state !== $previous_contract_state
        ) {
            $queued_contract_notice  = true;
            $contract_notice_context = [
                'initiator'      => get_current_user_id(),
                'previous_state' => $previous_contract_state,
                'current_state'  => $new_contract_state,
            ];
            error_log(sprintf(
                '[AUTOSAVE] Contract state changed from %s to %s for ID %d',
                $previous_contract_state !== '' ? $previous_contract_state : '(none)',
                $new_contract_state,
                $post_id
            ));
        }

        if (
            $queued_contract_notice
            && $new_contract_state === 'activada'
            && $post_id
            && ! $pending_payment_event
        ) {
            $method_for_payment = $payment_method !== ''
                ? $payment_method
                : (string) get_post_meta($post_id, 'garantia_contratada_metodo_pago', true);
            $method_key = sanitize_key($method_for_payment);
            if ($method_key !== '' && strpos($method_key, 'domiciliacion') !== 0) {
                $pending_payment_event = [
                    'method'     => $method_for_payment,
                    'actor_type' => current_user_can('manage_options') ? 'vendor' : 'actor',
                ];
            }
        }

        if ($pending_payment_event && $post_id) {
            $method_for_payment = $pending_payment_event['method'] !== ''
                ? $pending_payment_event['method']
                : (string) get_post_meta($post_id, 'garantia_contratada_metodo_pago', true);
            $method_for_payment = sanitize_text_field($method_for_payment);
            if ($method_for_payment !== '') {
                $state_for_payment = $new_contract_state !== ''
                    ? $new_contract_state
                    : (string) get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
                self::log_payment_event(
                    $post_id,
                    $method_for_payment,
                    $state_for_payment,
                    $pending_payment_event['actor_type'] ?? ''
                );
            }
            $pending_payment_event = null;
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

        $vendor_id   = get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $firma_sello = [
            'add_firma_sello' => false,
            'firma'           => '',
            'sello'           => '',
        ];

        if ($vendor_id) {
            if (function_exists('get_field')) {
                $add = get_field('documentos_firma_y_sello_add_firma_sello', 'user_' . $vendor_id);
                if ($add) {
                    $firma_id = get_field('documentos_firma_y_sello_firma', 'user_' . $vendor_id);
                    $sello_id = get_field('documentos_firma_y_sello_sello', 'user_' . $vendor_id);
                    $firma_sello = [
                        'add_firma_sello' => true,
                        'firma'           => $firma_id ? wp_get_attachment_url($firma_id) : '',
                        'sello'           => $sello_id ? wp_get_attachment_url($sello_id) : '',
                    ];
                }
            } else {
                $add = get_user_meta($vendor_id, 'documentos_firma_y_sello_add_firma_sello', true);
                if ($add) {
                    $firma_id = get_user_meta($vendor_id, 'documentos_firma_y_sello_firma', true);
                    $sello_id = get_user_meta($vendor_id, 'documentos_firma_y_sello_sello', true);
                    $firma_sello = [
                        'add_firma_sello' => true,
                        'firma'           => $firma_id ? wp_get_attachment_url($firma_id) : '',
                        'sello'           => $sello_id ? wp_get_attachment_url($sello_id) : '',
                    ];
                }
            }
        }

        if (!$cobertura_source) {
            $stored_cobertura = get_post_meta($post_id, 'docs_url_cobertura', true);
            $cobertura_source = self::sync_document_meta($post_id, 'docs_url_cobertura', $stored_cobertura);
        }
        if (!$condicionado_source) {
            $stored_condicionado = get_post_meta($post_id, 'docs_url_condicionado', true);
            $condicionado_source = self::sync_document_meta($post_id, 'docs_url_condicionado', $stored_condicionado);
        }

        $cobertura_url = self::build_document_download_url($post_id, 'cobertura');
        $condicionado_url = self::build_document_download_url($post_id, 'condicionado');
        $transfer_iban = self::get_transfer_iban();

        $notify_url = rest_url(self::NAMESPACE . '/' . self::BASE . '/' . $post_id . '/notify');
        $notify_url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $notify_url);
        $scheme     = wp_parse_url(home_url(), PHP_URL_SCHEME);

        $response = [
            'id'               => $post_id,
            'uuid'             => $uuid,
            'template_url'     => $template_url,
            'cobertura_url'    => $cobertura_url,
            'condicionado_url' => $condicionado_url,
            'transfer_iban'    => $transfer_iban['formatted'],
            'firma_sello'      => $firma_sello,
            'notify_url'       => set_url_scheme($notify_url, $scheme),
        ];

        if ($queued_contract_notice && ! empty($contract_notice_context)) {
            $contract_notice_context['queued_at'] = current_time('mysql');
            update_post_meta($post_id, self::CONTRACT_NOTICE_META, $contract_notice_context);
            GuaranteeLogger::log(
                get_current_user_id(),
                $post_id,
                'contract_notice_queued',
                wp_json_encode($contract_notice_context)
            );
            self::schedule_contract_notice_dispatch($post_id);
        }

        return new WP_REST_Response($response);
    }

    public static function dispatch_notifications($request)
    {
        $post_id = isset($request['id']) ? absint($request['id']) : 0;
        $uuid    = isset($request['uuid']) ? sanitize_text_field($request['uuid']) : '';
        if ($uuid === '') {
            $body = $request->get_json_params();
            if (is_array($body) && isset($body['uuid'])) {
                $uuid = sanitize_text_field($body['uuid']);
            }
        }

        if ($post_id <= 0 || get_post_type($post_id) !== \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
            return new WP_Error('invalid_id', __('ID de garantía no válido', 'garantias-online-360vo'), ['status' => 400]);
        }

        $stored_uuid = get_post_meta($post_id, 'estado_garantia_uuid', true);
        if (! $uuid || $stored_uuid !== $uuid) {
            return new WP_Error('invalid_uuid', __('Identificador de sesión no válido', 'garantias-online-360vo'), ['status' => 403]);
        }
        $dispatched = self::dispatch_contract_notice_internal($post_id, get_current_user_id());
        if (! $dispatched) {
            self::schedule_contract_notice_dispatch($post_id);
        }

        return rest_ensure_response([
            'dispatched' => $dispatched,
        ]);
    }

    public static function handle_scheduled_contract_notice($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        error_log('[AUTOSAVE] Scheduled contract notice execution for ID ' . $post_id);
        self::dispatch_contract_notice_internal($post_id, 0);
    }

    private static function get_modalidad_document_url($plan_id, $field_key, array $context = [])
    {
        static $cache = [];

        $plan_id   = (int) $plan_id;
        $field_key = (string) $field_key;

        if ($plan_id <= 0 || $field_key === '') {
            return '';
        }

        $context_key = $context ? md5(wp_json_encode($context)) : 'default';

        if (isset($cache[$plan_id][$field_key][$context_key])) {
            return $cache[$plan_id][$field_key][$context_key];
        }

        $url = '';

        $filtered = apply_filters('go_modalidad_document_source', '', $plan_id, $field_key, $context);
        if (is_string($filtered) && $filtered !== '') {
            $url = esc_url_raw($filtered);
        }

        if ($url === '' && function_exists('get_field')) {
            $file = get_field($field_key, $plan_id);
            $url  = self::normalize_acf_file_url($file);
        }

        if (!isset($cache[$plan_id])) {
            $cache[$plan_id] = [];
        }
        if (!isset($cache[$plan_id][$field_key])) {
            $cache[$plan_id][$field_key] = [];
        }

        $cache[$plan_id][$field_key][$context_key] = $url;

        return $url;
    }

    private static function schedule_contract_notice_dispatch($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        if (wp_next_scheduled(self::CONTRACT_NOTICE_EVENT, [$post_id])) {
            error_log('[AUTOSAVE] Contract notice dispatch already scheduled for ID ' . $post_id);
            return;
        }

        $timestamp = time() + 5;
        wp_schedule_single_event($timestamp, self::CONTRACT_NOTICE_EVENT, [$post_id]);
        error_log('[AUTOSAVE] Scheduled contract notice dispatch via cron for ID ' . $post_id);
        self::spawn_contract_cron();
    }

    private static function spawn_contract_cron()
    {
        if ((defined('DOING_CRON') && DOING_CRON) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return;
        }

        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
    }

    private static function dispatch_contract_notice_internal($post_id, $actor_id = 0)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return false;
        }

        $context = get_post_meta($post_id, self::CONTRACT_NOTICE_META, true);
        if (! is_array($context) || empty($context)) {
            error_log('[AUTOSAVE] No pending contract notice for ID ' . $post_id);
            return false;
        }

        delete_post_meta($post_id, self::CONTRACT_NOTICE_META);

        $initiator = (int) ($context['initiator'] ?? $actor_id);
        if ($initiator <= 0) {
            $initiator = get_current_user_id();
        }

        $prepared_context = [
            'initiator'      => $initiator,
            'previous_state' => sanitize_text_field($context['previous_state'] ?? ''),
            'current_state'  => sanitize_text_field($context['current_state'] ?? ''),
        ];

        error_log('[AUTOSAVE] Dispatching contract notice for ID ' . $post_id . ' (state ' . $prepared_context['current_state'] . ')');
        do_action('go360/guarantee/contracted', $post_id, $prepared_context);

        GuaranteeLogger::log(
            $initiator,
            $post_id,
            'contract_notice_dispatched',
            wp_json_encode($prepared_context)
        );

        return true;
    }

    private static function normalize_acf_file_url($value)
    {
        if (is_array($value)) {
            if (!empty($value['url'])) {
                return esc_url_raw($value['url']);
            }
            if (!empty($value['ID'])) {
                $tmp = wp_get_attachment_url((int) $value['ID']);
                if ($tmp) {
                    return esc_url_raw($tmp);
                }
            }
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                return esc_url_raw($value);
            }
        }

        return '';
    }

    private static function build_document_context($post_id, array $overrides = [])
    {
        $post_id = (int) $post_id;
        $context = [
            'channel'   => self::normalize_channel_meta(get_post_meta($post_id, 'garantia_contratada_canal_venta', true)),
            'vendor_id' => self::normalize_vendor_meta(get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true)),
        ];

        if (array_key_exists('canal_venta', $overrides)) {
            $context['channel'] = sanitize_text_field((string) $overrides['canal_venta']);
        } elseif (array_key_exists('channel', $overrides)) {
            $context['channel'] = sanitize_text_field((string) $overrides['channel']);
        }

        if (array_key_exists('concesionario_empresa_profesional', $overrides)) {
            $context['vendor_id'] = (int) $overrides['concesionario_empresa_profesional'];
        } elseif (array_key_exists('vendor_id', $overrides)) {
            $context['vendor_id'] = (int) $overrides['vendor_id'];
        }

        return $context;
    }

    private static function normalize_channel_meta($raw)
    {
        if (is_array($raw)) {
            if (isset($raw['value'])) {
                return sanitize_text_field((string) $raw['value']);
            }
            if (isset($raw['label']) && ! isset($raw['value'])) {
                return sanitize_text_field((string) $raw['label']);
            }
        } elseif (is_string($raw)) {
            return sanitize_text_field($raw);
        }

        return '';
    }

    private static function normalize_vendor_meta($raw)
    {
        if (is_array($raw)) {
            if (isset($raw['ID'])) {
                return (int) $raw['ID'];
            }
            if (isset($raw['id'])) {
                return (int) $raw['id'];
            }
        }

        return (int) $raw;
    }

    private static function sync_document_meta($post_id, $meta_key, $value)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return '';
        }

        $meta_key = sanitize_key($meta_key);

        $new_value = '';
        if (is_array($value)) {
            if (!empty($value['url'])) {
                $new_value = esc_url_raw($value['url']);
            } elseif (!empty($value['ID'])) {
                $tmp = wp_get_attachment_url((int) $value['ID']);
                if ($tmp) {
                    $new_value = esc_url_raw($tmp);
                }
            }
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                $new_value = esc_url_raw($value);
            }
        }

        $current = get_post_meta($post_id, $meta_key, true);
        $current = is_string($current) ? trim($current) : '';

        if ($new_value === '') {
            if ($current !== '') {
                delete_post_meta($post_id, $meta_key);
            }

            return '';
        }

        if ($current !== $new_value) {
            update_post_meta($post_id, $meta_key, $new_value);
        }

        return $new_value;
    }

    private static function get_transfer_iban()
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cached = [
            'raw'       => '',
            'formatted' => '',
        ];

        $iban = '';

        if (function_exists('get_field')) {
            $group = get_field('datos_bancarios', SettingsPage::SUBMENU_SLUG);
            if (is_array($group) && !empty($group['iban_360vo'])) {
                $iban = (string) $group['iban_360vo'];
            } else {
                $single = get_field('datos_bancarios_iban_360vo', SettingsPage::SUBMENU_SLUG);
                if (is_string($single) && $single !== '') {
                    $iban = $single;
                }
            }
        } else {
            $option = get_option('options_datos_bancarios_iban_360vo');
            if (is_string($option) && $option !== '') {
                $iban = $option;
            }
        }

        if (is_string($iban)) {
            $normalized = preg_replace('/[^A-Z0-9]/i', '', strtoupper($iban));
            if (is_string($normalized) && $normalized !== '') {
                $cached['raw'] = $normalized;
                $cached['formatted'] = trim(chunk_split($normalized, 4, ' '));
            }
        }

        return $cached;
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
    private static function get_detail_data($id, $include_document_urls = true)
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
        $metodo_pago_raw = get_post_meta($id, 'garantia_contratada_metodo_pago', true);
        $metodo_pago = is_array($metodo_pago_raw)
            ? ($metodo_pago_raw['value'] ?? '')
            : $metodo_pago_raw;
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
        $vendor_id = is_array($vendor_id) && isset($vendor_id['ID']) ? (int) $vendor_id['ID'] : (int) $vendor_id;
        $labels = [
            'company_name' => '',
            'personal_name' => '',
            'company' => [
                'name' => '',
                'trade_name' => '',
                'legal_name' => '',
                'tax_id' => '',
                'type' => ['value' => '', 'label' => ''],
                'address' => ['street' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => ''],
            ],
        ];
        if ($vendor_id) {
            $labels = UserProfileResolver::get_vendor_labels($vendor_id);
        }
        $concesionario = $labels['company_name'] !== '' ? $labels['company_name'] : ($labels['personal_name'] ?? '');

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
            ? NotificationEmailResolver::resolve($vendor_id)
            : '';
        $avatar_vendedor = $vendor_id ? get_avatar_url($vendor_id, ['size' => 96]) : '';
        $vendedor_url   = $vendor_id ? get_edit_user_link($vendor_id) : '#';

        $cobro_realizado = get_post_meta($id, 'garantia_contratada_estado_cobro_cobro_realizado', true);
        $iban_vendedor = $vendor_id
            ? get_user_meta($vendor_id, 'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta', true)
            : '';
        $transfer_iban = self::get_transfer_iban();

        $nombre_comprador = get_post_meta($id, 'datos_cliente_nombre_y_apellidos', true);
        $dni_comprador = get_post_meta($id, 'datos_cliente_dni', true);
        $telefono_comprador = get_post_meta($id, 'datos_cliente_telefono', true);
        $email_comprador = get_post_meta($id, 'datos_cliente_email', true);
        $direccion_comprador = get_post_meta($id, 'datos_cliente_direccion', true);
        $localidad_comprador = get_post_meta($id, 'datos_cliente_localidad', true);
        $provincia_comprador = get_post_meta($id, 'datos_cliente_provincia', true);
        $codigo_postal_comprador = get_post_meta($id, 'datos_cliente_codigo_postal', true);

        $uuid = get_post_meta($id, 'estado_garantia_uuid', true);

        $detail = [
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
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => [
                'value' => $estado,
                'label' => $estado_label,
            ],
            'concesionario' => $concesionario ?: '-',
            'vendor_id' => $vendor_id,
            'concesionario_personal' => $labels['personal_name'] ?? '',
            'vendor_company' => $labels['company'] ?? [],
            'canal_venta' => $canal_venta ?: '-',
            'canal_venta_value' => $canal_venta_value,
            'telefono_vendedor' => $telefono_vendedor ?: '',
            'email_vendedor' => sanitize_email($email_vendedor) ?: '',
            'avatar_vendedor' => $avatar_vendedor ?: '',
            'vendedor_url' => $vendedor_url,
            'condicionado_url' => '',
            'cobertura_url' => '',
            'certificate_url' => '',
            'cobro_realizado' => $cobro_realizado ? true : false,
            'iban_vendedor' => $iban_vendedor ?: '',
            'transfer_iban' => $transfer_iban['formatted'],
            'nombre_comprador' => $nombre_comprador ?: '-',
            'dni_comprador' => $dni_comprador ?: '-',
            'telefono_comprador' => $telefono_comprador ?: '-',
            'email_comprador' => $email_comprador ?: '-',
            'direccion_comprador' => $direccion_comprador ?: '-',
            'localidad_comprador' => $localidad_comprador ?: '-',
            'provincia_comprador' => $provincia_comprador ?: '-',
            'codigo_postal_comprador' => $codigo_postal_comprador ?: '-',
        ];

        if ($include_document_urls) {
            $detail = self::hydrate_detail_document_urls($detail, $id);
        }

        return $detail;
    }

    public static function collect_detail_snapshot($id, $include_document_urls = true)
    {
        return self::get_detail_data($id, $include_document_urls);
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
        if ($estado === 'pendiente_cobro') {
            $meta_query[] = [
                'relation' => 'AND',
                [
                    'key'   => 'garantia_contratada_metodo_pago',
                    'value' => 'domiciliacion_bancaria',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => 'garantia_contratada_estado_cobro_cobro_realizado',
                        'value'   => 0,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => 'garantia_contratada_estado_cobro_cobro_realizado',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ];
        } elseif ($estado) {
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
            $detail  = get_transient('go_gdetail_' . $post_id);
            if (
                $detail === false ||
                !is_array($detail) ||
                !isset($detail['metodo_pago']) ||
                !array_key_exists('cobro_realizado', $detail)
            ) {
                $detail = self::get_detail_data($post_id, false);
                set_transient('go_gdetail_' . $post_id, $detail, 300);
            }

            $detail_with_urls = self::hydrate_detail_document_urls($detail, $post_id);

            $data[] = [
                'id'         => $post_id,
                'mat'        => $detail_with_urls['matricula'],
                'marca'      => $detail_with_urls['marca_modelo'],
                'desde'      => $detail_with_urls['desde'],
                'hasta'      => $detail_with_urls['hasta'],
                'plan'       => $detail_with_urls['plan'],
                'precio'     => $detail_with_urls['precio'],
                'estado'     => $detail_with_urls['estado'],
                'vendedor'   => $detail_with_urls['concesionario'],
                'canal_venta' => [
                    'value' => $detail_with_urls['canal_venta_value'] ?? '',
                    'label' => $detail_with_urls['canal_venta'],
                ],
                'detail'     => $detail_with_urls,
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
        if (
            $cached !== false &&
            is_array($cached) &&
            isset($cached['metodo_pago']) &&
            array_key_exists('cobro_realizado', $cached)
        ) {
            return rest_ensure_response(self::hydrate_detail_document_urls($cached, $id));
        }
        $data = self::get_detail_data($id, false);

        set_transient($cache_key, $data, 300);

        return rest_ensure_response(self::hydrate_detail_document_urls($data, $id));
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

        if (current_user_can('manage_options')) {
            $estados[] = [
                'value' => 'pendiente_cobro',
                'label' => __('Pendiente de cobro', 'garantias-online-360vo'),
            ];
        }

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
            'fields' => ['ID'],
        ]);
        $concesionarios = [];
        foreach ($users as $u) {
            $labels = UserProfileResolver::get_vendor_labels((int) $u->ID);
            $name = $labels['company_name'] !== ''
                ? $labels['company_name']
                : ($labels['personal_name'] !== '' ? $labels['personal_name'] : sprintf(__('Usuario #%d', 'garantias-online-360vo'), (int) $u->ID));
            $concesionarios[] = [
                'id'   => (int) $u->ID,
                'name' => $name,
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

    private static function log_payment_event(int $post_id, string $method, string $state, string $actor_type): void
    {
        $method = sanitize_text_field($method);
        if ($post_id <= 0 || $method === '') {
            return;
        }

        $payload = ['method' => $method];

        $state = sanitize_text_field($state);
        if ($state !== '') {
            $payload['state'] = $state;
        }

        $actor_type = sanitize_key($actor_type);
        if ($actor_type !== '') {
            $payload['actor_type'] = $actor_type;
        }

        GuaranteeLogger::log(
            get_current_user_id(),
            $post_id,
            'payment_recorded',
            wp_json_encode($payload)
        );
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
