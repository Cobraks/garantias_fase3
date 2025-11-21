<?php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Server;
use WP_Query;
use WP_REST_Response;
use WP_Error;
use DateTimeImmutable;
use GarantiasOnline360VO\Docs\PrivateDocsManager;
use GarantiasOnline360VO\Register\SepaMandateService;
use GarantiasOnline360VO\GuaranteeLogger;
use GarantiasOnline360VO\Notifications\Email\EmailMessage;
use GarantiasOnline360VO\Notifications\Email\EmailNotificationService;
use GarantiasOnline360VO\Notifications\Email\GuaranteeEmailDataFactory;
use GarantiasOnline360VO\Notifications\Email\Mailer;
use GarantiasOnline360VO\Notifications\Email\TemplateRenderer;
use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;

class GuaranteeRestController
{
    const CONTRACT_NOTICE_META = '_go360_pending_contract_notice';
    const CONTRACT_NOTICE_EVENT = 'go360/guarantee/dispatch_contract_notice';
    const NAMESPACE = 'go/v1';
    const BASE      = 'guarantees';
    const ADDITIONAL_DOCS_FIELD = 'garantia_contratada_documentacion_add_document';
    const TRANSFER_RECEIPT_HASH_META = '_go360_transfer_receipt_hash';
    const TRANSFER_RECEIPT_EXTENSION_META = '_go360_transfer_receipt_extension';
    const TRANSFER_RECEIPT_ROW_META = '_go360_transfer_receipt_row';
    const SUMMARY_TRANSIENT = 'go_gsummary_admin';
    const LIST_CACHE_GENERATION_OPTION = 'go_glist_generation';
    const SUMMARY_PROFESSIONAL_TRANSIENT_PREFIX = 'go_gsummary_prof_';
    const RECEIPT_ALLOWED_MIMES = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];
    const RECEIPT_MAX_BYTES = 10485760; // 10 MB

    private static $cache_hooks_registered = false;
    private static $list_cache_invalidated = [];

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
                        'vendor_type'  => ['sanitize_callback' => 'sanitize_key'],
                        'payment_method' => ['sanitize_callback' => 'sanitize_key'],
                        'commercial'   => ['validate_callback' => 'absint'],
                        'order_by'     => ['sanitize_callback' => 'sanitize_key'],
                        'order'        => ['sanitize_callback' => 'sanitize_key'],
                        'year'         => ['validate_callback' => 'absint'],
                        'month_from'   => ['validate_callback' => 'absint'],
                        'month_to'     => ['validate_callback' => 'absint'],
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
            '/' . self::BASE . '/summary',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_summary'],
                    'permission_callback' => [__CLASS__, 'can_view_summary'],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/generation',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_list_generation'],
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
            '/' . self::BASE . '/(?P<id>\d+)/document/extra/(?P<row>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'download_document'],
                    'permission_callback' => [__CLASS__, 'can_view'],
                    'args'                => [
                        'id'  => ['validate_callback' => 'absint'],
                        'row' => ['validate_callback' => 'absint'],
                        'type' => [
                            'default'           => 'extra',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
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
            '/' . self::BASE . '/(?P<id>\d+)/trash',
            [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [__CLASS__, 'trash_item'],
                    'permission_callback' => [__CLASS__, 'can_trash'],
                    'args'                => [
                        'id' => ['validate_callback' => 'absint'],
                    ],
                ],
            ]
        );
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE . '/(?P<id>\d+)/confirm-transfer',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'confirm_transfer'],
                    'permission_callback' => [__CLASS__, 'can_edit'],
                    'args'                => [
                        'id' => ['validate_callback' => 'absint'],
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

        self::register_cache_hooks();
    }

    public static function register_cache_hooks(): void
    {
        if (self::$cache_hooks_registered) {
            return;
        }

        self::$cache_hooks_registered = true;
        // Limpieza de transients al guardar/borrar garantías
        add_action('save_post_' . \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE, [__CLASS__, 'clear_list_transients'], 10, 3);
        add_action('deleted_post', [__CLASS__, 'clear_list_transients_on_delete']);
    }

    public static function get_summary($request)
    {
        $current_user = wp_get_current_user();
        return rest_ensure_response(self::get_summary_data_for_user($current_user));
    }

    public static function get_admin_summary_data(): array
    {
        $cached = get_transient(self::SUMMARY_TRANSIENT);
        if (is_array($cached)) {
            $cached_has_draft_state = false;
            $normalized_cached = self::normalize_admin_summary($cached, $cached_has_draft_state);

            if ($cached_has_draft_state) {
                if ($normalized_cached !== $cached) {
                    set_transient(self::SUMMARY_TRANSIENT, $normalized_cached, 5 * MINUTE_IN_SECONDS);
                }

                return $normalized_cached;
            }
        }

        $data = self::build_admin_summary();
        $data = is_array($data) ? $data : [];

        $data_has_draft_state = false;
        $normalized_data = self::normalize_admin_summary($data, $data_has_draft_state);

        if (! empty($normalized_data)) {
            set_transient(self::SUMMARY_TRANSIENT, $normalized_data, 5 * MINUTE_IN_SECONDS);
        }

        return $normalized_data;
    }

    public static function get_summary_data_for_user($user = null): array
    {
        if (! $user instanceof \WP_User) {
            $user = wp_get_current_user();
        }

        if ($user instanceof \WP_User && self::user_is_professional($user)) {
            return self::get_professional_summary_data((int) $user->ID);
        }

        return self::get_admin_summary_data();
    }

    private static function get_professional_summary_data(int $user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            $empty_payload = self::build_empty_summary_payload();
            $dummy = false;

            return self::normalize_admin_summary($empty_payload, $dummy);
        }

        $cache_key = self::SUMMARY_PROFESSIONAL_TRANSIENT_PREFIX . $user_id;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $cached_has_draft_state = false;
            $normalized_cached = self::normalize_admin_summary($cached, $cached_has_draft_state);

            if ($cached_has_draft_state) {
                if ($normalized_cached !== $cached) {
                    set_transient($cache_key, $normalized_cached, 5 * MINUTE_IN_SECONDS);
                }

                return $normalized_cached;
            }
        }

        $vendor_ids = self::get_accessible_professional_vendor_ids($user_id);
        if (empty($vendor_ids)) {
            $empty_payload = self::build_empty_summary_payload();
            $dummy = false;
            $normalized_empty = self::normalize_admin_summary($empty_payload, $dummy);
            set_transient($cache_key, $normalized_empty, 5 * MINUTE_IN_SECONDS);

            return $normalized_empty;
        }

        $summary = self::build_professional_summary($vendor_ids);
        $summary = is_array($summary) ? $summary : [];

        $has_draft_state = false;
        $normalized = self::normalize_admin_summary($summary, $has_draft_state);

        if (! empty($normalized)) {
            set_transient($cache_key, $normalized, 5 * MINUTE_IN_SECONDS);
        }

        return $normalized;
    }

    private static function build_empty_summary_payload(): array
    {
        $state_counts = [
            'activada'             => 0,
            'pendiente_pago'       => 0,
            'validacion_pendiente' => 0,
            'pendiente_cobro'      => 0,
            'sin_finalizar'        => 0,
        ];
        $state_amounts = [
            'activada'             => 0.0,
            'pendiente_pago'       => 0.0,
            'validacion_pendiente' => 0.0,
            'pendiente_cobro'      => 0.0,
            'sin_finalizar'        => 0.0,
        ];

        $states  = self::aggregate_summary_states($state_counts);
        $amounts = self::aggregate_summary_state_amounts($state_amounts);
        $month_label = function_exists('date_i18n') ? date_i18n('F Y') : gmdate('F Y');

        $context = [
            'label'      => __('Tus garantías', 'garantias-online-360vo'),
            'count'      => 0,
            'states'     => $states,
            'amounts'    => $amounts,
            'trends'     => [],
            'month_name' => $month_label,
        ];

        return [
            'totals'    => ['count' => 0],
            'states'    => $states,
            'contexts'  => [
                'year'  => $context,
                'month' => $context,
            ],
            'pending'   => [
                'draft'      => ['count' => 0, 'amount' => 0.0],
                'payment'    => ['count' => 0, 'amount' => 0.0],
                'validation' => ['count' => 0, 'amount' => 0.0],
                'collect'    => ['count' => 0, 'amount' => 0.0],
            ],
            'month'      => $context,
            'currency'   => 'EUR',
            'updated_at' => current_time('mysql'),
        ];
    }

    private static function build_professional_summary(array $vendor_ids): array
    {
        $normalized_vendor_ids = array_values(array_unique(array_filter(array_map('intval', $vendor_ids))));
        if (empty($normalized_vendor_ids)) {
            return self::build_empty_summary_payload();
        }

        $statuses = self::get_summary_post_statuses();
        $args = [
            'post_type'      => \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE,
            'post_status'    => $statuses,
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => 'garantia_contratada_concesionario_empresa_profesional',
                    'value'   => $normalized_vendor_ids,
                    'compare' => 'IN',
                ],
            ],
        ];

        $post_ids = get_posts($args);
        if (! is_array($post_ids) || empty($post_ids)) {
            return self::build_empty_summary_payload();
        }

        $state_counts = [
            'activada'             => 0,
            'pendiente_pago'       => 0,
            'validacion_pendiente' => 0,
            'pendiente_cobro'      => 0,
            'sin_finalizar'        => 0,
        ];
        $state_amounts = [
            'activada'             => 0.0,
            'pendiente_pago'       => 0.0,
            'validacion_pendiente' => 0.0,
            'pendiente_cobro'      => 0.0,
            'sin_finalizar'        => 0.0,
        ];

        $pending_payment_count     = 0;
        $pending_payment_amount    = 0.0;
        $pending_collect_count     = 0;
        $pending_collect_amount    = 0.0;
        $pending_validation_count  = 0;
        $pending_validation_amount = 0.0;
        $draft_count               = 0;

        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                continue;
            }

            $state = (string) get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
            if ($state === '') {
                $state = 'sin_finalizar';
            }

            if (! array_key_exists($state, $state_counts)) {
                $state_counts[$state] = 0;
                $state_amounts[$state] = 0.0;
            }

            $state_counts[$state]++;

            if ($state === 'sin_finalizar') {
                $draft_count++;
            }

            $price = self::normalize_price_amount(get_post_meta($post_id, 'garantia_contratada_precio', true));

            if (in_array($state, ['activada', 'pendiente_pago', 'validacion_pendiente', 'pendiente_cobro'], true)) {
                $state_amounts[$state] += $price;
            }

            if ($state === 'pendiente_pago') {
                $pending_payment_count++;
                $pending_payment_amount += $price;
            } elseif ($state === 'pendiente_cobro') {
                $pending_collect_count++;
                $pending_collect_amount += $price;
            } elseif ($state === 'validacion_pendiente') {
                $pending_validation_count++;
                $pending_validation_amount += $price;
            }
        }

        $states      = self::aggregate_summary_states($state_counts);
        $amounts     = self::aggregate_summary_state_amounts($state_amounts);
        $total_posts = count($post_ids);
        $month_label = function_exists('date_i18n') ? date_i18n('F Y') : gmdate('F Y');

        $context = [
            'label'      => __('Tus garantías', 'garantias-online-360vo'),
            'count'      => (int) $total_posts,
            'states'     => $states,
            'amounts'    => $amounts,
            'trends'     => [],
            'month_name' => $month_label,
        ];

        return [
            'totals'    => ['count' => (int) $total_posts],
            'states'    => $states,
            'contexts'  => [
                'year'  => $context,
                'month' => $context,
            ],
            'pending'   => [
                'draft'      => ['count' => (int) $draft_count, 'amount' => 0.0],
                'payment'    => ['count' => (int) $pending_payment_count, 'amount' => round($pending_payment_amount, 2)],
                'validation' => ['count' => (int) $pending_validation_count, 'amount' => round($pending_validation_amount, 2)],
                'collect'    => ['count' => (int) $pending_collect_count, 'amount' => round($pending_collect_amount, 2)],
            ],
            'month'      => $context,
            'currency'   => 'EUR',
            'updated_at' => current_time('mysql'),
        ];
    }

    private static function get_accessible_professional_vendor_ids(int $user_id): array
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return [];
        }

        $vendor_ids = [$user_id];
        $assigned = get_users([
            'role__in' => ['go_profesional', 'profesional'],
            'fields'   => 'ID',
            'meta_query' => [
                [
                    'key'     => 'ajustes_usuarios_comercial_asignado',
                    'value'   => '"' . $user_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if (is_array($assigned) && ! empty($assigned)) {
            foreach ($assigned as $assigned_id) {
                $vendor_ids[] = (int) $assigned_id;
            }
        }

        return array_values(array_unique(array_filter(array_map('intval', $vendor_ids))));
    }

    public static function download_document($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;
        if ($id <= 0) {
            return new WP_Error('invalid_id', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
        }

        $type = isset($request['type']) ? sanitize_key($request['type']) : '';
        $force_download = (bool) $request->get_param('download');

        if ($type === 'extra') {
            $row_index = isset($request['row']) ? (int) $request['row'] : 0;
            if ($row_index <= 0) {
                return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
            }

            $document = self::locate_additional_document($id, $row_index);
            if (!$document) {
                return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
            }

            if (!self::current_user_can_access_document($document)) {
                return new WP_Error('forbidden_document', __('No tienes permisos para ver este documento.', 'garantias-online-360vo'), ['status' => 403]);
            }

            $filename = self::normalize_document_filename($document['filename'] ?? ($document['title'] ?? 'documento.pdf'));
            $mime = 'application/octet-stream';
            $binary = '';

            if (!empty($document['is_private'])) {
                $extension = isset($document['extension']) && $document['extension'] !== ''
                    ? strtolower((string) $document['extension'])
                    : 'pdf';
                $hash = (string) ($document['hash'] ?? '');
                if ($hash === '') {
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $binary = PrivateDocsManager::retrieve($hash, $extension);
                if (!is_string($binary) || $binary === '') {
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $mime = self::map_extension_to_mime($extension);
                if (!str_contains($filename, '.')) {
                    $filename .= '.' . $extension;
                }
            } else {
                $public = self::load_public_document_binary($document);
                if (!$public) {
                    return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
                }
                $binary = $public['binary'];
                $mime = $public['mime'];
                $filename = self::normalize_document_filename($public['filename']);
            }

            GuaranteeLogger::log(get_current_user_id(), $id, 'document_downloaded', 'extra:' . ($document['key'] ?? $row_index));

            $response = new WP_REST_Response($binary, 200);
            $response->header('Content-Type', $mime);
            $response->header('Content-Disposition', self::build_content_disposition_header($force_download, $filename));
            $response->header('X-Go360-Binary', '1');

            return $response;
        }

        $binary = '';
        $filename = '';
        $mime = 'application/pdf';

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
                $mime = 'application/pdf';
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
                $mime = 'application/pdf';
                break;
            default:
                return new WP_Error('not_found', __('Documento no disponible', 'garantias-online-360vo'), ['status' => 404]);
        }

        GuaranteeLogger::log(get_current_user_id(), $id, 'document_downloaded', $type);
        $response = new WP_REST_Response($binary, 200);
        $response->header('Content-Type', $mime);
        $response->header('Content-Disposition', self::build_content_disposition_header($force_download, $filename));
        $response->header('X-Go360-Binary', '1');

        return $response;
    }

    private static function build_content_disposition_header($force_download, $filename)
    {
        $sanitized = $filename !== '' ? $filename : 'documento.pdf';
        $type_header = $force_download ? 'attachment' : 'inline';

        return sprintf(
            "%s; filename=\"%s\"; filename*=UTF-8''%s",
            $type_header,
            $sanitized,
            rawurlencode($sanitized)
        );
    }

    private static function map_extension_to_mime($extension)
    {
        $ext = strtolower((string) $extension);
        return match ($ext) {
            'pdf'  => 'application/pdf',
            'jpg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default => 'application/octet-stream',
        };
    }

    private static function load_public_document_binary(array $document)
    {
        $attachment_id = isset($document['attachment_id']) ? (int) $document['attachment_id'] : 0;
        $attachment_url = isset($document['attachment_url']) ? trim((string) $document['attachment_url']) : '';
        $fallback_filename = isset($document['filename']) ? (string) $document['filename'] : '';

        if ($attachment_id > 0) {
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                $binary = file_get_contents($file_path);
                if ($binary !== false) {
                    $mime = get_post_mime_type($attachment_id);
                    if (!$mime) {
                        $filetype = wp_check_filetype($file_path);
                        $mime = isset($filetype['type']) && $filetype['type']
                            ? $filetype['type']
                            : 'application/octet-stream';
                    }
                    $filename = $fallback_filename !== '' ? $fallback_filename : basename($file_path);
                    return [
                        'binary'   => $binary,
                        'mime'     => $mime,
                        'filename' => $filename,
                    ];
                }
            }
        }

        if ($attachment_url !== '') {
            $response = wp_remote_get($attachment_url, ['timeout' => 20]);
            if (!is_wp_error($response)) {
                $code = (int) wp_remote_retrieve_response_code($response);
                if ($code === 200) {
                    $body = wp_remote_retrieve_body($response);
                    if (is_string($body) && $body !== '') {
                        $content_type = wp_remote_retrieve_header($response, 'content-type');
                        if (is_array($content_type)) {
                            $content_type = reset($content_type);
                        }
                        $mime = is_string($content_type) && $content_type !== ''
                            ? strtolower($content_type)
                            : (isset($document['mime']) ? (string) $document['mime'] : 'application/octet-stream');
                        $filename = $fallback_filename;
                        if ($filename === '') {
                            $path = parse_url($attachment_url, PHP_URL_PATH);
                            $filename = $path ? basename($path) : 'documento';
                        }
                        return [
                            'binary'   => $body,
                            'mime'     => $mime,
                            'filename' => $filename,
                        ];
                    }
                }
            }
        }

        return null;
    }

    private static function locate_additional_document(int $post_id, int $row_index): ?array
    {
        $rows = self::load_additional_documents_raw($post_id);
        if (empty($rows)) {
            return null;
        }

        $receipt_hash = get_post_meta($post_id, self::TRANSFER_RECEIPT_HASH_META, true);

        foreach (array_values($rows) as $offset => $row) {
            $normalized = self::normalize_additional_document_row(
                $row,
                $offset + 1,
                $post_id,
                ['receipt_hash' => $receipt_hash]
            );
            if (!$normalized) {
                continue;
            }
            if ((int) $normalized['row'] === (int) $row_index) {
                return $normalized;
            }
        }

        return null;
    }

    private static function current_user_can_access_document(array $document)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();
        if (!$current_user instanceof \WP_User) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $user_id = (int) $current_user->ID;
        $user_roles = array_map('sanitize_key', (array) $current_user->roles);

        $allowed_users = array_map('intval', $document['allowed_users'] ?? []);
        if (!empty($allowed_users) && in_array($user_id, $allowed_users, true)) {
            return true;
        }

        $allowed_roles = array_map('sanitize_key', $document['allowed_roles'] ?? []);
        if (empty($allowed_roles)) {
            return true;
        }

        foreach ($allowed_roles as $role) {
            switch ($role) {
                case 'admin':
                    if (current_user_can('manage_options') || in_array('administrator', $user_roles, true) || in_array('admin', $user_roles, true)) {
                        return true;
                    }
                    break;
                case 'cliente':
                    if (in_array('go_cliente', $user_roles, true) || in_array('customer', $user_roles, true)) {
                        return true;
                    }
                    break;
                case 'comercial':
                    if (in_array('go_comercial', $user_roles, true)) {
                        return true;
                    }
                    break;
                case 'director_comercial':
                    if (in_array('go_director_comercial', $user_roles, true)) {
                        return true;
                    }
                    break;
                case 'gestion_garantias':
                    if (in_array('go_garantias', $user_roles, true)) {
                        return true;
                    }
                    break;
                case 'profesional':
                    if (in_array('go_profesional', $user_roles, true) || in_array('profesional', $user_roles, true)) {
                        return true;
                    }
                    break;
                default:
                    if (str_starts_with($role, 'user:')) {
                        $maybe_id = (int) substr($role, 5);
                        if ($maybe_id > 0 && $maybe_id === $user_id) {
                            return true;
                        }
                    }
                    if ($role !== '' && in_array($role, $user_roles, true)) {
                        return true;
                    }
            }
        }

        return false;
    }

    private static function load_additional_documents_raw(int $post_id)
    {
        if (!function_exists('get_field')) {
            return [];
        }

        $rows = get_field(self::ADDITIONAL_DOCS_FIELD, $post_id);
        return is_array($rows) ? $rows : [];
    }

    private static function normalize_additional_document_row($row, int $index, int $post_id, array $context = [])
    {
        if (!is_array($row)) {
            return null;
        }

        $title = isset($row['titulo_documento']) ? sanitize_text_field((string) $row['titulo_documento']) : '';
        $roles_raw = $row['rol_de_usuario'] ?? [];
        $users_raw = $row['permisos_usuario'] ?? [];
        $hash = isset($row['documento_privado_hash']) ? trim((string) $row['documento_privado_hash']) : '';
        $extension = isset($row['documento_privado_extension']) ? strtolower((string) $row['documento_privado_extension']) : '';
        $is_private_flag = !empty($row['documento_privado_es_privado']);
        $file = isset($row['archivo_documento']) && is_array($row['archivo_documento']) ? $row['archivo_documento'] : [];
        $attachment_id = isset($file['ID']) ? (int) $file['ID'] : 0;
        $attachment_url = isset($file['url']) ? trim((string) $file['url']) : '';
        $attachment_filename = isset($file['filename']) ? (string) $file['filename'] : '';
        $attachment_mime = isset($file['mime_type']) ? (string) $file['mime_type'] : '';

        if ($is_private_flag) {
            if ($hash === '') {
                return null;
            }
        } elseif ($attachment_id === 0 && $attachment_url === '') {
            return null;
        }

        if ($extension === '' && $attachment_filename !== '') {
            $derived_ext = strtolower(pathinfo($attachment_filename, PATHINFO_EXTENSION));
            if ($derived_ext !== '') {
                $extension = $derived_ext;
            }
        }

        $roles = self::extract_role_slugs($roles_raw);
        $users = self::extract_user_permissions($users_raw);

        $filename = $attachment_filename !== '' ? $attachment_filename : ($title !== '' ? $title : 'documento-' . $index);
        if ($extension !== '' && !str_contains($filename, '.')) {
            $filename .= '.' . $extension;
        }

        $document = [
            'key'            => 'extra-' . $index,
            'row'            => $index,
            'title'          => $title !== '' ? $title : sprintf(__('Documento %d', 'garantias-online-360vo'), $index),
            'allowed_roles'  => $roles,
            'allowed_users'  => $users,
            'hash'           => $hash,
            'extension'      => $extension,
            'is_private'     => $is_private_flag && $hash !== '',
            'attachment_id'  => $attachment_id,
            'attachment_url' => $attachment_url,
            'attachment_mime'=> $attachment_mime,
            'filename'       => $filename,
            'mime'           => $attachment_mime,
            'source'         => 'repeater',
            'url'            => '',
        ];

        $receipt_hash = isset($context['receipt_hash']) ? (string) $context['receipt_hash'] : '';
        if ($receipt_hash !== '' && $receipt_hash === $hash) {
            $document['kind'] = 'transfer_receipt';
        } elseif ($document['is_private']) {
            $document['kind'] = 'private';
        } else {
            $document['kind'] = 'general';
        }

        return $document;
    }

    private static function extract_role_slugs($raw)
    {
        $roles = [];
        if (is_array($raw)) {
            foreach ($raw as $entry) {
                if (is_array($entry)) {
                    if (isset($entry['value'])) {
                        $value = sanitize_key((string) $entry['value']);
                        if ($value !== '') {
                            $roles[] = $value;
                        }
                    } elseif (isset($entry['role'])) {
                        $value = sanitize_key((string) $entry['role']);
                        if ($value !== '') {
                            $roles[] = $value;
                        }
                    }
                } elseif (is_string($entry)) {
                    $value = sanitize_key($entry);
                    if ($value !== '') {
                        $roles[] = $value;
                    }
                }
            }
        } elseif (is_string($raw)) {
            $value = sanitize_key($raw);
            if ($value !== '') {
                $roles[] = $value;
            }
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (str_starts_with($role, 'user:')) {
                $maybe_id = (int) substr($role, 5);
                if ($maybe_id > 0) {
                    $normalized[] = 'user:' . $maybe_id;
                }
            } elseif ($role !== '') {
                $normalized[] = $role;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function build_document_collection(int $post_id, bool $include_urls = true)
    {
        $documents = [];

        $static_docs = [
            [
                'key'           => 'certificate',
                'title'         => __('Certificado completo', 'garantias-online-360vo'),
                'routeType'     => 'certificado',
                'is_private'    => true,
                'extension'     => 'pdf',
                'allowed_roles' => [],
                'allowed_users' => [],
                'filename'      => '',
                'source'        => 'static',
            ],
            [
                'key'           => 'cobertura',
                'title'         => __('Cobertura', 'garantias-online-360vo'),
                'routeType'     => 'cobertura',
                'is_private'    => false,
                'extension'     => 'pdf',
                'allowed_roles' => [],
                'allowed_users' => [],
                'filename'      => '',
                'source'        => 'static',
            ],
            [
                'key'           => 'condicionado',
                'title'         => __('Condicionado', 'garantias-online-360vo'),
                'routeType'     => 'condicionado',
                'is_private'    => false,
                'extension'     => 'pdf',
                'allowed_roles' => [],
                'allowed_users' => [],
                'filename'      => '',
                'source'        => 'static',
            ],
        ];

        foreach ($static_docs as $doc) {
            $url = $include_urls ? self::build_document_download_url($post_id, $doc['routeType']) : '';
            $documents[] = array_merge($doc, [
                'url'      => $url,
                'mime'     => 'application/pdf',
                'row'      => 0,
                'kind'     => $doc['is_private'] ? 'private' : 'general',
                'hash'     => '',
                'attachment_id' => 0,
                'attachment_url' => '',
            ]);
        }

        $raw_rows = self::load_additional_documents_raw($post_id);
        if (!empty($raw_rows)) {
            $receipt_hash = get_post_meta($post_id, self::TRANSFER_RECEIPT_HASH_META, true);
            foreach (array_values($raw_rows) as $offset => $row) {
                $normalized = self::normalize_additional_document_row(
                    $row,
                    $offset + 1,
                    $post_id,
                    ['receipt_hash' => $receipt_hash]
                );
                if (!$normalized) {
                    continue;
                }
                if ($include_urls) {
                    $normalized['url'] = self::build_additional_document_url($post_id, (int) $normalized['row']);
                } else {
                    $normalized['url'] = '';
                }
                $documents[] = $normalized;
            }
        }

        $filtered = [];
        foreach ($documents as $doc) {
            if ($doc['source'] === 'repeater' && !self::current_user_can_access_document($doc)) {
                continue;
            }
            $filtered[] = $doc;
        }

        return array_values($filtered);
    }

    private static function build_additional_document_url(int $post_id, int $row_index, bool $with_nonce = true)
    {
        if ($post_id <= 0 || $row_index <= 0) {
            return '';
        }

        $url = rest_url(self::NAMESPACE . '/' . self::BASE . '/' . $post_id . '/document/extra/' . $row_index);
        if ($with_nonce) {
            $url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $url);
        }
        $scheme = wp_parse_url(home_url(), PHP_URL_SCHEME);

        return set_url_scheme($url, $scheme);
    }

    private static function inject_document_collection(array $detail, int $post_id, bool $include_urls)
    {
        $documents = self::build_document_collection($post_id, $include_urls);

        $documents = self::maybe_append_sepa_document($documents, $detail);
        $detail['documents'] = $documents;

        $detail['certificate_url'] = '';
        $detail['cobertura_url'] = '';
        $detail['condicionado_url'] = '';

        foreach ($documents as $doc) {
            if (!isset($doc['key'])) {
                continue;
            }
            switch ($doc['key']) {
                case 'certificate':
                    $detail['certificate_url'] = $doc['url'] ?? '';
                    break;
                case 'cobertura':
                    $detail['cobertura_url'] = $doc['url'] ?? '';
                    break;
                case 'condicionado':
                    $detail['condicionado_url'] = $doc['url'] ?? '';
                    break;
            }
        }

        return $detail;
    }

    /**
     * Append the signed SEPA mandate to the document collection for admin users.
     *
     * @param array<int, array<string, mixed>> $documents
     * @param array<string, mixed> $detail
     * @return array<int, array<string, mixed>>
     */
    private static function maybe_append_sepa_document(array $documents, array $detail): array
    {
        $vendor_id = isset($detail['vendor_id']) ? (int) $detail['vendor_id'] : 0;
        if ($vendor_id <= 0 || ! self::current_user_can_view_vendor_sepa_document()) {
            return $documents;
        }

        foreach ($documents as $doc) {
            if (isset($doc['key']) && $doc['key'] === 'sepa-signed') {
                return $documents;
            }
        }

        $meta = SepaMandateService::get_document_meta($vendor_id, SepaMandateService::TYPE_SIGNED);
        if ($meta['hash'] === '') {
            return $documents;
        }

        $download_url = SepaMandateService::build_download_url($vendor_id, SepaMandateService::TYPE_SIGNED);
        if ($download_url === '') {
            return $documents;
        }

        $company_candidates = [];
        if (! empty($detail['vendor_company']) && is_array($detail['vendor_company'])) {
            $company = $detail['vendor_company'];
            $company_candidates[] = (string) ($company['trade_name'] ?? '');
            $company_candidates[] = (string) ($company['name'] ?? '');
            $company_candidates[] = (string) ($company['legal_name'] ?? '');
        }
        $company_candidates[] = (string) ($detail['concesionario'] ?? '');

        $company_name = '';
        foreach ($company_candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && $candidate !== '-') {
                $company_name = $candidate;
                break;
            }
        }

        if ($company_name === '') {
            $company_name = __('profesional', 'garantias-online-360vo');
        }

        $company_name = sanitize_text_field($company_name);

        $title = sprintf(__('Mandato SEPA %s', 'garantias-online-360vo'), $company_name);
        $filename = $meta['filename'] !== '' ? $meta['filename'] : 'mandato-sepa.pdf';

        $documents[] = [
            'key'            => 'sepa-signed',
            'title'          => $title,
            'listLabel'      => $title,
            'downloadLabel'  => __('Descargar mandato SEPA', 'garantias-online-360vo'),
            'routeType'      => '',
            'is_private'     => true,
            'extension'      => 'pdf',
            'allowed_roles'  => ['admin'],
            'allowed_users'  => [],
            'filename'       => $filename,
            'hash'           => $meta['hash'],
            'reference'      => $meta['reference'],
            'generated_at'   => $meta['generated_at'],
            'source'         => 'sepa',
            'mime'           => 'application/pdf',
            'url'            => $download_url,
            'icon'           => 'payment',
            'kind'           => 'sepa_signed',
            'row'            => 0,
        ];

        return $documents;
    }

    private static function current_user_can_view_vendor_sepa_document(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user = wp_get_current_user();
        if (! $current_user instanceof \WP_User) {
            return false;
        }

        $roles = array_map('sanitize_key', (array) $current_user->roles);

        return in_array('admin', $roles, true);
    }

    private static function upsert_transfer_receipt_document(int $post_id, string $title, string $hash, string $extension)
    {
        if (!function_exists('get_field') || !function_exists('update_field')) {
            return;
        }

        $rows = get_field(self::ADDITIONAL_DOCS_FIELD, $post_id);
        if (!is_array($rows)) {
            $rows = [];
        }

        $previous_hash = get_post_meta($post_id, self::TRANSFER_RECEIPT_HASH_META, true);
        $target_index = null;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row_hash = isset($row['documento_privado_hash']) ? (string) $row['documento_privado_hash'] : '';
            if ($previous_hash !== '' && $row_hash === $previous_hash) {
                $target_index = $idx;
                break;
            }
        }

        $row_payload = [
            'titulo_documento'            => $title,
            'archivo_documento'          => null,
            'rol_de_usuario'             => ['admin'],
            'documento_privado_hash'     => $hash,
            'documento_privado_extension'=> $extension,
            'documento_privado_es_privado' => 1,
            'enviar_por_correo_documento'=> 0,
            'permisos_usuario'           => [],
        ];

        if ($target_index !== null) {
            $rows[$target_index] = array_merge(
                is_array($rows[$target_index]) ? $rows[$target_index] : [],
                $row_payload
            );
        } else {
            $rows[] = $row_payload;
            $target_index = count($rows) - 1;
        }

        $rows = array_values($rows);

        $final_index = null;
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $row_hash = isset($row['documento_privado_hash']) ? (string) $row['documento_privado_hash'] : '';
            if ($row_hash !== '' && hash_equals($row_hash, $hash)) {
                $final_index = $idx;
                break;
            }
        }

        if ($final_index === null) {
            $final_index = $target_index !== null ? (int) $target_index : count($rows) - 1;
        }

        update_field(self::ADDITIONAL_DOCS_FIELD, $rows, $post_id);
        update_post_meta($post_id, self::TRANSFER_RECEIPT_HASH_META, $hash);
        update_post_meta($post_id, self::TRANSFER_RECEIPT_EXTENSION_META, $extension);
        update_post_meta($post_id, self::TRANSFER_RECEIPT_ROW_META, $final_index >= 0 ? $final_index + 1 : 0);
    }

    private static function extract_user_permissions($raw)
    {
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $entry) {
                if (is_array($entry) && isset($entry['ID'])) {
                    $ids[] = (int) $entry['ID'];
                } elseif (is_numeric($entry)) {
                    $ids[] = (int) $entry;
                }
            }
        } elseif (is_numeric($raw)) {
            $ids[] = (int) $raw;
        }

        return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    }

    private static function get_plan_info($id)
    {
        $plan = self::resolve_contracted_plan($id);
        $matricula = get_post_meta($id, 'datos_vehiculo_matricula', true);

        return [
            'plan'      => is_string($plan['label']) ? $plan['label'] : '',
            'plan_id'   => (int) ($plan['id'] ?? 0),
            'matricula' => is_string($matricula) ? $matricula : '',
        ];
    }

    private static function resolve_contracted_plan($post_id)
    {
        $raw_plan = get_post_meta($post_id, 'garantia_contratada_garantia', true);
        $plan_id = self::resolve_plan_id($raw_plan);
        $plan_source = $raw_plan;

        if ($plan_id <= 0 && function_exists('get_field')) {
            $contracted = get_field('garantia_contratada', $post_id);
            if (is_array($contracted) && isset($contracted['garantia'])) {
                $plan_source = $contracted['garantia'];
                $plan_id = self::resolve_plan_id($plan_source);
            }
        }

        $plan_label = '';
        if ($plan_id > 0) {
            $custom_plan = function_exists('get_field')
                ? get_field('detalles_modalidad_nombre_mostrar', $plan_id)
                : '';
            $plan_label = $custom_plan ?: get_the_title($plan_id);
        } elseif ($plan_source instanceof \WP_Post) {
            $plan_label = $plan_source->post_title ?? '';
        } elseif (is_array($plan_source) && isset($plan_source['post_title'])) {
            $plan_label = (string) $plan_source['post_title'];
        } elseif (is_string($plan_source)) {
            $plan_label = $plan_source;
        }

        return [
            'id'    => $plan_id,
            'label' => is_string($plan_label) ? trim($plan_label) : '',
        ];
    }

    private static function resolve_plan_id($value)
    {
        if ($value instanceof \WP_Post) {
            return (int) $value->ID;
        }
        if (is_object($value) && isset($value->ID) && is_numeric($value->ID)) {
            return (int) $value->ID;
        }
        if (is_array($value)) {
            if (isset($value['ID']) && is_numeric($value['ID'])) {
                return (int) $value['ID'];
            }
            if (isset($value['id']) && is_numeric($value['id'])) {
                return (int) $value['id'];
            }
            if (isset($value['value']) && is_numeric($value['value'])) {
                return (int) $value['value'];
            }
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
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

        $plan = self::resolve_contracted_plan($id);
        $plan_id = (int) $plan['id'];
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
        return self::inject_document_collection($detail, (int) $id, true);
    }

    public static function serve_document($served, $result, $request, $server)
    {
        if ($result instanceof WP_REST_Response) {
            $headers = $result->get_headers();
            if (isset($headers['X-Go360-Binary']) && $headers['X-Go360-Binary'] === '1') {
                unset($headers['X-Go360-Binary']);
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

    public static function confirm_transfer($request)
    {
        if (! is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('No tienes permisos para realizar esta acción.', 'garantias-online-360vo'),
                ['status' => 401]
            );
        }

        $id = isset($request['id']) ? (int) $request['id'] : 0;
        if ($id <= 0) {
            return new WP_Error(
                'invalid_id',
                __('Identificador de garantía no válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $detail = self::get_detail_data($id, true);
        if (! is_array($detail) || empty($detail)) {
            return new WP_Error(
                'not_found',
                __('No se ha encontrado la garantía solicitada.', 'garantias-online-360vo'),
                ['status' => 404]
            );
        }

        $current_user = wp_get_current_user();
        $user_id      = (int) $current_user->ID;
        $roles        = (array) $current_user->roles;
        $is_admin     = current_user_can('manage_options');
        $is_profesional = in_array('go_profesional', $roles, true) || in_array('profesional', $roles, true);

        if (! $is_profesional && ! $is_admin) {
            return new WP_Error(
                'rest_forbidden_role',
                __('Solo el profesional puede confirmar la transferencia.', 'garantias-online-360vo'),
                ['status' => 403]
            );
        }

        $vendor_id = isset($detail['vendor_id']) ? (int) $detail['vendor_id'] : 0;
        if ($vendor_id > 0 && $user_id !== $vendor_id && ! $is_admin) {
            return new WP_Error(
                'rest_forbidden_owner',
                __('No puedes modificar esta garantía.', 'garantias-online-360vo'),
                ['status' => 403]
            );
        }

        $payment_method = sanitize_key($detail['metodo_pago'] ?? '');
        if ($payment_method !== 'transferencia' && $payment_method !== 'transferencia_bancaria') {
            return new WP_Error(
                'invalid_method',
                __('Solo puedes confirmar transferencias bancarias.', 'garantias-online-360vo'),
                ['status' => 409]
            );
        }

        $current_state = sanitize_key($detail['estado']['value'] ?? '');
        if ($current_state === 'validacion_pendiente') {
            $snapshot = self::collect_detail_snapshot($id, true);
            return new WP_REST_Response(['detail' => $snapshot], 200);
        }

        if ($current_state !== 'pendiente_pago') {
            return new WP_Error(
                'invalid_state',
                __('La garantía no está pendiente de pago.', 'garantias-online-360vo'),
                ['status' => 409]
            );
        }

        $concept = sanitize_text_field((string) $request->get_param('concept'));
        $amount  = sanitize_text_field((string) $request->get_param('amount'));
        $account = sanitize_text_field((string) $request->get_param('account'));

        $file_params = $request->get_file_params();
        $receipt_file = is_array($file_params) && isset($file_params['receipt']) ? $file_params['receipt'] : null;

        if (!is_array($receipt_file)) {
            return new WP_Error('receipt_missing', __('Debes adjuntar el justificante de la transferencia.', 'garantias-online-360vo'), ['status' => 400]);
        }

        if (!empty($receipt_file['error']) && (int) $receipt_file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('receipt_upload_error', __('No se pudo procesar el justificante de la transferencia.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $receipt_size = isset($receipt_file['size']) ? (int) $receipt_file['size'] : 0;
        if ($receipt_size <= 0) {
            return new WP_Error('receipt_empty', __('El justificante recibido está vacío.', 'garantias-online-360vo'), ['status' => 400]);
        }
        if ($receipt_size > self::RECEIPT_MAX_BYTES) {
            return new WP_Error('receipt_too_large', sprintf(
                /* translators: %s: tamaño máximo en MB */
                __('El justificante supera el tamaño máximo permitido (%s MB).', 'garantias-online-360vo'),
                number_format_i18n(self::RECEIPT_MAX_BYTES / 1048576, 0)
            ), ['status' => 413]);
        }

        $tmp_name = isset($receipt_file['tmp_name']) ? (string) $receipt_file['tmp_name'] : '';
        if ($tmp_name === '' || !file_exists($tmp_name)) {
            return new WP_Error('receipt_tmp_missing', __('No se pudo localizar el justificante subido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $original_name = isset($receipt_file['name']) ? (string) $receipt_file['name'] : 'receipt';
        $check = wp_check_filetype_and_ext($tmp_name, $original_name, self::RECEIPT_ALLOWED_MIMES);
        $ext = isset($check['ext']) ? strtolower((string) $check['ext']) : '';
        $type = isset($check['type']) ? strtolower((string) $check['type']) : '';
        if ($ext === '' && $type !== '') {
            foreach (self::RECEIPT_ALLOWED_MIMES as $allowed_ext => $allowed_mime) {
                if ($allowed_mime === $type) {
                    $ext = $allowed_ext;
                    break;
                }
            }
        }
        if ($ext === '') {
            $derived_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if ($derived_ext !== '') {
                $ext = $derived_ext;
            }
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!isset(self::RECEIPT_ALLOWED_MIMES[$ext])) {
            return new WP_Error('receipt_invalid_type', __('Formato de justificante no admitido. Usa PDF, JPG o PNG.', 'garantias-online-360vo'), ['status' => 415]);
        }

        $binary = file_get_contents($tmp_name);
        if (!is_string($binary) || $binary === '') {
            return new WP_Error('receipt_read_error', __('No se pudo leer el justificante adjunto.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $hash = PrivateDocsManager::store($binary, $ext);
        if ($hash === '') {
            return new WP_Error('receipt_store_error', __('No se pudo guardar el justificante en el área privada.', 'garantias-online-360vo'), ['status' => 500]);
        }

        $receipt_title = sprintf(
            __('Justificante %s', 'garantias-online-360vo'),
            isset($detail['matricula']) && $detail['matricula'] !== '' ? $detail['matricula'] : $id
        );
        self::upsert_transfer_receipt_document($id, $receipt_title, $hash, $ext);

        update_post_meta($id, 'estado_garantia_estado_contratacion', 'validacion_pendiente');
        update_post_meta($id, '_go360_transfer_reported_at', current_time('mysql'));
        update_post_meta($id, '_go360_transfer_reported_by', $user_id);

        delete_transient('go_gdetail_' . $id);
        self::clear_list_transients($id, null, true);

        $log_payload = [
            'method'     => 'transferencia',
            'state'      => 'validacion_pendiente',
            'actor_type' => 'vendor',
        ];
        if ($concept !== '') {
            $log_payload['concept'] = $concept;
        }
        if ($amount !== '') {
            $log_payload['amount'] = $amount;
        }
        if ($account !== '') {
            $log_payload['account'] = $account;
        }
        if (! empty($detail['concesionario']) && $detail['concesionario'] !== '-') {
            $log_payload['actor_label'] = sanitize_text_field($detail['concesionario']);
        }

        GuaranteeLogger::log(
            $user_id,
            $id,
            'transfer_reported',
            wp_json_encode($log_payload)
        );

        $data_factory   = new GuaranteeEmailDataFactory();
        $guarantee_data = $data_factory->build($id);

        $vendor_label = $detail['concesionario'] ?? '';
        if ($vendor_label === '' && isset($guarantee_data['vendor']['company_name'])) {
            $vendor_label = (string) $guarantee_data['vendor']['company_name'];
        }
        if ($vendor_label === '' && isset($guarantee_data['vendor']['name'])) {
            $vendor_label = (string) $guarantee_data['vendor']['name'];
        }
        $vendor_label = $vendor_label !== ''
            ? sanitize_text_field($vendor_label)
            : __('el cliente', 'garantias-online-360vo');

        $plate = isset($guarantee_data['plate'])
            ? sanitize_text_field($guarantee_data['plate'])
            : '';
        $plate_label = $plate !== '' ? $plate : sprintf('#%d', $id);

        $transfer_data    = isset($guarantee_data['transfer']) && is_array($guarantee_data['transfer'])
            ? $guarantee_data['transfer']
            : [];
        $transfer_concept = $concept !== ''
            ? $concept
            : sanitize_text_field($transfer_data['concept'] ?? '');
        $transfer_amount  = $amount !== ''
            ? $amount
            : sanitize_text_field($transfer_data['amount'] ?? '');
        $transfer_account = $account !== ''
            ? $account
            : sanitize_text_field($transfer_data['iban'] ?? '');

        $transfer_concept = $transfer_concept !== '' ? sanitize_text_field($transfer_concept) : '';
        $transfer_amount  = $transfer_amount !== '' ? sanitize_text_field($transfer_amount) : '';
        $transfer_account = $transfer_account !== '' ? sanitize_text_field($transfer_account) : '';

        $permalink = isset($guarantee_data['permalink'])
            ? esc_url_raw($guarantee_data['permalink'])
            : '';
        if ($permalink === '') {
            $permalink = home_url('/garantias-online/mis-garantias/');
            if ($plate !== '') {
                $permalink = add_query_arg('matricula', rawurlencode($plate), $permalink);
            }
        }

        $delivery = EmailNotificationService::resolve_admin_delivery(
            $id,
            [
                'event'     => 'transfer_reported',
                'initiator' => $user_id,
            ]
        );

        $recipients = $delivery['to'] ?? [];
        $bcc        = $delivery['bcc'] ?? [];

        if (! empty($recipients) || ! empty($bcc)) {
            $subject = sprintf(
                /* translators: %s: vehicle plate */
                __('Transferencia confirmada · Garantía %s', 'garantias-online-360vo'),
                $plate_label
            );

            $renderer = new TemplateRenderer();
            $body = $renderer->render(
                'transfer-reported-admin',
                [
                    'vendor_name' => $vendor_label,
                    'plate_label' => $plate_label,
                    'permalink'   => $permalink,
                    'transfer'    => [
                        'amount'  => $transfer_amount,
                        'account' => $transfer_account,
                        'concept' => $transfer_concept,
                    ],
                ]
            );

            if ($body === '') {
                ob_start();
                ?>
                <p>
                    <?php
                    printf(
                        wp_kses(
                            /* translators: %s: customer name */
                            __('El cliente <strong>%s</strong> ha indicado que ha realizado la transferencia.', 'garantias-online-360vo'),
                            ['strong' => []]
                        ),
                        esc_html($vendor_label)
                    );
                    ?>
                </p>
                <?php if ($transfer_amount !== '' || $transfer_account !== '' || $transfer_concept !== '') : ?>
                    <ul>
                        <?php if ($transfer_amount !== '') : ?>
                            <li><strong><?php esc_html_e('Importe:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($transfer_amount); ?></li>
                        <?php endif; ?>
                        <?php if ($transfer_account !== '') : ?>
                            <li><strong><?php esc_html_e('Cuenta:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($transfer_account); ?></li>
                        <?php endif; ?>
                        <?php if ($transfer_concept !== '') : ?>
                            <li><strong><?php esc_html_e('Concepto:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($transfer_concept); ?></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <p><?php esc_html_e('Revisa la operación y accede a la garantía para activarla.', 'garantias-online-360vo'); ?></p>
                <?php if ($permalink !== '') : ?>
                    <p><a href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Abrir garantía', 'garantias-online-360vo'); ?></a></p>
                <?php endif; ?>
                <?php
                $body = trim((string) ob_get_clean());
            }

            $headers  = ['Content-Type: text/html; charset=UTF-8'];
            $sender_email = sanitize_email(
                apply_filters('go360/email/sender_email', get_option('admin_email'), 'admin', [])
            );
            if ($sender_email !== '') {
                $headers[] = sprintf(
                    'From: %s <%s>',
                    __('Garantías 360VO', 'garantias-online-360vo'),
                    $sender_email
                );
            }
            $metadata = [];
            if (! empty($bcc)) {
                $metadata['bcc'] = $bcc;
            }

            $message = new EmailMessage($recipients, $subject, $body, $headers, [], $metadata);
            $mailer  = new Mailer();
            $sent    = $mailer->send($message);

            $log_details = sprintf(
                'transfer_reported|to:%s',
                implode(',', $message->get_recipients())
            );
            if (! empty($metadata['bcc'])) {
                $log_details .= '|bcc:' . implode(',', $metadata['bcc']);
            }

            GuaranteeLogger::log(
                $user_id,
                $id,
                $sent ? 'email_sent' : 'email_failed',
                $log_details
            );
        }

        $snapshot = self::collect_detail_snapshot($id, true);

        return new WP_REST_Response(['detail' => $snapshot], 200);
    }

    public static function trash_item($request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('No tienes permisos para realizar esta acción.', 'garantias-online-360vo'),
                ['status' => 401]
            );
        }

        $post_id = isset($request['id']) ? (int) $request['id'] : 0;
        if ($post_id <= 0) {
            return new WP_Error(
                'invalid_id',
                __('Identificador de garantía no válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
            return new WP_Error(
                'not_found',
                __('No se ha encontrado la garantía solicitada.', 'garantias-online-360vo'),
                ['status' => 404]
            );
        }

        if ($post->post_status === 'trash') {
            return new WP_REST_Response([
                'success' => true,
                'id'      => $post_id,
                'status'  => 'trash',
            ], 200);
        }

        $trashed = wp_trash_post($post_id);
        if ($trashed === false || is_wp_error($trashed)) {
            $error_message = $trashed instanceof WP_Error
                ? $trashed->get_error_message()
                : __('No se pudo enviar la garantía a la papelera.', 'garantias-online-360vo');
            return new WP_Error('trash_failed', $error_message, ['status' => 500]);
        }

        delete_transient('go_gdetail_' . $post_id);
        self::clear_list_transients($post_id, null, true);

        return new WP_REST_Response([
            'success' => true,
            'id'      => $post_id,
            'status'  => 'trash',
        ], 200);
    }

    public static function can_list($request)
    {
        return is_user_logged_in();
    }

    public static function can_view_summary()
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user = wp_get_current_user();
        if (! $current_user instanceof \WP_User) {
            return false;
        }

        $roles = (array) $current_user->roles;

        if (in_array('go_director_comercial', $roles, true)) {
            return true;
        }

        if (in_array('go_profesional', $roles, true) || in_array('profesional', $roles, true)) {
            return true;
        }

        return false;
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
            in_array('go_comercial', (array) $current_user->roles, true) ||
            in_array('go_director_comercial', (array) $current_user->roles, true)
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

    public static function can_trash($request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user = wp_get_current_user();
        if (! $current_user instanceof \WP_User) {
            return false;
        }

        $roles = (array) $current_user->roles;

        if (in_array('admin', $roles, true)) {
            return true;
        }

        if (in_array('go_garantias', $roles, true)) {
            return true;
        }

        if (in_array('go_director_comercial', $roles, true)) {
            return true;
        }

        return false;
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
        $current_roles = (array) $current_user->roles;
        $normalized_roles = array_map('strtolower', $current_roles);
        $is_professional_role = in_array('go_profesional', $normalized_roles, true)
            || in_array('profesional', $normalized_roles, true);
        $is_particular_role = self::roles_include_particular($normalized_roles);

        if ($is_professional_role || $is_particular_role) {
            if (!isset($data['garantia_contratada']) || !is_array($data['garantia_contratada'])) {
                $data['garantia_contratada'] = [];
            }
            if (!isset($data['garantia_contratada']['canal_venta'])) {
                $data['garantia_contratada']['canal_venta'] = $is_particular_role ? 'particular' : 'profesional';
            } elseif ($is_particular_role) {
                $data['garantia_contratada']['canal_venta'] = 'particular';
            }
            $provided_vendor = 0;
            if (isset($data['garantia_contratada']['concesionario_empresa_profesional'])) {
                $provided_vendor = self::normalize_vendor_meta(
                    $data['garantia_contratada']['concesionario_empresa_profesional']
                );
            }
            if ($provided_vendor <= 0) {
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
        $acf_gc_payload = function_exists('get_field')
            ? get_field('garantia_contratada', $post_id)
            : [];
        if (!is_array($acf_gc_payload)) {
            $acf_gc_payload = [];
        }
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
                        $gc[$k] = sanitize_text_field($v);
                        $payment_method = $gc[$k];
                        break;
                    case 'canal_venta':
                        $normalized_channel = self::normalize_channel_slug((string) $v);
                        if ($normalized_channel === '') {
                            $normalized_channel = sanitize_text_field($v);
                        }

                        $gc[$k] = $normalized_channel;
                        $gc['canal_venta_value'] = $normalized_channel;

                        $label_source = $normalized_channel !== ''
                            ? $normalized_channel
                            : (string) $v;
                        $channel_label = self::normalize_channel_label_text($label_source);
                        if ($channel_label === '' && $normalized_channel !== '') {
                            $channel_label = ucfirst($normalized_channel);
                        }
                        if ($channel_label === '' && is_string($v)) {
                            $channel_label = sanitize_text_field($v);
                        }
                        if ($channel_label !== '') {
                            $gc['canal_venta_label'] = $channel_label;
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
                            error_log('[AUTOSAVE] incoming descuentos_y_recargos: ' . wp_json_encode($v));
                            $dr = [];
                            if (isset($v['precio_base'])) {
                                $base               = self::normalize_decimal($v['precio_base']);
                                $dr['precio_base'] = is_numeric($base) ? $base : '';
                            }
                            if (!empty($v['listado_descuentos_recargos']) && is_array($v['listado_descuentos_recargos'])) {
                                $list = [];
                                foreach ($v['listado_descuentos_recargos'] as $row) {
                                    $concepto = sanitize_text_field($row['concepto'] ?? '');
                                    $valor     = self::normalize_decimal(
                                        $row['valor'] ?? ($row['importe'] ?? '')
                                    );
                                    $destacado = isset($row['destacado']) && $row['destacado'] ? 1 : 0;
                                    $list[] = [
                                        'concepto'  => $concepto,
                                        'valor'     => is_numeric($valor) ? $valor : '',
                                        'destacado' => $destacado,
                                    ];
                                }
                                if ($list) {
                                    $dr['listado_descuentos_recargos'] = array_values($list);
                                }
                            }
                            error_log('[AUTOSAVE] normalized descuentos_y_recargos: ' . wp_json_encode($dr));
                            $gc['descuentos_y_recargos'] = $dr;
                        }
                        break;
                    default:
                        $gc[$k] = sanitize_text_field($v);
                        break;
                }
            }

            foreach ($gc as $key => $value) {
                $acf_gc_payload[$key] = $value;
            }

            $preferred_channel_slug = '';
            if ($is_particular_role) {
                $preferred_channel_slug = 'particular';
            } elseif ($post_id > 0) {
                $existing_channel_slug = self::normalize_channel_meta(
                    get_post_meta($post_id, 'garantia_contratada_canal_venta', true)
                );
                if ($existing_channel_slug === 'particular') {
                    $author_id = (int) get_post_field('post_author', $post_id);
                    if ($author_id > 0 && self::user_id_is_particular($author_id)) {
                        $preferred_channel_slug = 'particular';
                    }
                }
            }

            if ($preferred_channel_slug === 'particular') {
                $gc['canal_venta'] = 'particular';
                $gc['canal_venta_value'] = 'particular';
                if (empty($gc['canal_venta_label'])) {
                    $gc['canal_venta_label'] = self::normalize_channel_label_text('particular');
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
            if (
                !isset($gc['concesionario_empresa_profesional']) ||
                (int) $gc['concesionario_empresa_profesional'] <= 0
            ) {
                $existing_vendor = 0;
                if ($post_id > 0) {
                    $existing_vendor = self::normalize_vendor_meta(
                        get_post_meta(
                            $post_id,
                            'garantia_contratada_concesionario_empresa_profesional',
                            true
                        )
                    );
                    if ($existing_vendor <= 0) {
                        $author_id = (int) get_post_field('post_author', $post_id);
                        if ($author_id > 0) {
                            $author = get_user_by('id', $author_id);
                            if (self::user_is_professional($author)) {
                                $existing_vendor = $author_id;
                            }
                        }
                    }
                }
                if ($existing_vendor > 0) {
                    $gc['concesionario_empresa_profesional'] = $existing_vendor;
                }
            }

            foreach ($gc as $key => $value) {
                $acf_gc_payload[$key] = $value;
            }

            if (!empty($acf_gc_payload)) {
                if (function_exists('update_field')) {
                    update_field('garantia_contratada', $acf_gc_payload, $post_id);

                    if (!empty($gc['descuentos_y_recargos'])) {
                        update_field(
                            'garantia_contratada_descuentos_y_recargos',
                            $gc['descuentos_y_recargos'],
                            $post_id
                        );
                        if (!empty($gc['descuentos_y_recargos']['listado_descuentos_recargos'])) {
                            update_field(
                                'garantia_contratada_descuentos_y_recargos_listado_descuentos_recargos',
                                $gc['descuentos_y_recargos']['listado_descuentos_recargos'],
                                $post_id
                            );
                        }
                    }
                } else {
                    update_post_meta($post_id, 'garantia_contratada', $acf_gc_payload);
                }
            }

            foreach ($gc as $k => $v) {
                if ($k === 'descuentos_y_recargos' && is_array($v)) {
                    continue;
                }
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
                $valid = ['pendiente_pago', 'validacion_pendiente', 'sin_finalizar', 'activada', 'expirada', 'expira_pronto'];
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

        $vendor_meta = $post_id
            ? get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true)
            : 0;
        $vendor_id = $vendor_meta;
        $context_vendor_id = self::normalize_vendor_meta($vendor_meta);

        $context_payment_method = $payment_method;
        if ($context_payment_method === '' && $post_id) {
            $context_payment_method = (string) get_post_meta($post_id, 'garantia_contratada_metodo_pago', true);
        }

        $should_stamp_contract_date = in_array($new_contract_state, ['activada', 'pendiente_pago'], true);

        if (
            $should_stamp_contract_date
            && $new_contract_state !== $previous_contract_state
        ) {
            $queued_contract_notice  = true;
            $contract_notice_context = [
                'initiator'      => get_current_user_id(),
                'previous_state' => $previous_contract_state,
                'current_state'  => $new_contract_state,
                'vendor_id'      => $context_vendor_id,
                'payment_method' => sanitize_key($context_payment_method),
            ];
            error_log(sprintf(
                '[AUTOSAVE] Contract state changed from %s to %s for ID %d',
                $previous_contract_state !== '' ? $previous_contract_state : '(none)',
                $new_contract_state,
                $post_id
            ));
        }

        if ($should_stamp_contract_date && $post_id) {
            $fecha_contratacion = get_post_meta($post_id, 'estado_garantia_fecha_contratacion', true);
            $fecha_contratacion = is_string($fecha_contratacion) ? trim($fecha_contratacion) : '';
            if ($fecha_contratacion === '') {
                $current_contract_date = current_time('d/m/Y');
                update_post_meta($post_id, 'estado_garantia_fecha_contratacion', $current_contract_date);
            }
        }

        if (
            $queued_contract_notice
            && $new_contract_state === 'activada'
            && $post_id
            && ! $pending_payment_event
        ) {
            $method_for_payment = $context_payment_method !== ''
                ? $context_payment_method
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
                : ($context_payment_method !== ''
                    ? $context_payment_method
                    : (string) get_post_meta($post_id, 'garantia_contratada_metodo_pago', true));
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

        if ($post_id > 0) {
            self::invalidate_guarantee_list_cache($post_id);
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

    private static function build_admin_summary(): array
    {
        global $wpdb;

        $post_type = \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE;
        $statuses  = self::get_summary_post_statuses();

        list($status_clause, $status_params) = self::build_in_clause($statuses);

        $total_sql = "
            SELECT COUNT(1)
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
              AND p.post_status IN ($status_clause)
        ";
        $total_params = array_merge([$post_type], $status_params);
        $total = (int) $wpdb->get_var($wpdb->prepare($total_sql, $total_params));

        $states = self::collect_state_distribution($statuses);
        $pending_draft = ['count' => 0, 'amount' => 0.0];
        foreach ($states as $state_entry) {
            if (! is_array($state_entry)) {
                continue;
            }

            $state_value = isset($state_entry['value']) ? (string) $state_entry['value'] : '';
            if ($state_value !== 'sin_finalizar') {
                continue;
            }

            $pending_draft['count'] = isset($state_entry['count']) ? (int) $state_entry['count'] : 0;
            break;
        }
        $pending_collect = self::sum_prices_for_states(['pendiente_cobro'], $statuses);
        $pending_payment = self::sum_prices_for_states(['pendiente_pago'], $statuses);
        $pending_validation = self::sum_prices_for_states(['validacion_pendiente'], $statuses);
        $month = self::collect_month_summary($statuses);
        $year = self::collect_year_summary($statuses);

        return [
            'totals' => [
                'count' => $total,
            ],
            'states' => $states,
            'contexts' => [
                'year'  => $year,
                'month' => $month,
            ],
            'pending' => [
                'draft'     => $pending_draft,
                'collect'    => $pending_collect,
                'payment'    => $pending_payment,
                'validation' => $pending_validation,
            ],
            'month'      => $month,
            'currency'   => 'EUR',
            'updated_at' => current_time('mysql'),
        ];
    }

    private static function normalize_admin_summary(array $summary, ?bool &$has_draft_state = null): array
    {
        $has_draft_state = false;

        $states = isset($summary['states']) && is_array($summary['states'])
            ? array_values($summary['states'])
            : [];

        foreach ($states as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = isset($entry['value']) ? (string) $entry['value'] : '';
            if ($value !== 'sin_finalizar') {
                continue;
            }

            $has_draft_state = true;
            $count = isset($entry['count']) ? (int) $entry['count'] : 0;
            $label = isset($entry['label']) ? (string) $entry['label'] : '';

            $states[$index] = [
                'value' => 'sin_finalizar',
                'label' => $label !== '' ? $label : __('Sin finalizar', 'garantias-online-360vo'),
                'count' => $count,
            ];
        }

        if (! $has_draft_state) {
            $states[] = [
                'value' => 'sin_finalizar',
                'label' => __('Sin finalizar', 'garantias-online-360vo'),
                'count' => 0,
            ];
        }

        $summary['states'] = $states;

        $draft_count = 0;
        foreach ($states as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['value'] ?? '') === 'sin_finalizar') {
                $draft_count = isset($entry['count']) ? (int) $entry['count'] : 0;
                break;
            }
        }

        if (! isset($summary['pending']) || ! is_array($summary['pending'])) {
            $summary['pending'] = [];
        }

        if (! isset($summary['pending']['draft']) || ! is_array($summary['pending']['draft'])) {
            $summary['pending']['draft'] = [
                'count'  => $draft_count,
                'amount' => 0.0,
            ];
        } else {
            if (! isset($summary['pending']['draft']['count'])) {
                $summary['pending']['draft']['count'] = $draft_count;
            } else {
                $summary['pending']['draft']['count'] = (int) $summary['pending']['draft']['count'];
            }

            if (! isset($summary['pending']['draft']['amount'])) {
                $summary['pending']['draft']['amount'] = 0.0;
            } else {
                $summary['pending']['draft']['amount'] = (float) $summary['pending']['draft']['amount'];
            }
        }

        return $summary;
    }

    private static function collect_year_summary(array $statuses): array
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $timezone);
        $year_start = $now->modify('first day of January this year')->setTime(0, 0, 0);
        $year_end = $now->setTime(23, 59, 59);
        if ($year_end < $year_start) {
            $year_end = $year_start->setTime(23, 59, 59);
        }

        $current = self::summarize_period($statuses, $year_start, $year_end);

        $previous_start = $year_start->modify('-1 year');
        $previous_end = $now->modify('-1 year')->setTime(23, 59, 59);
        if ($previous_end < $previous_start) {
            $previous_end = $previous_start->setTime(23, 59, 59);
        }
        $previous = self::summarize_period($statuses, $previous_start, $previous_end);

        $trends = [
            'amount' => self::build_summary_trend(
                $current['amount_map']['activada'] ?? 0.0,
                $previous['amount_map']['activada'] ?? 0.0,
                __('vs año ant.', 'garantias-online-360vo')
            ),
            'count'  => self::build_summary_trend(
                $current['count'] ?? 0,
                $previous['count'] ?? 0,
                __('vs año ant.', 'garantias-online-360vo')
            ),
        ];

        unset($current['amount_map'], $previous['amount_map']);

        $current['label'] = $year_start->format('Y');
        $current['trends'] = $trends;

        return $current;
    }

    private static function get_summary_state_groups(): array
    {
        return [
            'activada' => [
                'states' => ['activada'],
                'label'  => __('Activadas', 'garantias-online-360vo'),
            ],
            'pendiente_pago' => [
                'states' => ['pendiente_pago'],
                'label'  => __('Pend. Pago', 'garantias-online-360vo'),
            ],
            'pendiente_revision' => [
                'states' => ['validacion_pendiente', 'pendiente_cobro'],
                'label'  => __('Verificar/cobrar', 'garantias-online-360vo'),
            ],
            'sin_finalizar' => [
                'states' => ['sin_finalizar'],
                'label'  => __('Sin finalizar', 'garantias-online-360vo'),
            ],
        ];
    }

    private static function aggregate_summary_states(array $state_counts): array
    {
        $groups = self::get_summary_state_groups();
        $summary = [];

        foreach ($groups as $value => $group) {
            $states = isset($group['states']) && is_array($group['states']) ? $group['states'] : [];
            $label = isset($group['label']) ? (string) $group['label'] : self::humanize_state($value);
            $count = 0;

            foreach ($states as $state_key) {
                if ($state_key === '') {
                    continue;
                }
                $count += isset($state_counts[$state_key]) ? (int) $state_counts[$state_key] : 0;
            }

            $summary[] = [
                'value' => $value,
                'label' => $label,
                'count' => (int) $count,
            ];
        }

        return $summary;
    }

    private static function get_summary_post_statuses(): array
    {
        return ['publish', 'pending', 'future', 'draft'];
    }

    private static function collect_state_distribution(array $statuses): array
    {
        global $wpdb;

        $post_type = \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE;
        list($status_clause, $status_params) = self::build_in_clause($statuses);

        $sql = "
            SELECT state.meta_value AS state, COUNT(DISTINCT p.ID) AS total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} state
                ON state.post_id = p.ID
                AND state.meta_key = 'estado_garantia_estado_contratacion'
            WHERE p.post_type = %s
              AND p.post_status IN ($status_clause)
            GROUP BY state.meta_value
        ";

        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge([$post_type], $status_params)), ARRAY_A);
        $state_counts = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $state = isset($row['state']) ? (string) $row['state'] : '';
                $count = isset($row['total']) ? (int) $row['total'] : 0;
                if ($state === '' || $count <= 0) {
                    continue;
                }

                if (! isset($state_counts[$state])) {
                    $state_counts[$state] = 0;
                }
                $state_counts[$state] += $count;
            }
        }

        $pending_collect = self::collect_pending_direct_debit_summary($statuses);
        if ($pending_collect['count'] > 0) {
            $state_counts['pendiente_cobro'] = ($state_counts['pendiente_cobro'] ?? 0) + $pending_collect['count'];
            if (isset($state_counts['activada'])) {
                $state_counts['activada'] = max(0, $state_counts['activada'] - $pending_collect['count']);
            }
        }

        return self::aggregate_summary_states($state_counts);
    }

    private static function collect_pending_direct_debit_summary(array $statuses, array $options = []): array
    {
        global $wpdb;

        $post_type = \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE;
        list($status_clause, $status_params) = self::build_in_clause($statuses);

        $date_clause = '';
        $date_params = [];

        if (! empty($options['start_date'])) {
            $date_clause .= ' AND p.post_date >= %s';
            $date_params[] = $options['start_date'];
        }

        if (! empty($options['end_date'])) {
            $date_clause .= ' AND p.post_date <= %s';
            $date_params[] = $options['end_date'];
        }

        list($payment_clause, $payment_params) = self::build_like_clause('payment.meta_value', self::get_direct_debit_payment_patterns());

        if ($payment_clause === '') {
            return ['count' => 0, 'amount' => 0.0];
        }

        $sql = "
            SELECT COALESCE(MAX(price.meta_value), '') AS price
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} state
                ON state.post_id = p.ID
                AND state.meta_key = 'estado_garantia_estado_contratacion'
            INNER JOIN {$wpdb->postmeta} payment
                ON payment.post_id = p.ID
                AND payment.meta_key = 'garantia_contratada_metodo_pago'
            LEFT JOIN {$wpdb->postmeta} collected
                ON collected.post_id = p.ID
                AND collected.meta_key = 'garantia_contratada_estado_cobro_cobro_realizado'
            LEFT JOIN {$wpdb->postmeta} price
                ON price.post_id = p.ID
                AND price.meta_key = 'garantia_contratada_precio'
            WHERE p.post_type = %s
              AND p.post_status IN ($status_clause)
              AND state.meta_value = 'activada'
              AND {$payment_clause}
              AND (
                    collected.post_id IS NULL
                    OR collected.meta_value = ''
                    OR CAST(collected.meta_value AS UNSIGNED) = 0
                )
              {$date_clause}
            GROUP BY p.ID
        ";

        $params = array_merge([$post_type], $status_params, $payment_params, $date_params);
        $values = $wpdb->get_col($wpdb->prepare($sql, $params));

        $count = is_array($values) ? count($values) : 0;
        $amount = self::sum_price_values($values);

        return [
            'count'  => $count,
            'amount' => $amount,
        ];
    }

    private static function sum_prices_for_states(array $states, array $statuses): array
    {
        global $wpdb;

        if (empty($states)) {
            return ['amount' => 0.0, 'count' => 0];
        }

        $normalized_states = array_values(array_unique(array_filter(array_map('strval', $states))));
        $include_pending_collect = in_array('pendiente_cobro', $normalized_states, true);
        $normalized_states = array_values(array_filter(
            $normalized_states,
            static function ($state) {
                return $state !== 'pendiente_cobro';
            }
        ));

        $amount = 0.0;
        $count = 0;

        if (! empty($normalized_states)) {
            $post_type = \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE;
            list($status_clause, $status_params) = self::build_in_clause($statuses);
            list($state_clause, $state_params) = self::build_in_clause($normalized_states);

            $sql = "
                SELECT price.meta_value AS price
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} state
                    ON state.post_id = p.ID
                    AND state.meta_key = 'estado_garantia_estado_contratacion'
                INNER JOIN {$wpdb->postmeta} price
                    ON price.post_id = p.ID
                    AND price.meta_key = 'garantia_contratada_precio'
                WHERE p.post_type = %s
                  AND p.post_status IN ($status_clause)
                  AND state.meta_value IN ($state_clause)
            ";

            $params = array_merge([$post_type], $status_params, $state_params);
            $values = $wpdb->get_col($wpdb->prepare($sql, $params));

            $count += is_array($values) ? count($values) : 0;
            $amount += self::sum_price_values($values);
        }

        if ($include_pending_collect) {
            $pending = self::collect_pending_direct_debit_summary($statuses);
            $count += $pending['count'];
            $amount += $pending['amount'];
        }

        return [
            'amount' => $amount,
            'count'  => $count,
        ];
    }

    private static function collect_month_summary(array $statuses): array
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $timezone);
        $month_start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $month_end = $now->setTime(23, 59, 59);
        if ($month_end < $month_start) {
            $month_end = $month_start->setTime(23, 59, 59);
        }

        $current = self::summarize_period($statuses, $month_start, $month_end);

        $previous_start = $month_start->modify('-1 month');
        $previous_end = $now->modify('-1 month')->setTime(23, 59, 59);
        if ($previous_end < $previous_start) {
            $previous_end = $previous_start->setTime(23, 59, 59);
        }
        $previous = self::summarize_period($statuses, $previous_start, $previous_end);

        $trends = [
            'amount' => self::build_summary_trend(
                $current['amount_map']['activada'] ?? 0.0,
                $previous['amount_map']['activada'] ?? 0.0,
                __('vs mes ant.', 'garantias-online-360vo')
            ),
            'count'  => self::build_summary_trend(
                $current['count'] ?? 0,
                $previous['count'] ?? 0,
                __('vs mes ant.', 'garantias-online-360vo')
            ),
        ];

        unset($current['amount_map'], $previous['amount_map']);

        $current['label'] = self::format_month_label($month_start);
        $current['month_name'] = self::format_month_name($month_start);
        $current['trends'] = $trends;

        return $current;
    }

    private static function summarize_period(array $statuses, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;

        if ($end < $start) {
            $end = $start;
        }

        $post_type = \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE;
        list($status_clause, $status_params) = self::build_in_clause($statuses);

        $sql = "
            SELECT state.meta_value AS state, price.meta_value AS price
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} state
                ON state.post_id = p.ID
                AND state.meta_key = 'estado_garantia_estado_contratacion'
            LEFT JOIN {$wpdb->postmeta} price
                ON price.post_id = p.ID
                AND price.meta_key = 'garantia_contratada_precio'
            WHERE p.post_type = %s
              AND p.post_status IN ($status_clause)
              AND p.post_date >= %s
              AND p.post_date <= %s
        ";

        $params = array_merge(
            [$post_type],
            $status_params,
            [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]
        );

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $count = 0;
        $amount_values = [];
        $state_counts = [];
        $state_amounts = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $count++;
                $normalized_price = self::normalize_price_amount($row['price'] ?? '');
                $amount_values[] = $normalized_price;
                $state = isset($row['state']) ? (string) $row['state'] : '';
                if ($state === '') {
                    continue;
                }
                if (! isset($state_counts[$state])) {
                    $state_counts[$state] = 0;
                }
                $state_counts[$state]++;
                if (! isset($state_amounts[$state])) {
                    $state_amounts[$state] = 0.0;
                }
                $state_amounts[$state] += $normalized_price;
            }
        }

        $pending_collect = self::collect_pending_direct_debit_summary($statuses, [
            'start_date' => $start->format('Y-m-d H:i:s'),
            'end_date'   => $end->format('Y-m-d H:i:s'),
        ]);
        if ($pending_collect['count'] > 0) {
            $state_counts['pendiente_cobro'] = ($state_counts['pendiente_cobro'] ?? 0) + $pending_collect['count'];
            if (isset($state_counts['activada'])) {
                $state_counts['activada'] = max(0, $state_counts['activada'] - $pending_collect['count']);
            }
            $state_amounts['pendiente_cobro'] = ($state_amounts['pendiente_cobro'] ?? 0.0) + $pending_collect['amount'];
            if (isset($state_amounts['activada'])) {
                $state_amounts['activada'] = max(0.0, $state_amounts['activada'] - $pending_collect['amount']);
            }
        }

        $amount = self::sum_price_values($amount_values);
        $states = self::aggregate_summary_states($state_counts);
        $amounts = self::aggregate_summary_state_amounts($state_amounts);

        $amount_map = [];
        foreach ($amounts as $entry) {
            $value = isset($entry['value']) ? (string) $entry['value'] : '';
            if ($value === '') {
                continue;
            }
            $amount_map[$value] = isset($entry['amount']) ? (float) $entry['amount'] : 0.0;
        }

        $top = null;
        foreach ($states as $entry) {
            $entry_count = isset($entry['count']) ? (int) $entry['count'] : 0;
            if ($top === null || $entry_count > (int) ($top['count'] ?? 0)) {
                $top = $entry;
            }
        }

        return [
            'count'      => (int) $count,
            'amount'     => $amount,
            'states'     => $states,
            'amounts'    => $amounts,
            'top_state'  => $top,
            'amount_map' => $amount_map,
        ];
    }

    private static function build_summary_trend($current, $previous, string $label): array
    {
        $current_value = is_numeric($current) ? (float) $current : 0.0;
        $previous_value = is_numeric($previous) ? (float) $previous : 0.0;

        $percentage = 0.0;
        if ($previous_value > 0.0) {
            $percentage = (($current_value - $previous_value) / $previous_value) * 100.0;
        } elseif ($current_value > 0.0) {
            $percentage = 100.0;
        }

        $percentage = round($percentage, 1);
        if (abs($percentage) < 0.05) {
            $percentage = 0.0;
        }

        $direction = 'neutral';
        if ($percentage > 0.0) {
            $direction = 'positive';
        } elseif ($percentage < 0.0) {
            $direction = 'negative';
        }

        return [
            'current'    => $current_value,
            'previous'   => $previous_value,
            'percentage' => $percentage,
            'formatted'  => self::format_trend_percentage($percentage),
            'direction'  => $direction,
            'label'      => $label,
        ];
    }

    private static function format_trend_percentage(float $percentage): string
    {
        $decimals = abs($percentage - (int) $percentage) < 0.05 ? 0 : 1;
        $formatted = number_format($percentage, $decimals, '.', '');
        return $formatted . '%';
    }

    private static function aggregate_summary_state_amounts(array $state_amounts): array
    {
        $groups = self::get_summary_state_groups();
        $summary = [];

        foreach ($groups as $value => $group) {
            $states = isset($group['states']) && is_array($group['states']) ? $group['states'] : [];
            $amount = 0.0;

            foreach ($states as $state_key) {
                if ($state_key === '') {
                    continue;
                }
                $amount += isset($state_amounts[$state_key]) ? (float) $state_amounts[$state_key] : 0.0;
            }

            $summary[] = [
                'value'  => $value,
                'amount' => round($amount, 2),
            ];
        }

        return $summary;
    }

    private static function sum_price_values($values): float
    {
        if (! is_array($values) || empty($values)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($values as $value) {
            $total += self::normalize_price_amount($value);
        }

        return round($total, 2);
    }

    private static function normalize_price_amount($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            $value = (string) $value;
        }

        $clean = preg_replace('/[^0-9,.-]/', '', $value);
        if (! is_string($clean) || $clean === '' || $clean === '-' || $clean === '.' || $clean === ',') {
            return 0.0;
        }

        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if (! is_numeric($clean)) {
            return 0.0;
        }

        return (float) $clean;
    }

    private static function get_direct_debit_payment_slugs(): array
    {
        static $slugs = null;

        if ($slugs === null) {
            $candidates = ['domiciliacion', 'domiciliacion_bancaria', 'domiciliacion-bancaria'];
            $sanitized = array_map('sanitize_key', $candidates);
            $slugs = array_values(array_unique(array_filter($sanitized, static function ($slug) {
                return $slug !== '';
            })));
        }

        return $slugs;
    }

    private static function get_direct_debit_payment_patterns(): array
    {
        static $patterns = null;

        if ($patterns === null) {
            $slugs = self::get_direct_debit_payment_slugs();
            $extras = ['domiciliacion', 'domiciliación'];
            $patterns = array_values(array_unique(array_filter(array_merge($slugs, $extras), static function ($pattern) {
                return is_string($pattern) && $pattern !== '';
            })));
        }

        return $patterns;
    }

    private static function build_like_clause(string $column, array $needles): array
    {
        global $wpdb;

        $needles = array_values(array_unique(array_filter(array_map('strval', $needles))));
        if (empty($needles)) {
            return ['', []];
        }

        $parts = [];
        $params = [];

        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            $parts[]  = sprintf('%s LIKE %%s', $column);
            $params[] = '%' . $wpdb->esc_like($needle) . '%';
        }

        if (empty($parts)) {
            return ['', []];
        }

        $clause = '(' . implode(' OR ', $parts) . ')';

        return [$clause, $params];
    }

    private static function build_direct_debit_payment_meta_query(): array
    {
        $patterns = self::get_direct_debit_payment_patterns();
        $clauses  = [];

        foreach ($patterns as $pattern) {
            $clauses[] = [
                'key'     => 'garantia_contratada_metodo_pago',
                'value'   => $pattern,
                'compare' => 'LIKE',
            ];
        }

        if (empty($clauses)) {
            return [
                'key'     => 'garantia_contratada_metodo_pago',
                'value'   => 'domiciliacion',
                'compare' => 'LIKE',
            ];
        }

        return array_merge(['relation' => 'OR'], $clauses);
    }

    private static function get_state_labels(): array
    {
        return [
            'pendiente_pago'        => __('Pendiente de pago', 'garantias-online-360vo'),
            'validacion_pendiente'  => __('Validación pendiente', 'garantias-online-360vo'),
            'sin_finalizar'         => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'              => __('Activada', 'garantias-online-360vo'),
            'expirada'              => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'         => __('Expira pronto', 'garantias-online-360vo'),
            'pendiente_cobro'       => __('Pend. Domiciliación', 'garantias-online-360vo'),
        ];
    }

    private static function humanize_state(string $state): string
    {
        $state = trim(str_replace('_', ' ', $state));
        if ($state === '') {
            return __('Sin estado', 'garantias-online-360vo');
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($state, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($state);
    }

    private static function format_month_label(DateTimeImmutable $date): string
    {
        $timestamp = $date->getTimestamp();
        $label = wp_date('F Y', $timestamp);
        if (! is_string($label) || $label === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($label);
    }

    private static function format_month_name(DateTimeImmutable $date): string
    {
        $timestamp = $date->getTimestamp();
        $label = wp_date('F', $timestamp);
        if (! is_string($label) || $label === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($label, 'UTF-8');
        }

        return strtolower($label);
    }

    private static function build_in_clause(array $values): array
    {
        $filtered = array_values(array_filter(array_map('strval', $values), static function ($value) {
            return $value !== '';
        }));

        if (empty($filtered)) {
            return ['%s', ['']];
        }

        $placeholders = implode(', ', array_fill(0, count($filtered), '%s'));

        return [$placeholders, $filtered];
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

        if (isset($context['vendor_id'])) {
            $prepared_context['vendor_id'] = (int) $context['vendor_id'];
        }

        if (! empty($context['payment_method'])) {
            $prepared_context['payment_method'] = sanitize_key($context['payment_method']);
        }

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
                return self::normalize_channel_slug((string) $raw['value']);
            }
            if (isset($raw['label']) && ! isset($raw['value'])) {
                $label = self::normalize_channel_label_text((string) $raw['label']);
                return $label !== '' ? sanitize_text_field($label) : '';
            }
        } elseif (is_string($raw)) {
            $slug = self::normalize_channel_slug($raw);
            if ($slug !== '') {
                return $slug;
            }
            $label = self::normalize_channel_label_text($raw);
            return $label !== '' ? sanitize_text_field($label) : '';
        }

        return '';
    }

    private static function user_is_professional($user): bool
    {
        if (!($user instanceof \WP_User)) {
            return false;
        }

        $roles = array_map('strtolower', (array) $user->roles);

        return in_array('go_profesional', $roles, true) || in_array('profesional', $roles, true);
    }

    private static function user_is_particular($user): bool
    {
        if (!($user instanceof \WP_User)) {
            return false;
        }

        return self::roles_include_particular((array) $user->roles);
    }

    private static function user_id_is_particular(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);

        return self::user_is_particular($user);
    }

    private static function roles_include_particular($roles): bool
    {
        if (empty($roles)) {
            return false;
        }

        $normalized = array_map(
            static function ($role) {
                return strtolower((string) $role);
            },
            (array) $roles
        );

        foreach (['go_particular', 'go_individual', 'particular', 'individual'] as $target) {
            if (in_array($target, $normalized, true)) {
                return true;
            }
        }

        return false;
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

        $traccion_raw = function_exists('get_field')
            ? get_field('datos_vehiculo_traccion', $id)
            : get_post_meta($id, 'datos_vehiculo_traccion', true);
        $traccion_label = is_array($traccion_raw)
            ? ($traccion_raw['label'] ?? $traccion_raw['value'] ?? '')
            : $traccion_raw;
        $traccion_value = is_array($traccion_raw)
            ? ($traccion_raw['value'] ?? $traccion_raw['label'] ?? '')
            : $traccion_raw;

        $traccion_camion_raw = function_exists('get_field')
            ? get_field('datos_vehiculo_traccion_camion', $id)
            : get_post_meta($id, 'datos_vehiculo_traccion_camion', true);
        $traccion_camion_label = is_array($traccion_camion_raw)
            ? ($traccion_camion_raw['label'] ?? $traccion_camion_raw['value'] ?? '')
            : $traccion_camion_raw;
        $traccion_camion_value = is_array($traccion_camion_raw)
            ? ($traccion_camion_raw['value'] ?? $traccion_camion_raw['label'] ?? '')
            : $traccion_camion_raw;
        $potencia = get_post_meta($id, 'datos_vehiculo_potencia', true);
        $potencia_kw = get_post_meta($id, 'datos_vehiculo_potencia_kw', true);
        $cilindrada = get_post_meta($id, 'datos_vehiculo_cilindrada', true);

        $plan_data = self::resolve_contracted_plan($id);
        $plan_id = $plan_data['id'];
        $plan = $plan_data['label'];
        $precio  = get_post_meta($id, 'garantia_contratada_precio', true);
        $metodo_pago_raw = get_post_meta($id, 'garantia_contratada_metodo_pago', true);
        $metodo_pago = is_array($metodo_pago_raw)
            ? ($metodo_pago_raw['value'] ?? '')
            : $metodo_pago_raw;
        $estado  = get_post_meta($id, 'estado_garantia_estado_contratacion', true);
        $raw_desde = get_post_meta($id, 'estado_garantia_inicio', true);
        $desde   = self::resolve_effective_start_date((int) $id, (string) $estado, $raw_desde);
        $hasta   = get_post_meta($id, 'estado_garantia_finalizacion', true);
        $fecha_contratacion_raw = get_post_meta($id, 'estado_garantia_fecha_contratacion', true);
        $fecha_contratacion_fmt = '';
        $fecha_contratacion_value = '';
        if (is_string($fecha_contratacion_raw) && $fecha_contratacion_raw !== '') {
            $fecha_contratacion_raw = sanitize_text_field($fecha_contratacion_raw);
            $fecha_contratacion_dt = DateTimeImmutable::createFromFormat('d/m/Y', $fecha_contratacion_raw);
            if (! $fecha_contratacion_dt) {
                $fecha_contratacion_dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha_contratacion_raw);
            }
            if ($fecha_contratacion_dt instanceof DateTimeImmutable) {
                $fecha_contratacion_fmt = $fecha_contratacion_dt->format('d/m/Y');
                $fecha_contratacion_value = $fecha_contratacion_dt->format('Y-m-d');
            } else {
                $fecha_contratacion_fmt = $fecha_contratacion_raw;
                $fecha_contratacion_value = $fecha_contratacion_raw;
            }
        }
        $estado_labels = [
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'validacion_pendiente' => __('Validación pendiente', 'garantias-online-360vo'),
            'sin_finalizar'  => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
        ];
        $estado_label = $estado_labels[$estado] ?? $estado;

        $vendor_id = get_post_meta($id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $vendor_id = is_array($vendor_id) && isset($vendor_id['ID']) ? (int) $vendor_id['ID'] : (int) $vendor_id;
        if ($vendor_id <= 0) {
            $author_id = (int) get_post_field('post_author', $id);
            if ($author_id > 0) {
                $author = get_user_by('id', $author_id);
                if (self::user_is_professional($author)) {
                    $vendor_id = $author_id;
                }
            }
        }
        $labels = [
            'company_name'       => '',
            'personal_name'      => '',
            'personal_full_name' => '',
            'first_name'         => '',
            'last_name'          => '',
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
        $vendor_company = $labels['company'] ?? [
            'name' => '',
            'trade_name' => '',
            'legal_name' => '',
            'tax_id' => '',
            'type' => ['value' => '', 'label' => ''],
            'address' => ['street' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => ''],
        ];

        $company_name_raw   = is_string($labels['company_name'] ?? '') ? trim((string) $labels['company_name']) : '';
        $personal_name_raw  = is_string($labels['personal_name'] ?? '') ? trim((string) $labels['personal_name']) : '';
        $personal_full_raw  = is_string($labels['personal_full_name'] ?? '') ? trim((string) $labels['personal_full_name']) : '';
        $personal_display   = $personal_full_raw !== '' ? $personal_full_raw : $personal_name_raw;
        $concesionario_raw  = $company_name_raw !== ''
            ? $company_name_raw
            : ($personal_name_raw !== '' ? $personal_name_raw : $personal_full_raw);
        $concesionario      = sanitize_text_field($concesionario_raw);
        if ($concesionario === '' && $personal_display !== '') {
            $concesionario = sanitize_text_field($personal_display);
        }

        $vendor_first_name = sanitize_text_field((string) ($labels['first_name'] ?? ''));
        $vendor_last_name  = sanitize_text_field((string) ($labels['last_name'] ?? ''));
        $vendor_full_name  = sanitize_text_field(
            $personal_full_raw !== ''
                ? $personal_full_raw
                : ($personal_name_raw !== '' ? $personal_name_raw : '')
        );
        if ($vendor_full_name === '' && ($vendor_first_name !== '' || $vendor_last_name !== '')) {
            $vendor_full_name = trim($vendor_first_name . ' ' . $vendor_last_name);
        }
        $vendor_type_label = '';
        $vendor_type_value = '';
        if (isset($vendor_company['type']) && is_array($vendor_company['type'])) {
            $raw_type_value = $vendor_company['type']['value'] ?? '';
            $vendor_type_value = self::normalize_vendor_type((string) $raw_type_value);
            $raw_type_label = isset($vendor_company['type']['label'])
                ? sanitize_text_field($vendor_company['type']['label'])
                : '';
            if ($vendor_type_value !== '') {
                $vendor_type_label = self::resolve_channel_label($vendor_type_value);
            } elseif ($raw_type_label !== '') {
                $vendor_type_label = $raw_type_label;
            }
        }

        $canal_venta_raw = get_post_meta($id, 'garantia_contratada_canal_venta', true);
        $canal_venta_value_raw = is_array($canal_venta_raw) && isset($canal_venta_raw['value'])
            ? (string) $canal_venta_raw['value']
            : (is_string($canal_venta_raw) ? (string) $canal_venta_raw : '');
        $canal_venta_value = self::normalize_channel_slug($canal_venta_value_raw);
        if (is_array($canal_venta_raw) && isset($canal_venta_raw['label'])) {
            $canal_venta = self::normalize_channel_label_text((string) $canal_venta_raw['label']);
        } else {
            $lookup = $canal_venta_value !== '' ? $canal_venta_value : '';
            $canal_choices = [
                'profesional' => __('Profesional', 'garantias-online-360vo'),
                'particular'  => __('Particular', 'garantias-online-360vo'),
                'gestoria'    => __('Gestoría', 'garantias-online-360vo'),
            ];
            $fallback_label = $lookup !== '' ? ucwords(str_replace(['_', '-'], ' ', $lookup)) : '';
            $canal_venta = $canal_choices[$lookup] ?? $fallback_label;
            $canal_venta = self::normalize_channel_label_text($canal_venta);
        }

        $canal_venta_summary = $canal_venta;
        if ($vendor_type_label !== '') {
            $canal_venta_summary = $canal_venta !== ''
                ? sprintf('%s (%s)', $canal_venta, $vendor_type_label)
                : $vendor_type_label;
        }
        $canal_venta_summary = self::normalize_channel_label_text($canal_venta_summary);

        $telefono_vendedor = $vendor_id
            ? get_user_meta($vendor_id, 'datos_usuario_telefono', true)
            : '';
        $email_details = $vendor_id
            ? NotificationEmailResolver::resolve_with_details($vendor_id)
            : ['email' => '', 'default_email' => '', 'source' => 'registration'];
        $email_vendedor = sanitize_email($email_details['email'] ?? '');
        $email_registro = sanitize_email($email_details['default_email'] ?? '');
        $email_source   = is_string($email_details['source'] ?? '')
            ? sanitize_key($email_details['source'])
            : 'registration';
        $avatar_vendedor = $vendor_id ? get_avatar_url($vendor_id, ['size' => 96]) : '';
        $vendedor_url   = $vendor_id ? get_edit_user_link($vendor_id) : '#';
        $vendor_slug = '';
        $vendor_profile_url = '';
        if ($vendor_id) {
            $vendor_user = get_user_by('id', $vendor_id);
            if ($vendor_user instanceof \WP_User) {
                $vendor_slug = sanitize_user(
                    $vendor_user->user_nicename !== ''
                        ? $vendor_user->user_nicename
                        : $vendor_user->user_login,
                    true
                );
                if ($vendor_slug !== '') {
                    $vendor_profile_url = trailingslashit(home_url('/garantias-online/clientes/' . $vendor_slug));
                }
            }
        }

        $cobro_realizado = get_post_meta($id, 'garantia_contratada_estado_cobro_cobro_realizado', true);
        $iban_vendedor = '';
        if ($vendor_id) {
            $iban_meta_keys = [
                'gestion_pagos_gestion_sepa_datos_deudor_numero_cuenta',
                'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta',
                'gestion_pagos_gestion_sepa_datos_deudor_iban',
                'gestion_pagos_gestion_sepa_numero_cuenta',
                'gestion_pagos_gestion_sepa_numero_cienta',
                'gestion_pagos_gestion_sepa_iban',
            ];

            foreach ($iban_meta_keys as $iban_key) {
                $raw_value = get_user_meta($vendor_id, $iban_key, true);
                if ($raw_value === '' || $raw_value === null) {
                    continue;
                }

                $iban_vendedor = (string) $raw_value;
                break;
            }
        }

        $iban_vendedor = sanitize_text_field($iban_vendedor);
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

        $post_date          = get_post_field('post_date', $id);
        $post_date_gmt      = get_post_field('post_date_gmt', $id);
        $post_modified      = get_post_field('post_modified', $id);
        $post_modified_gmt  = get_post_field('post_modified_gmt', $id);

        $primera_matriculacion_display = '-';
        if (is_string($primera_matriculacion) && $primera_matriculacion !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $primera_matriculacion);
            if ($date instanceof DateTimeImmutable) {
                $primera_matriculacion_display = $date->format('d/m/y');
            } else {
                $primera_matriculacion_display = $primera_matriculacion;
            }
        }

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
            'primera_matriculacion' => $primera_matriculacion_display,
            'bastidor' => $bastidor ?: '-',
            'precio_venta' => $precio_venta ?: '-',
            'combustible' => $combustible_label ?: '-',
            'combustible_value' => $combustible_value ?: '',
            'cambio' => $cambio_label ?: '-',
            'cambio_value' => $cambio_value ?: '',
            'traccion' => $traccion_label ?: '-',
            'traccion_value' => $traccion_value ?: '',
            'traccion_camion' => $traccion_camion_label ?: '-',
            'traccion_camion_value' => $traccion_camion_value ?: '',
            'potencia' => $potencia ?: '-',
            'potencia_kw' => $potencia_kw ?: '',
            'cilindrada' => $cilindrada ?: '-',
            'plan' => $plan,
            'plan_id' => $plan_id,
            'precio' => $precio,
            'metodo_pago' => $metodo_pago ?: '',
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => [
                'value' => $estado,
                'label' => $estado_label,
            ],
            'estado_garantia' => [
                'estado_contratacion'   => $estado,
                'inicio'                => $raw_desde,
                'finalizacion'          => $hasta,
                'fecha_contratacion'    => $fecha_contratacion_value,
                'fecha_contratacion_raw' => $fecha_contratacion_raw,
                'fecha_contratacion_fmt' => $fecha_contratacion_fmt,
            ],
            'concesionario' => $concesionario !== '' ? $concesionario : '-',
            'vendor_id' => $vendor_id,
            'concesionario_personal' => $vendor_full_name,
            'concesionario_personal_first' => $vendor_first_name,
            'concesionario_personal_last' => $vendor_last_name,
            'concesionario_personal_greeting' => $vendor_first_name !== '' ? $vendor_first_name : $vendor_full_name,
            'vendor_company' => $vendor_company,
            'vendor_company_type_label' => $vendor_type_label,
            'vendor_company_type_value' => $vendor_type_value,
            'canal_venta' => $canal_venta ?: '-',
            'canal_venta_value' => $canal_venta_value,
            'canal_venta_summary' => $canal_venta_summary,
            'telefono_vendedor' => sanitize_text_field($telefono_vendedor ?: ''),
            'email_vendedor' => $email_vendedor,
            'email_vendedor_registro' => $email_registro,
            'email_vendedor_source' => $email_source,
            'avatar_vendedor' => $avatar_vendedor ?: '',
            'vendedor_url' => $vendedor_url,
            'vendor_slug' => $vendor_slug,
            'vendor_profile_url' => $vendor_profile_url,
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
            'post_date' => is_string($post_date) ? $post_date : '',
            'post_date_gmt' => is_string($post_date_gmt) ? $post_date_gmt : '',
            'post_modified' => is_string($post_modified) ? $post_modified : '',
            'post_modified_gmt' => is_string($post_modified_gmt) ? $post_modified_gmt : '',
        ];

        return self::inject_document_collection($detail, $id, $include_document_urls);
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
        $current_user_obj = wp_get_current_user();
        $current_user_roles = $current_user_obj instanceof \WP_User ? (array) $current_user_obj->roles : [];
        $is_director = in_array('go_director_comercial', $current_user_roles, true);
        $is_garantias_role = in_array('go_garantias', $current_user_roles, true);
        $is_particular_role = self::roles_include_particular($current_user_roles);
        $page          = absint($request['page']);
        $per_page      = absint($request['per_page']);
        $search        = isset($request['search']) ? sanitize_text_field($request['search']) : '';
        $estado        = isset($request['estado']) ? sanitize_text_field($request['estado']) : '';
        $plan          = isset($request['plan']) ? absint($request['plan']) : 0;
        $canal         = isset($request['canal']) ? sanitize_text_field($request['canal']) : '';
        $concesionario = isset($request['concesionario']) ? absint($request['concesionario']) : 0;
        $vendor_type   = isset($request['vendor_type']) ? sanitize_key($request['vendor_type']) : '';
        $payment_method = isset($request['payment_method']) ? sanitize_key($request['payment_method']) : '';
        $commercial    = isset($request['commercial']) ? absint($request['commercial']) : 0;
        $order_by      = isset($request['order_by']) ? sanitize_key($request['order_by']) : '';
        $order         = isset($request['order']) ? strtolower(sanitize_key($request['order'])) : '';
        $year          = isset($request['year']) ? absint($request['year']) : 0;
        $month_from    = isset($request['month_from']) ? absint($request['month_from']) : 0;
        $month_to      = isset($request['month_to']) ? absint($request['month_to']) : 0;

        $normalized_month_from = 0;
        $normalized_month_to   = 0;
        if ($year > 0) {
            $normalized_month_from = ($month_from >= 1 && $month_from <= 12) ? $month_from : 1;
            $normalized_month_to   = ($month_to >= 1 && $month_to <= 12) ? $month_to : 12;
            if ($normalized_month_from > $normalized_month_to) {
                $normalized_month_to = $normalized_month_from;
            }
        }

        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        $sort_config = self::resolve_sort_config($order_by, $order);

        // ----- CACHING -----
        $cache_generation = self::get_list_cache_generation();
        // Elimina search del cache_key porque si no el mismo usuario puede buscar cosas distintas y obtiene el cache anterior
        $cache_key = 'go_glist_v' . $cache_generation . '_' . $current_user . "_p{$page}_pp{$per_page}";
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
        if ($vendor_type) {
            $cache_key .= '_vt_' . md5($vendor_type);
        }
        if ($payment_method) {
            $cache_key .= '_pm_' . md5($payment_method);
        }
        if ($commercial) {
            $cache_key .= '_cm_' . $commercial;
        }
        if ($year > 0) {
            $cache_key .= '_yr_' . $year;
            $cache_key .= '_mf_' . $normalized_month_from;
            $cache_key .= '_mt_' . $normalized_month_to;
        }
        if (! empty($sort_config['cache_suffix'])) {
            $cache_key .= $sort_config['cache_suffix'];
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

        $args['orderby'] = $sort_config['orderby'];
        $args['order']   = $sort_config['order'];

        if (! empty($sort_config['meta_key'])) {
            $args['meta_key']  = $sort_config['meta_key'];
            $args['meta_type'] = $sort_config['meta_type'] ?? 'CHAR';
        }

        // Permisos: restringe por profesional/comercial salvo admins
        $meta_query = [];
        $vendor_type_ids = [];
        $commercial_vendor_ids = [];
        if (!current_user_can('manage_options') && ! $is_director && ! $is_garantias_role) {
            $user_profesional_ids = [$current_user];
            if (! $is_particular_role) {
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
            }
            $meta_query[] = [
                'key'     => 'garantia_contratada_concesionario_empresa_profesional',
                'value'   => $user_profesional_ids,
                'compare' => 'IN',
            ];
        }

        if ($vendor_type !== '') {
            $vendor_type_ids = self::get_professional_ids_by_type($vendor_type);
            if (empty($vendor_type_ids)) {
                $vendor_type_ids = [0];
            }
        }

        if ($commercial > 0) {
            $commercial_vendor_ids = self::get_professional_ids_by_commercial($commercial);
            if (empty($commercial_vendor_ids)) {
                $commercial_vendor_ids = [0];
            }
        }

        // ---- FILTROS ----
        $pending_collect_meta_query = [
            'relation' => 'AND',
            self::build_direct_debit_payment_meta_query(),
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

        if ($estado === 'pendiente_revision') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'   => 'estado_garantia_estado_contratacion',
                    'value' => 'validacion_pendiente',
                ],
                $pending_collect_meta_query,
            ];
        } elseif ($estado === 'pendiente_cobro') {
            $meta_query[] = $pending_collect_meta_query;
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
            $normalized_canal = self::normalize_channel_slug($canal);
            $canal_like_values = array_values(array_filter(array_unique([
                $normalized_canal,
                $canal,
                $normalized_canal === 'particular' ? 'individual' : '',
            ])));

            if (count($canal_like_values) <= 1) {
                $meta_query[] = [
                    'key'     => 'garantia_contratada_canal_venta',
                    'value'   => $canal_like_values ? $canal_like_values[0] : $normalized_canal,
                    'compare' => 'LIKE',
                ];
            } else {
                $or_conditions = [];
                foreach ($canal_like_values as $value) {
                    $or_conditions[] = [
                        'key'     => 'garantia_contratada_canal_venta',
                        'value'   => $value,
                        'compare' => 'LIKE',
                    ];
                }
                $meta_query[] = array_merge(['relation' => 'OR'], $or_conditions);
            }
        }
        if ($concesionario) {
            $meta_query[] = [
                'key'   => 'garantia_contratada_concesionario_empresa_profesional',
                'value' => $concesionario,
            ];
        }
        $needs_vendor_filter = false;
        $combined_vendor_ids = [];

        if ($vendor_type !== '') {
            $needs_vendor_filter = true;
            $combined_vendor_ids = $vendor_type_ids;
        }

        if ($commercial > 0) {
            $needs_vendor_filter = true;
            if (empty($combined_vendor_ids)) {
                $combined_vendor_ids = $commercial_vendor_ids;
            } else {
                $combined_vendor_ids = array_values(
                    array_intersect($combined_vendor_ids, $commercial_vendor_ids)
                );
            }
        }

        if ($needs_vendor_filter) {
            if (empty($combined_vendor_ids)) {
                $combined_vendor_ids = [0];
            }
            $meta_query[] = [
                'key'     => 'garantia_contratada_concesionario_empresa_profesional',
                'value'   => $combined_vendor_ids,
                'compare' => 'IN',
            ];
        }
        if ($payment_method) {
            $meta_query[] = [
                'key'     => 'garantia_contratada_metodo_pago',
                'value'   => $payment_method,
                'compare' => 'LIKE',
            ];
        }

        if ($year > 0) {
            $start_month = $normalized_month_from > 0 ? $normalized_month_from : 1;
            $end_month   = $normalized_month_to > 0 ? $normalized_month_to : 12;
            $start_date  = sprintf('%04d-%02d-01', $year, $start_month);
            $end_day     = cal_days_in_month(CAL_GREGORIAN, $end_month, $year);
            $end_date    = sprintf('%04d-%02d-%02d', $year, $end_month, $end_day);

            $date_range_query = [
                'key'     => 'estado_garantia_inicio',
                'value'   => [$start_date, $end_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ];

            if ($estado === '' || $estado === 'sin_finalizar') {
                $meta_query[] = array_merge(
                    ['relation' => 'OR'],
                    [
                        $date_range_query,
                        [
                            'relation' => 'AND',
                            [
                                'key'   => 'estado_garantia_estado_contratacion',
                                'value' => 'sin_finalizar',
                            ],
                            [
                                'relation' => 'OR',
                                [
                                    'key'     => 'estado_garantia_inicio',
                                    'compare' => 'NOT EXISTS',
                                ],
                                [
                                    'key'     => 'estado_garantia_inicio',
                                    'value'   => '',
                                    'compare' => '=',
                                ],
                                [
                                    'key'     => 'estado_garantia_inicio',
                                    'value'   => '0000-00-00',
                                    'compare' => '=',
                                ],
                            ],
                        ],
                    ]
                );
            } else {
                $meta_query[] = $date_range_query;
            }
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

        $cache_generation = self::get_list_cache_generation();
        $cache_key = 'go_gfilters_v' . $cache_generation . '_' . $current_user;
        $cache = get_transient($cache_key);
        if ($cache !== false) {
            return $cache;
        }

        $meta_query = [];
        $current_user_obj = wp_get_current_user();
        $current_user_roles = $current_user_obj instanceof \WP_User ? (array) $current_user_obj->roles : [];
        $is_director = in_array('go_director_comercial', $current_user_roles, true);
        $is_garantias_role = in_array('go_garantias', $current_user_roles, true);

        if (!current_user_can('manage_options') && ! $is_director && ! $is_garantias_role) {
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
            'post_status'    => ['draft', 'publish', 'pending', 'future'],
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
        $estado_counts = [];
        $plan_ids = [];
        $channels_map = [];
        $channel_counts = [];
        $vendor_ids = [];
        $vendor_id_counts = [];
        $start_dates_by_year = [];

        foreach ($q->posts as $post_id) {
            $e = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
            if ($e) {
                $estados[] = $e;
                if (! isset($estado_counts[$e])) {
                    $estado_counts[$e] = 0;
                }
                $estado_counts[$e]++;
            }
            $plan_entry = self::resolve_contracted_plan($post_id);
            if (($plan_entry['id'] ?? 0) > 0) {
                $plan_ids[] = (int) $plan_entry['id'];
            }

            $channel_raw = get_post_meta($post_id, 'garantia_contratada_canal_venta', true);
            $channel_value = sanitize_key(str_replace('go_', '', self::normalize_channel_meta($channel_raw)));
            if ($channel_value !== '') {
                $channels_map[$channel_value] = self::resolve_channel_label($channel_value);
                if (! isset($channel_counts[$channel_value])) {
                    $channel_counts[$channel_value] = 0;
                }
                $channel_counts[$channel_value]++;
            }

            $vendor_raw = get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
            $vendor_id = self::normalize_vendor_meta($vendor_raw);
            if ($vendor_id > 0) {
                $vendor_ids[] = $vendor_id;
                if (! isset($vendor_id_counts[$vendor_id])) {
                    $vendor_id_counts[$vendor_id] = 0;
                }
                $vendor_id_counts[$vendor_id]++;
            }

            $start_raw = get_post_meta($post_id, 'estado_garantia_inicio', true);
            $effective_start = self::resolve_effective_start_date((int) $post_id, (string) $e, $start_raw);
            if ($effective_start !== '') {
                $timestamp = strtotime($effective_start);
                if ($timestamp !== false) {
                    $year_value = (int) gmdate('Y', $timestamp);
                    $month_value = (int) gmdate('n', $timestamp);
                    if ($year_value > 0 && $month_value >= 1 && $month_value <= 12) {
                        if (! isset($start_dates_by_year[$year_value])) {
                            $start_dates_by_year[$year_value] = [];
                        }
                        $start_dates_by_year[$year_value][$month_value] = true;
                    }
                }
            }
        }

        $estados = array_values(array_unique(array_filter($estados)));
        sort($estados);
        $estado_labels = [
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'validacion_pendiente' => __('Validación pendiente', 'garantias-online-360vo'),
            'sin_finalizar'  => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
        ];
        $estados = array_map(function ($e) use ($estado_labels, $estado_counts) {
            return [
                'value' => $e,
                'label' => $estado_labels[$e] ?? $e,
                'count' => $estado_counts[$e] ?? 0,
            ];
        }, $estados);

        $estado_values = array_map(function ($entry) {
            return $entry['value'] ?? '';
        }, $estados);
        $sin_finalizar_count = $estado_counts['sin_finalizar'] ?? 0;
        if ($sin_finalizar_count > 0 && ! in_array('sin_finalizar', $estado_values, true)) {
            $estados[] = [
                'value' => 'sin_finalizar',
                'label' => $estado_labels['sin_finalizar'],
                'count' => $sin_finalizar_count,
            ];
        }

        $has_revision_option = false;
        $has_collect_option = false;
        foreach ($estados as $entry) {
            if (isset($entry['value']) && $entry['value'] === 'pendiente_revision') {
                $has_revision_option = true;
                break;
            }
        }

        if (! $has_revision_option) {
            $estados[] = [
                'value' => 'pendiente_revision',
                'label' => __('Verificar/cobrar', 'garantias-online-360vo'),
                'count' => $estado_counts['pendiente_revision'] ?? 0,
            ];
        }

        foreach ($estados as $entry) {
            if (isset($entry['value']) && $entry['value'] === 'pendiente_cobro') {
                $has_collect_option = true;
                break;
            }
        }

        if (! $has_collect_option) {
            $estados[] = [
                'value' => 'pendiente_cobro',
                'label' => __('Pend. Domiciliación', 'garantias-online-360vo'),
                'count' => $estado_counts['pendiente_cobro'] ?? 0,
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
            'fields' => 'ID',
        ]);
        $concesionarios = [];
        $concesionario_map = [];
        foreach ($users as $user_id) {
            $labels = UserProfileResolver::get_vendor_labels((int) $user_id);
            $name = $labels['company_name'] !== ''
                ? $labels['company_name']
                : ($labels['personal_name'] !== '' ? $labels['personal_name'] : sprintf(__('Usuario #%d', 'garantias-online-360vo'), (int) $user_id));
            $type_value = self::normalize_vendor_type((string) ($labels['company']['type']['value'] ?? ''));
            $raw_type_label = $labels['company']['type']['label'] ?? '';
            $type_label = $type_value !== ''
                ? self::resolve_channel_label($type_value)
                : sanitize_text_field($raw_type_label);
            $concesionarios[] = [
                'id'   => (int) $user_id,
                'name' => $name,
                'type' => $type_value,
                'type_label' => $type_label,
            ];
            $concesionario_map[(int) $user_id] = [
                'type'       => $type_value,
                'type_label' => $type_label,
            ];
        }

        $vendor_type_counts = [];
        foreach ($vendor_id_counts as $vendor_id => $count) {
            if (! isset($concesionario_map[$vendor_id])) {
                continue;
            }
            $type_value = sanitize_key($concesionario_map[$vendor_id]['type'] ?? '');
            if ($type_value === '') {
                continue;
            }
            if (! isset($vendor_type_counts[$type_value])) {
                $vendor_type_counts[$type_value] = 0;
            }
            $vendor_type_counts[$type_value] += (int) $count;
        }

        $commercial_users = get_users([
            'role'    => 'go_comercial',
            'fields'  => ['ID', 'display_name'],
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]);
        $commercials = [];
        foreach ($commercial_users as $user) {
            $id = isset($user->ID) ? (int) $user->ID : 0;
            if ($id <= 0) {
                continue;
            }
            $display_name = isset($user->display_name) ? trim((string) $user->display_name) : '';
            if ($display_name === '') {
                $user_obj = get_userdata($id);
                if ($user_obj instanceof WP_User) {
                    $display_name = $user_obj->display_name ?: $user_obj->user_email;
                }
            }
            if ($display_name === '') {
                $display_name = sprintf(__('Comercial #%d', 'garantias-online-360vo'), $id);
            }
            $commercials[] = [
                'id'   => $id,
                'name' => $display_name,
            ];
        }

        $payment_methods = self::collect_payment_methods($q->posts);

        $channels = [];
        $channel_priority = [
            'particular' => 1,
            'profesional' => 2,
            'gestoria'    => 3,
        ];
        foreach ($channels_map as $value => $label) {
            $channels[] = [
                'value'    => $value,
                'label'    => $label,
                'count'    => (int) ($channel_counts[$value] ?? 0),
                'priority' => $channel_priority[$value] ?? 99,
            ];
        }

        usort($channels, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcasecmp($a['label'], $b['label']);
            }
            return $a['priority'] <=> $b['priority'];
        });

        $channels = array_map(function ($item) {
            return [
                'value' => $item['value'],
                'label' => $item['label'],
                'count' => (int) ($item['count'] ?? 0),
            ];
        }, $channels);

        $vendor_types = [];
        $vendor_ids = array_unique(array_filter($vendor_ids));
        $vendor_type_priority = [
            'compraventa'          => 1,
            'concesionario_oficial'=> 2,
            'gestoria'             => 3,
            'profesional'          => 4,
        ];
        foreach ($vendor_ids as $vendor_id) {
            if (! isset($concesionario_map[$vendor_id])) {
                continue;
            }
            $type_value = sanitize_key($concesionario_map[$vendor_id]['type'] ?? '');
            if ($type_value === '') {
                continue;
            }
            if (isset($vendor_types[$type_value])) {
                continue;
            }
            $type_label = $concesionario_map[$vendor_id]['type_label'] ?? '';
            if ($type_label === '') {
                $type_label = self::resolve_channel_label($type_value);
            }
            $vendor_types[$type_value] = [
                'value'    => $type_value,
                'label'    => $type_label,
                'count'    => (int) ($vendor_type_counts[$type_value] ?? 0),
                'priority' => $vendor_type_priority[$type_value] ?? 99,
            ];
        }

        if (! empty($vendor_types)) {
            uasort($vendor_types, function ($a, $b) {
                if ($a['priority'] === $b['priority']) {
                    return strcasecmp($a['label'], $b['label']);
                }
                return $a['priority'] <=> $b['priority'];
            });
            $vendor_types = array_map(function ($item) {
                return [
                    'value' => $item['value'],
                    'label' => $item['label'],
                    'count' => (int) ($item['count'] ?? 0),
                ];
            }, $vendor_types);
        } else {
            $vendor_types = [];
        }

        $period_years = array_keys($start_dates_by_year);
        rsort($period_years);

        $period_year_months = [];
        foreach ($start_dates_by_year as $year_value => $months_map) {
            $month_numbers = array_keys($months_map);
            sort($month_numbers);
            $period_year_months[(string) $year_value] = array_values(array_map('intval', $month_numbers));
        }

        $current_year  = (int) current_time('Y');
        $current_month = (int) current_time('n');

        $response = new WP_REST_Response([
            'estados'          => $estados,
            'planes'           => $planes,
            'concesionarios'   => $concesionarios,
            'payment_methods'  => $payment_methods,
            'channels'         => $channels,
            'vendor_types'     => $vendor_types,
            'commercials'      => $commercials,
            'periods'          => [
                'years'        => array_values(array_map('intval', $period_years)),
                'year_months'  => $period_year_months,
                'current_year' => $current_year,
                'current_month'=> $current_month,
            ],
        ]);

        set_transient($cache_key, $response, 300);

        return $response;
    }

    private static function resolve_effective_start_date(int $post_id, string $estado, $raw_start): string
    {
        $start = is_string($raw_start) ? trim($raw_start) : '';
        if ($start !== '' && $start !== '0000-00-00') {
            return $start;
        }

        if ($estado !== 'sin_finalizar') {
            return '';
        }

        if ($post_id <= 0) {
            return '';
        }

        $candidates = [
            get_post_field('post_date', $post_id),
            get_post_field('post_date_gmt', $post_id),
            get_post_field('post_modified', $post_id),
            get_post_field('post_modified_gmt', $post_id),
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalize_post_date($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $created = get_post_time('Y-m-d', false, $post_id, false);
        if (is_string($created) && $created !== '' && $created !== '0000-00-00') {
            return $created;
        }

        $current = current_time('Y-m-d');
        return is_string($current) ? $current : '';
    }

    private static function resolve_sort_config(string $order_by, string $order): array
    {
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        $config = [
            'orderby'     => 'date',
            'order'       => $order,
            'cache_suffix'=> '_ob_created_or_' . strtolower($order),
        ];

        switch ($order_by) {
            case 'valid_from':
                $config['orderby']      = 'meta_value';
                $config['meta_key']     = 'estado_garantia_inicio';
                $config['meta_type']    = 'DATE';
                $config['cache_suffix'] = '_ob_valid_from_or_' . strtolower($order);
                break;
            case 'valid_until':
                $config['orderby']      = 'meta_value';
                $config['meta_key']     = 'estado_garantia_finalizacion';
                $config['meta_type']    = 'DATE';
                $config['cache_suffix'] = '_ob_valid_until_or_' . strtolower($order);
                break;
            case 'created':
            default:
                // Mantiene configuración por defecto
                break;
        }

        return $config;
    }

    private static function resolve_channel_label(string $value): string
    {
        $value = sanitize_key($value);
        $map = [
            'particular'          => __('Particular', 'garantias-online-360vo'),
            'individual'          => __('Particular', 'garantias-online-360vo'),
            'go_particular'       => __('Particular', 'garantias-online-360vo'),
            'go_individual'       => __('Particular', 'garantias-online-360vo'),
            'profesional'         => __('Profesional', 'garantias-online-360vo'),
            'compraventa'         => __('Compraventa', 'garantias-online-360vo'),
            'concesionario'       => __('Concesionario Oficial', 'garantias-online-360vo'),
            'concesionario_oficial'=> __('Concesionario Oficial', 'garantias-online-360vo'),
            'gestoria'            => __('Gestoría', 'garantias-online-360vo'),
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        $value = str_replace(['_', '-'], ' ', $value);
        return ucwords($value);
    }

    private static function get_professional_ids_by_type(string $type): array
    {
        $type = self::normalize_vendor_type($type);
        if ($type === '') {
            return [];
        }

        $users = get_users([
            'role'   => 'go_profesional',
            'fields' => 'ID',
        ]);

        if (! $users) {
            return [];
        }

        $matched = [];
        foreach ($users as $user_id) {
            $labels = UserProfileResolver::get_vendor_labels((int) $user_id);
            $company = self::normalize_vendor_type((string) ($labels['company']['type']['value'] ?? ''));
            if ($company === $type) {
                $matched[] = (int) $user_id;
            }
        }

        return array_values(array_unique($matched));
    }

    private static function normalize_post_date($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $converted = mysql2date('Y-m-d', $value, false);
        if (is_string($converted) && $converted !== '' && $converted !== '0000-00-00') {
            return $converted;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return '';
    }

    private static function normalize_channel_slug(string $value): string
    {
        $value = sanitize_key($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'go_profesional' => 'profesional',
            'go_particular'  => 'particular',
            'go_gestoria'    => 'gestoria',
            'go_individual'  => 'particular',
            'individual'     => 'particular',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        if (strpos($value, 'go_') === 0) {
            return substr($value, 3);
        }

        return $value;
    }

    private static function normalize_channel_label_text(string $label): string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return '';
        }

        $lower = strtolower($trimmed);
        if (in_array($lower, ['particular', 'individual', 'go_particular', 'go_individual'], true)) {
            return __('Particular', 'garantias-online-360vo');
        }

        if (preg_match('/\bindividual\b/i', $trimmed)) {
            return preg_replace(
                '/\bindividual\b/i',
                __('Particular', 'garantias-online-360vo'),
                $trimmed
            );
        }

        return $trimmed;
    }

    private static function normalize_vendor_type(string $value): string
    {
        $value = sanitize_key($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'concesionario'         => 'concesionario_oficial',
            'concesionario-oficial' => 'concesionario_oficial',
            'oficial'               => 'concesionario_oficial',
            'go_particular'         => 'particular',
            'go_individual'         => 'particular',
            'individual'            => 'particular',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        return $value;
    }

    private static function get_professional_ids_by_commercial(int $commercial_id): array
    {
        $commercial_id = absint($commercial_id);
        if ($commercial_id <= 0) {
            return [];
        }

        $users = get_users([
            'role'       => 'go_profesional',
            'fields'     => 'ID',
            'meta_query' => [
                [
                    'key'     => 'ajustes_usuarios_comercial_asignado',
                    'value'   => '"' . $commercial_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if (empty($users)) {
            return [];
        }

        return array_values(
            array_unique(
                array_map('intval', $users)
            )
        );
    }

    private static function collect_payment_methods(array $post_ids): array
    {
        $choices = [
            'domiciliacion'          => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'domiciliacion_bancaria' => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'domiciliacion-bancaria' => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'transferencia'         => __('Transferencia bancaria', 'garantias-online-360vo'),
        ];

        $found = [];
        foreach ($post_ids as $post_id) {
            $raw = get_post_meta($post_id, 'garantia_contratada_metodo_pago', true);
            if (is_array($raw)) {
                $value = sanitize_key($raw['value'] ?? '');
                $label = sanitize_text_field($raw['label'] ?? '');
            } else {
                $value = sanitize_key((string) $raw);
                $label = '';
            }

            if ($value === '') {
                continue;
            }

            if ($label === '' && isset($choices[$value])) {
                $label = $choices[$value];
            }

            if ($label === '') {
                $label = ucwords(str_replace(['_', '-'], ' ', $value));
            }

            $found[$value] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return array_values($found);
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

        /**
         * Fires after a payment has been recorded for a guarantee.
         *
         * @param int    $post_id     Guarantee ID.
         * @param string $method      Payment method slug.
         * @param string $state       Contract state associated with the event.
         * @param string $actor_type  Actor that confirmed the payment (platform, vendor, actor...).
         * @param int    $initiator   Current user ID.
         */
        do_action(
            'go360/guarantee/payment_recorded',
            $post_id,
            $method,
            $state,
            $actor_type,
            get_current_user_id()
        );
    }

    /**
     * Limpia todos los transients del listado al guardar una garantía.
     */
    public static function clear_list_transients($post_id, $post, $update)
    {
        self::invalidate_guarantee_list_cache((int) $post_id);
    }
    public static function clear_list_transients_on_delete($post_id)
    {
        $post_type = get_post_type($post_id);
        if ($post_type === \GarantiasOnline360VO\GuaranteeCPT::POST_TYPE) {
            self::invalidate_guarantee_list_cache((int) $post_id);
        }
    }

    private static function get_list_cache_generation(): int
    {
        $generation = (int) get_option(self::LIST_CACHE_GENERATION_OPTION, 1);
        if ($generation <= 0) {
            $generation = 1;
        }

        return $generation;
    }

    private static function bump_list_cache_generation(): int
    {
        $next_generation = self::get_list_cache_generation() + 1;
        update_option(self::LIST_CACHE_GENERATION_OPTION, $next_generation, false);
        wp_cache_delete(self::LIST_CACHE_GENERATION_OPTION, 'options');

        return $next_generation;
    }

    private static function invalidate_guarantee_list_cache(int $post_id = 0): void
    {
        $key = $post_id > 0 ? $post_id : 0;
        if (isset(self::$list_cache_invalidated[$key])) {
            return;
        }

        self::$list_cache_invalidated[$key] = true;

        if ($post_id > 0) {
            delete_transient('go_gdetail_' . $post_id);
        }

        self::bump_list_cache_generation();

        delete_transient(self::SUMMARY_TRANSIENT);
    }

    /**
     * Exposes the current list cache generation for frontend consumers.
     */
    public static function get_list_cache_generation_snapshot(): int
    {
        return self::get_list_cache_generation();
    }

    public static function get_list_generation()
    {
        return rest_ensure_response([
            'generation' => self::get_list_cache_generation_snapshot(),
        ]);
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
