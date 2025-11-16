<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Account\AccountViewModel;
use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\GuaranteeCPT;
use GarantiasOnline360VO\Register\SepaMandateService;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use WP_User_Query;
use function get_current_user_id;
use function get_transient;
use function home_url;
use function is_user_logged_in;
use function sanitize_key;
use function set_transient;
use function time;

if (! defined('ABSPATH')) {
    exit;
}

class ClientRestController
{
    public const NAMESPACE = 'go/v1';
    public const REST_BASE = 'clientes';

    private const QUICK_FILTERS = [
        'pendiente_pago',
        'sin_finalizar',
        'sin_comercial',
        'sin_ofertas',
    ];

    /**
     * @var array<int,int>|null
     */
    private static $commercial_client_counts = null;

    private const PRESENCE_TRANSIENT_PREFIX = 'go_client_presence_';
    private const PRESENCE_STORAGE_TTL      = 900; // 15 minutes.
    private const PRESENCE_ACTIVE_GRACE     = 150; // Seconds a heartbeat keeps the user online.

    /**
     * @var array<int,bool>
     */
    private static array $session_status_cache = [];

    /**
     * @var array<int,array{status:string,timestamp:int}>
     */
    private static array $presence_cache = [];

    /**
     * @var array<string,array<int,int>>
     */
    private static array $guarantee_state_client_cache = [];

    /**
     * @var array<int,int>|null
     */
    private static ?array $clients_with_commercial_assignments = null;

    /**
     * @var array<int,int>|null
     */
    private static ?array $clients_with_active_offers = null;

    /**
     * @var array<int,array<int,array<string,mixed>>>
     */
    private static array $active_offers_cache = [];

    /**
     * @var array<int,int>|null
     */
    private static ?array $all_client_ids = null;

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_items'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'page' => [
                            'default'           => 1,
                            'validate_callback' => 'absint',
                        ],
                        'per_page' => [
                            'default'           => 20,
                            'validate_callback' => 'absint',
                        ],
                        'search' => [
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'channel' => [
                            'sanitize_callback' => 'sanitize_key',
                        ],
                        'quick_filter' => [
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/status',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_statuses'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'ids' => [
                            'required' => false,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/status/heartbeat',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [__CLASS__, 'record_heartbeat'],
                    'permission_callback' => [__CLASS__, 'heartbeat_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/comerciales',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'search_commercials'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'search' => [
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'per_page' => [
                            'default'           => 20,
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/commercials',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'update_commercials'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/offers',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_user_offers'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'update_user_offers'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/sepa/activate',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'activate_sepa'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/sepa/pending',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'generate_pending_sepa'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/sepa/deactivate',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'deactivate_sepa'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/(?P<id>\d+)/sepa/signed',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'upload_signed_sepa'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'validate_callback' => 'absint',
                        ],
                    ],
                ],
            ]
        );
    }

    public static function permissions_check($request = null): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        $current_user = wp_get_current_user();
        if (! $current_user instanceof WP_User) {
            return false;
        }

        $roles = array_map('strval', (array) $current_user->roles);
        $has_client_access = in_array('go_director_comercial', $roles, true)
            || in_array('go_garantias', $roles, true);

        if (! $has_client_access) {
            return false;
        }

        if (! $request instanceof WP_REST_Request) {
            return true;
        }

        $method = strtoupper($request->get_method());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $route = (string) $request->get_route();
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            if (preg_match('#/clientes/\d+/commercials/?$#', $route)) {
                return true;
            }
        }

        return false;
    }

    public static function get_user_offers(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('Identificador de usuario no válido.', 'garantias-online-360vo')],
                400
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El usuario indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        return new WP_REST_Response(
            self::prepare_offers_response($user_id),
            200
        );
    }

    public static function update_user_offers(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('Identificador de usuario no válido.', 'garantias-online-360vo')],
                400
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El usuario indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $payload = $request->get_json_params();
        $offers  = isset($payload['offers']) && is_array($payload['offers']) ? $payload['offers'] : null;

        $previous_offers = self::collect_user_offers($user_id);
        $previous_snapshot = self::format_offers_for_log($previous_offers);

        if ($offers === null) {
            return new WP_Error(
                'go_offers_invalid_payload',
                __('El formato de datos enviado no es válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $normalized = [];
        $choices_map = self::get_offer_type_map();
        $scope_map   = self::get_offer_scope_map();

        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                return new WP_Error(
                    'go_offers_invalid_entry',
                    sprintf(
                        /* translators: %d: offer index */
                        __('La oferta %d no tiene el formato correcto.', 'garantias-online-360vo'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            $tipo = isset($offer['tipo_oferta']) ? sanitize_key($offer['tipo_oferta']) : '';
            if ($tipo === '' || ! isset($choices_map[$tipo])) {
                return new WP_Error(
                    'go_offers_invalid_type',
                    sprintf(
                        /* translators: %d: offer index */
                        __('Selecciona un tipo de oferta válido en la fila %d.', 'garantias-online-360vo'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            $nombre = isset($offer['nombre_oferta']) ? self::clean_text($offer['nombre_oferta']) : '';
            if ($tipo === 'personalizar' && $nombre === '') {
                return new WP_Error(
                    'go_offers_missing_name',
                    sprintf(
                        /* translators: %d: offer index */
                        __('Introduce un nombre personalizado en la fila %d.', 'garantias-online-360vo'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            $scope = isset($offer['aplicacion']) ? sanitize_key($offer['aplicacion']) : '';
            if ($scope === '' || ! isset($scope_map[$scope])) {
                return new WP_Error(
                    'go_offers_invalid_scope',
                    sprintf(
                        /* translators: %d: offer index */
                        __('Selecciona un ámbito de aplicación válido en la fila %d.', 'garantias-online-360vo'),
                        $index + 1
                    ),
                    ['status' => 400]
                );
            }

            $discount = null;
            if ($tipo !== 'sin_suplementos') {
                $raw_discount = isset($offer['porcentaje_descuento']) ? $offer['porcentaje_descuento'] : '';
                if ($raw_discount === '' || $raw_discount === null) {
                    return new WP_Error(
                        'go_offers_missing_discount',
                        sprintf(
                            __('Indica el porcentaje de descuento en la fila %d.', 'garantias-online-360vo'),
                            $index + 1
                        ),
                        ['status' => 400]
                    );
                }

                $discount = (float) $raw_discount;
                if ($discount <= 0 || $discount > 100) {
                    return new WP_Error(
                        'go_offers_invalid_discount',
                        sprintf(
                            __('El descuento de la fila %d debe estar entre 1 y 100.', 'garantias-online-360vo'),
                            $index + 1
                        ),
                        ['status' => 400]
                    );
                }
            }

            $caducidad_iso = isset($offer['caducidad_iso']) ? sanitize_text_field($offer['caducidad_iso']) : '';
            $caducidad     = '';
            if ($caducidad_iso !== '') {
                $caducidad = self::format_date_for_storage($caducidad_iso);
                if ($caducidad === '') {
                    return new WP_Error(
                        'go_offers_invalid_expiry',
                        sprintf(
                            __('La fecha de caducidad de la fila %d no es válida.', 'garantias-online-360vo'),
                            $index + 1
                        ),
                        ['status' => 400]
                    );
                }
            }

            $seleccion = [];
            if ($scope === 'seleccion' && isset($offer['seleccion_modalidad']) && is_array($offer['seleccion_modalidad'])) {
                foreach ($offer['seleccion_modalidad'] as $modalidad_id) {
                    $modalidad_id = (int) $modalidad_id;
                    if ($modalidad_id > 0) {
                        $seleccion[] = $modalidad_id;
                    }
                }

                if (empty($seleccion)) {
                    return new WP_Error(
                        'go_offers_missing_modalities',
                        sprintf(
                            __('Selecciona al menos una modalidad en la fila %d.', 'garantias-online-360vo'),
                            $index + 1
                        ),
                        ['status' => 400]
                    );
                }
            }

            $estado = isset($offer['estado']) ? (bool) $offer['estado'] : true;

            $normalized[] = [
                'tipo_oferta'          => [
                    'value' => $tipo,
                    'label' => $choices_map[$tipo],
                ],
                'nombre_oferta'        => $nombre,
                'porcentaje_descuento' => $tipo === 'sin_suplementos' ? null : $discount,
                'aplicacion'           => [
                    'value' => $scope,
                    'label' => $scope_map[$scope],
                ],
                'caducidad_oferta'     => $caducidad,
                'seleccion_modalidad'  => $seleccion,
                'estado'               => $estado,
            ];
        }

        if (! function_exists('update_field')) {
            return new WP_Error(
                'go_offers_acf_missing',
                __('No se ha podido guardar la información de ofertas.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $desired_snapshot = self::format_offers_for_log($normalized);
        $result = update_field('ofertas_y_descuentos', ['ofertas' => $normalized], 'user_' . $user_id);
        self::$clients_with_active_offers = null;
        self::$active_offers_cache      = [];

        if ($result === false) {
            $stored_snapshot = self::format_offers_for_log(self::collect_user_offers($user_id));
            if ($stored_snapshot !== $desired_snapshot) {
                return new WP_Error(
                    'go_offers_save_failed',
                    __('Ha ocurrido un error al guardar las ofertas.', 'garantias-online-360vo'),
                    ['status' => 500]
                );
            }

            $updated_snapshot = $stored_snapshot;
        } else {
            $updated_snapshot = $desired_snapshot;
        }

        if ($previous_snapshot !== $updated_snapshot) {
            $context = array_merge(
                self::build_client_log_context($user),
                [
                    'offers_before'      => $previous_snapshot,
                    'offers_after'       => $updated_snapshot,
                    'offer_count_before' => count($previous_snapshot),
                    'offer_count_after'  => count($updated_snapshot),
                ]
            );

            ActivityLogger::log(
                'client.offers_updated',
                [
                    'target_type' => 'user',
                    'target_id'   => (int) $user->ID,
                    'context'     => $context,
                ]
            );
        }

        return new WP_REST_Response(
            self::prepare_offers_response($user_id),
            200
        );
    }

    public static function generate_pending_sepa(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $sepa_fields = AccountRestController::get_array_param($request, 'sepa');
        $reference_param = is_string($request->get_param('reference')) ? (string) $request->get_param('reference') : '';
        $generated_at_param = is_string($request->get_param('generated_at')) ? (string) $request->get_param('generated_at') : '';
        $signature_locality_param = is_string($request->get_param('signature_locality')) ? (string) $request->get_param('signature_locality') : '';
        $signature_date_param = is_string($request->get_param('signature_date')) ? (string) $request->get_param('signature_date') : '';

        $files = $request->get_file_params();
        $pending_file = is_array($files) ? ($files['sepa_pending'] ?? ($files['file'] ?? null)) : null;
        if (! is_array($pending_file) || empty($pending_file['tmp_name'])) {
            return new WP_REST_Response(
                ['message' => __('No se ha recibido el mandato SEPA generado.', 'garantias-online-360vo')],
                400
            );
        }

        $sanitized = AccountRestController::sanitize_sepa_request(
            $sepa_fields,
            $reference_param,
            $generated_at_param,
            $signature_locality_param,
            $signature_date_param
        );
        if (is_wp_error($sanitized)) {
            $status = (int) ($sanitized->get_error_data()['status'] ?? 400);

            return new WP_REST_Response(
                ['message' => $sanitized->get_error_message()],
                $status > 0 ? $status : 400
            );
        }

        $file_validation = AccountRestController::validate_pending_file($pending_file);
        if (is_wp_error($file_validation)) {
            $status = (int) ($file_validation->get_error_data()['status'] ?? 400);

            return new WP_REST_Response(
                ['message' => $file_validation->get_error_message()],
                $status > 0 ? $status : 400
            );
        }

        $binary = file_get_contents($pending_file['tmp_name']);
        if (! is_string($binary) || $binary === '') {
            return new WP_REST_Response(
                ['message' => __('No se ha podido procesar el mandato SEPA generado.', 'garantias-online-360vo')],
                500
            );
        }

        $filename = AccountRestController::resolve_pending_filename($pending_file['name'] ?? '', $sanitized['reference']);
        $context = [
            'filename'     => $filename,
            'reference'    => $sanitized['reference'],
            'generated_at' => $sanitized['generated_at'],
        ];

        $stored = SepaMandateService::store_pending_mandate($user_id, $binary, $context);
        if (is_wp_error($stored)) {
            $status = (int) ($stored->get_error_data()['status'] ?? 500);

            return new WP_REST_Response(
                ['message' => $stored->get_error_message()],
                $status > 0 ? $status : 500
            );
        }

        SepaMandateService::clear_signed_mandate($user_id);

        $normalized_document = SepaMandateService::normalize_document(
            $stored,
            $user_id,
            SepaMandateService::TYPE_PENDING
        );

        AccountRestController::update_sepa_acf_snapshot($user_id, $sanitized, $normalized_document);
        AccountRestController::persist_sepa_meta_snapshot($user_id, $sanitized);
        AccountRestController::notify_user_pending_mandate($user_id, $normalized_document, $binary);

        $account = AccountViewModel::from_user($user);
        $payments = $account['payments'] ?? [];
        $sepa_details = self::format_sepa_details($payments);
        $payment_info = self::format_payment($payments);

        $log_context = array_merge(
            self::build_client_log_context($user),
            [
                'document_reference' => self::clean_text($normalized_document['reference'] ?? ''),
                'document_name'      => self::clean_text($normalized_document['filename'] ?? ''),
                'generated_by_admin' => true,
            ]
        );

        $actor = wp_get_current_user();
        $actor_id = ($actor instanceof WP_User) ? (int) $actor->ID : 0;
        if ($actor instanceof WP_User) {
            $log_context['actor_name']  = self::clean_text($actor->display_name);
            $log_context['actor_email'] = sanitize_email($actor->user_email);
        }

        ActivityLogger::log(
            'sepa.pending_generated',
            [
                'actor_id'    => $actor_id,
                'target_type' => 'user',
                'target_id'   => (int) $user->ID,
                'context'     => $log_context,
            ]
        );

        return new WP_REST_Response(
            [
                'message'  => __('Mandato SEPA generado y enviado al profesional.', 'garantias-online-360vo'),
                'document' => $normalized_document,
                'sepa'     => $sepa_details,
                'payment'  => $payment_info,
            ],
            200
        );
    }

    public static function activate_sepa(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $signed_meta = SepaMandateService::get_document_meta($user_id, SepaMandateService::TYPE_SIGNED);
        if (empty($signed_meta['hash'])) {
            return new WP_REST_Response(
                ['message' => __('No hay un mandato SEPA firmado para validar.', 'garantias-online-360vo')],
                400
            );
        }

        SepaMandateService::set_activation_flag($user_id, true);
        SepaMandateService::clear_disabled_message($user_id);

        $account = AccountViewModel::from_user($user);
        $payments = $account['payments'] ?? [];
        $sepa_details = self::format_sepa_details($payments);
        $payment_info = self::format_payment($payments);

        $account_user = is_array($account['user'] ?? null) ? $account['user'] : [];
        $name_data = is_array($account_user['name'] ?? null) ? $account_user['name'] : [];
        $full_name = self::clean_text($account_user['full_name'] ?? '');
        if ($full_name === '' && ! empty($name_data)) {
            $full_name = self::clean_text($name_data['full'] ?? '');
            if ($full_name === '') {
                $parts = array_filter([
                    self::clean_text($name_data['first'] ?? ''),
                    self::clean_text($name_data['last'] ?? ''),
                ]);
                if (! empty($parts)) {
                    $full_name = trim(implode(' ', $parts));
                }
            }
            if ($full_name === '') {
                $full_name = self::clean_text($name_data['personal'] ?? '');
            }
        }
        if ($full_name === '') {
            $full_name = self::clean_text($user->display_name);
        }

        $company_data = is_array($account_user['company'] ?? null) ? $account_user['company'] : [];
        $company_name = self::clean_text($company_data['name'] ?? '');
        if ($company_name === '') {
            $company_name = self::clean_text($company_data['trade_name'] ?? '');
        }

        $reference = self::clean_text($signed_meta['reference'] ?? '');

        $actor_label = $full_name;
        if ($company_name !== '' && $full_name !== '' && strcasecmp($company_name, $full_name) !== 0) {
            $actor_label = sprintf('%s (%s)', $full_name, $company_name);
        } elseif ($full_name === '' && $company_name !== '') {
            $actor_label = $company_name;
        }

        $context = array_merge(
            self::build_client_log_context($user),
            [
                'user_name'          => $actor_label,
                'user_email'         => sanitize_email($user->user_email),
                'company_name'       => $company_name,
                'document_reference' => $reference,
                'status_label'       => isset($sepa_details['label']) ? (string) $sepa_details['label'] : '',
                'profile_url'        => self::build_client_profile_url($user),
            ]
        );

        ActivityLogger::log(
            'sepa.activated',
            [
                'target_type' => 'user',
                'target_id'   => (int) $user->ID,
                'context'     => $context,
            ]
        );

        $notify_options = [];
        $actor = wp_get_current_user();
        if ($actor instanceof WP_User) {
            $notify_options['actor_name'] = self::clean_text($actor->display_name);
            $notify_options['actor_email'] = sanitize_email($actor->user_email);
        }

        AccountRestController::notify_sepa_activation($user_id, $sepa_details, $notify_options);

        return new WP_REST_Response(
            [
                'message' => __('Domiciliación bancaria activada.', 'garantias-online-360vo'),
                'sepa'    => $sepa_details,
                'payment' => $payment_info,
            ],
            200
        );
    }

    private static function build_client_profile_url(WP_User $user): string
    {
        $slug = $user->user_nicename !== '' ? $user->user_nicename : $user->user_login;
        $slug = sanitize_title($slug);
        if ($slug === '') {
            return '';
        }

        return trailingslashit(home_url('/garantias-online/clientes/' . rawurlencode($slug)));
    }

    public static function deactivate_sepa(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $status_payload = SepaMandateService::get_status($user_id);
        if ($status_payload['value'] === SepaMandateService::STATUS_DISABLED) {
            return new WP_REST_Response(
                ['message' => __('La domiciliación bancaria ya está deshabilitada.', 'garantias-online-360vo')],
                400
            );
        }

        $reason_param = $request->get_param('reason');
        $reason = is_string($reason_param) ? trim($reason_param) : '';

        if ($reason === '') {
            return new WP_REST_Response(
                ['message' => __('Debes indicar el motivo de la deshabilitación.', 'garantias-online-360vo')],
                400
            );
        }

        SepaMandateService::set_status($user_id, SepaMandateService::STATUS_DISABLED);
        SepaMandateService::set_activation_flag($user_id, false, SepaMandateService::ACTIVATION_DISABLED);
        SepaMandateService::set_disabled_message($user_id, $reason);

        $account = AccountViewModel::from_user($user);
        $payments = $account['payments'] ?? [];
        $sepa_details = self::format_sepa_details($payments);
        $payment_info = self::format_payment($payments);

        $account_user = is_array($account['user'] ?? null) ? $account['user'] : [];
        $name_data = is_array($account_user['name'] ?? null) ? $account_user['name'] : [];
        $full_name = self::clean_text($account_user['full_name'] ?? '');
        if ($full_name === '' && ! empty($name_data)) {
            $full_name = self::clean_text($name_data['full'] ?? '');
            if ($full_name === '') {
                $parts = array_filter([
                    self::clean_text($name_data['first'] ?? ''),
                    self::clean_text($name_data['last'] ?? ''),
                ]);
                if (! empty($parts)) {
                    $full_name = trim(implode(' ', $parts));
                }
            }
            if ($full_name === '') {
                $full_name = self::clean_text($name_data['personal'] ?? '');
            }
        }
        if ($full_name === '') {
            $full_name = self::clean_text($user->display_name);
        }

        $company_data = is_array($account_user['company'] ?? null) ? $account_user['company'] : [];
        $company_name = self::clean_text($company_data['name'] ?? '');
        if ($company_name === '') {
            $company_name = self::clean_text($company_data['trade_name'] ?? '');
        }

        $reference = '';
        $signed_meta = SepaMandateService::get_document_meta($user_id, SepaMandateService::TYPE_SIGNED);
        if (! empty($signed_meta['reference'])) {
            $reference = self::clean_text($signed_meta['reference']);
        }

        $actor_label = $full_name;
        if ($company_name !== '' && $full_name !== '' && strcasecmp($company_name, $full_name) !== 0) {
            $actor_label = sprintf('%s (%s)', $full_name, $company_name);
        } elseif ($full_name === '' && $company_name !== '') {
            $actor_label = $company_name;
        }

        $context = array_merge(
            self::build_client_log_context($user),
            [
                'user_name'          => $actor_label,
                'document_reference' => $reference,
                'deactivation_reason' => self::clean_text($reason),
            ]
        );

        ActivityLogger::log(
            'sepa.deactivated',
            [
                'target_type' => 'user',
                'target_id'   => (int) $user->ID,
                'context'     => $context,
            ]
        );

        return new WP_REST_Response(
            [
                'message' => __('Domiciliación bancaria inhabilitada.', 'garantias-online-360vo'),
                'sepa'    => $sepa_details,
                'payment' => $payment_info,
            ],
            200
        );
    }

    public static function upload_signed_sepa(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $files = $request->get_file_params();
        $file  = is_array($files) ? ($files['sepa_signed'] ?? ($files['file'] ?? null)) : null;

        if (! is_array($file) || empty($file['tmp_name'])) {
            return new WP_REST_Response(
                ['message' => __('Selecciona un mandato SEPA en formato PDF.', 'garantias-online-360vo')],
                400
            );
        }

        $stored = self::handle_admin_signed_upload($user_id, $file);
        if (is_wp_error($stored)) {
            $status = (int) ($stored->get_error_data()['status'] ?? 400);

            return new WP_REST_Response(
                ['message' => $stored->get_error_message()],
                $status > 0 ? $status : 400
            );
        }

        $account = AccountViewModel::from_user($user);
        $payments = $account['payments'] ?? [];
        $sepa_details = self::format_sepa_details($payments);
        $payment_info = self::format_payment($payments);

        $actor = wp_get_current_user();
        $actor_id = ($actor instanceof WP_User) ? (int) $actor->ID : 0;

        $account_user = is_array($account['user'] ?? null) ? $account['user'] : [];
        $name_data = is_array($account_user['name'] ?? null) ? $account_user['name'] : [];
        $full_name = self::clean_text($account_user['full_name'] ?? '');
        if ($full_name === '' && ! empty($name_data)) {
            $full_name = self::clean_text($name_data['full'] ?? '');
            if ($full_name === '') {
                $parts = array_filter([
                    self::clean_text($name_data['first'] ?? ''),
                    self::clean_text($name_data['last'] ?? ''),
                ]);
                if (! empty($parts)) {
                    $full_name = trim(implode(' ', $parts));
                }
            }
            if ($full_name === '') {
                $full_name = self::clean_text($name_data['personal'] ?? '');
            }
        }
        if ($full_name === '') {
            $full_name = self::clean_text($user->display_name);
        }

        $company_data = is_array($account_user['company'] ?? null) ? $account_user['company'] : [];
        $company_name = self::clean_text($company_data['name'] ?? '');
        if ($company_name === '') {
            $company_name = self::clean_text($company_data['trade_name'] ?? '');
        }

        $reference = self::clean_text($stored['reference'] ?? '');
        $status_label = isset($sepa_details['label']) ? (string) $sepa_details['label'] : '';

        $actor_label = $full_name;
        if ($company_name !== '' && $full_name !== '' && strcasecmp($company_name, $full_name) !== 0) {
            $actor_label = sprintf('%s (%s)', $full_name, $company_name);
        } elseif ($full_name === '' && $company_name !== '') {
            $actor_label = $company_name;
        }

        $context = array_merge(
            self::build_client_log_context($user),
            [
                'user_name'          => $actor_label,
                'user_email'         => sanitize_email($user->user_email),
                'company_name'       => $company_name,
                'document_reference' => $reference,
                'document_name'      => self::clean_text($stored['filename'] ?? ''),
                'status_label'       => $status_label,
                'profile_url'        => self::build_client_profile_url($user),
                'uploaded_by_admin'  => true,
            ]
        );

        if ($actor instanceof WP_User) {
            $context['actor_name']       = self::clean_text($actor->display_name);
            $context['actor_email']      = sanitize_email($actor->user_email);
            $context['uploaded_by']      = self::clean_text($actor->display_name);
            $context['uploaded_by_email'] = sanitize_email($actor->user_email);
        }

        ActivityLogger::log(
            'sepa.activated',
            [
                'actor_id'    => $actor_id,
                'target_type' => 'user',
                'target_id'   => (int) $user->ID,
                'context'     => $context,
            ]
        );

        $notify_options = [
            'uploaded_by_admin' => true,
        ];

        if ($actor instanceof WP_User) {
            $notify_options['actor_name'] = self::clean_text($actor->display_name);
            $notify_options['actor_email'] = sanitize_email($actor->user_email);
        }

        AccountRestController::notify_sepa_activation($user_id, $sepa_details, $notify_options);

        return new WP_REST_Response(
            [
                'message'  => __('Mandato SEPA firmado actualizado.', 'garantias-online-360vo'),
                'document' => $stored,
                'sepa'     => $sepa_details,
                'payment'  => $payment_info,
            ],
            200
        );
    }

    public static function get_items(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');

        if ($per_page <= 0) {
            $per_page = 20;
        }

        $per_page = min($per_page, 50);

        $args = [
            'role__in' => self::get_supported_roles(),
            'number'   => $per_page,
            'offset'   => ($page - 1) * $per_page,
            'orderby'  => 'registered',
            'order'    => 'DESC',
        ];

        $search = $request->get_param('search');
        if (is_string($search) && $search !== '') {
            $search = trim($search);
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
        }

        $channel = $request->get_param('channel');
        if (is_string($channel) && $channel !== '') {
            $channel = sanitize_key($channel);
            $role_for_channel = self::map_channel_to_role($channel);
            if ($role_for_channel !== '') {
                $args['role__in'] = [$role_for_channel];
            }
        }

        $quick_filter = self::normalize_quick_filter($request->get_param('quick_filter'));

        if ($quick_filter !== '') {
            if ($quick_filter === 'pendiente_pago' || $quick_filter === 'sin_finalizar') {
                $states = $quick_filter === 'pendiente_pago' ? ['pendiente_pago'] : ['sin_finalizar'];
                $include_ids = self::get_client_ids_with_guarantee_states($states);
                if (empty($include_ids)) {
                    return self::build_empty_clients_response($page, $per_page, $quick_filter);
                }
                $args['include'] = $include_ids;
                $args['orderby'] = 'include';
            } elseif ($quick_filter === 'sin_comercial') {
                $assigned = self::get_client_ids_with_commercial_assignments();
                if (! empty($assigned)) {
                    $args['exclude'] = $assigned;
                }
            } elseif ($quick_filter === 'sin_ofertas') {
                $active_offers = self::get_client_ids_with_active_offers();
                if (! empty($active_offers)) {
                    $args['exclude'] = $active_offers;
                }
            }
        }

        if (isset($args['include']) && empty($args['include'])) {
            return self::build_empty_clients_response($page, $per_page, $quick_filter);
        }

        $query   = new WP_User_Query($args);
        $results = $query->get_results();
        $items   = [];

        foreach ($results as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $items[] = self::prepare_item($user);
        }

        $total       = (int) $query->get_total();
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

        $response = [
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'items'       => array_values(array_filter($items)),
            'filters'     => [
                'channels' => self::get_channel_filters(),
            ],
            'quick_actions' => self::get_quick_filter_counts(),
            'quick_filter' => $quick_filter,
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function get_statuses(WP_REST_Request $request): WP_REST_Response
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $ids_param = $request->get_param('ids');
        $raw_ids   = [];

        if (is_array($ids_param)) {
            $raw_ids = $ids_param;
        } elseif (is_string($ids_param) && $ids_param !== '') {
            $raw_ids = preg_split('/[\s,]+/', $ids_param) ?: [];
        }

        $normalized = [];
        foreach ($raw_ids as $value) {
            $id = (int) $value;
            if ($id <= 0 || isset($normalized[$id])) {
                continue;
            }

            $normalized[$id] = $id;
        }

        $limited = array_slice(array_values($normalized), 0, 200);

        $items = array_map(
            static function (int $user_id): array {
                return [
                    'id'     => $user_id,
                    'online' => self::is_user_online($user_id),
                ];
            },
            $limited
        );

        return new WP_REST_Response([
            'items' => $items,
        ], 200);
    }

    public static function search_commercials(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $per_page = (int) $request->get_param('per_page');
        if ($per_page <= 0) {
            $per_page = 20;
        }
        $per_page = min($per_page, 50);

        $args = [
            'role__in' => self::get_commercial_roles(),
            'number'   => $per_page,
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ];

        $search = $request->get_param('search');
        if (is_string($search) && $search !== '') {
            $search = trim($search);
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name', 'first_name', 'last_name'];
        }

        $query   = new WP_User_Query($args);
        $results = $query->get_results();
        $items   = [];

        foreach ($results as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $items[] = self::format_commercial_user($user);
        }

        return new WP_REST_Response([
            'items' => array_values(array_filter($items)),
        ], 200);
    }

    public static function update_commercials(WP_REST_Request $request)
    {
        if (! self::permissions_check($request)) {
            return new WP_REST_Response(
                ['message' => __('Acceso denegado', 'garantias-online-360vo')],
                403
            );
        }

        $client_id = isset($request['id']) ? (int) $request['id'] : 0;
        if ($client_id <= 0) {
            return new WP_REST_Response(
                ['message' => __('Identificador de cliente no válido.', 'garantias-online-360vo')],
                400
            );
        }

        $user = get_user_by('id', $client_id);
        if (! $user instanceof WP_User) {
            return new WP_REST_Response(
                ['message' => __('El cliente indicado no existe.', 'garantias-online-360vo')],
                404
            );
        }

        $params = $request->get_json_params();
        $commercial_ids = isset($params['commercial_ids']) ? (array) $params['commercial_ids'] : [];

        $previous_assignments = self::get_assigned_commercial_ids($client_id);

        $normalized = [];
        foreach ($commercial_ids as $value) {
            $id = (int) $value;
            if ($id <= 0 || isset($normalized[$id])) {
                continue;
            }

            if (! self::is_valid_commercial($id)) {
                continue;
            }

            $normalized[$id] = $id;
        }

        $ids = array_values($normalized);

        if (function_exists('update_field')) {
            update_field('ajustes_usuarios_comercial_asignado', $ids, 'user_' . $client_id);
        }
        update_user_meta($client_id, 'ajustes_usuarios_comercial_asignado', $ids);

        self::reset_commercial_client_counts();
        self::$clients_with_commercial_assignments = null;

        $sorted_previous = $previous_assignments;
        $sorted_current  = $ids;
        sort($sorted_previous);
        sort($sorted_current);

        if ($sorted_previous !== $sorted_current) {
            $added   = array_values(array_diff($sorted_current, $sorted_previous));
            $removed = array_values(array_diff($sorted_previous, $sorted_current));

            $context = array_merge(
                self::build_client_log_context($user),
                [
                    'commercials_before' => self::build_commercial_log_entries($previous_assignments),
                    'commercials_after'  => self::build_commercial_log_entries($ids),
                    'commercials_added'  => self::build_commercial_log_entries($added),
                    'commercials_removed'=> self::build_commercial_log_entries($removed),
                    'commercial_count_before' => count($previous_assignments),
                    'commercial_count_after'  => count($ids),
                ]
            );

            ActivityLogger::log(
                'client.commercials_updated',
                [
                    'target_type' => 'user',
                    'target_id'   => (int) $user->ID,
                    'context'     => $context,
                ]
            );
        }

        $account     = AccountViewModel::from_user($user);
        $commercials = self::format_commercials($account['commercials'] ?? []);

        return new WP_REST_Response([
            'commercials' => $commercials,
        ], 200);
    }

    private static function build_client_log_context(WP_User $user): array
    {
        $name = self::clean_text($user->display_name);
        if ($name === '') {
            $name = sanitize_user($user->user_login, true);
        }

        $roles = array_map('sanitize_key', (array) $user->roles);

        return [
            'client_id'       => (int) $user->ID,
            'client_name'     => $name,
            'client_email'    => sanitize_email($user->user_email),
            'client_username' => sanitize_user($user->user_login, true),
            'client_roles'    => array_values(array_filter($roles)),
            'vendor_id'       => (int) $user->ID,
        ];
    }

    private static function format_offers_for_log(array $offers): array
    {
        $formatted = [];

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $type_value = '';
            $type_label = '';
            if (isset($offer['tipo_oferta'])) {
                if (is_array($offer['tipo_oferta'])) {
                    $type_value = sanitize_key($offer['tipo_oferta']['value'] ?? '');
                    $type_label = self::clean_text($offer['tipo_oferta']['label'] ?? '');
                } else {
                    $type_value = sanitize_key((string) $offer['tipo_oferta']);
                    $type_label = self::clean_text((string) $offer['tipo_oferta']);
                }
            }

            $scope_value = '';
            $scope_label = '';
            if (isset($offer['aplicacion'])) {
                if (is_array($offer['aplicacion'])) {
                    $scope_value = sanitize_key($offer['aplicacion']['value'] ?? '');
                    $scope_label = self::clean_text($offer['aplicacion']['label'] ?? '');
                } else {
                    $scope_value = sanitize_key((string) $offer['aplicacion']);
                    $scope_label = self::clean_text((string) $offer['aplicacion']);
                }
            }

            $selection = [];
            if (! empty($offer['seleccion_modalidad']) && is_array($offer['seleccion_modalidad'])) {
                foreach ($offer['seleccion_modalidad'] as $modalidad_id) {
                    $modalidad_id = (int) $modalidad_id;
                    if ($modalidad_id > 0) {
                        $selection[] = $modalidad_id;
                    }
                }
            }
            sort($selection);
            $selection = array_values(array_unique($selection));

            $discount = null;
            if (isset($offer['porcentaje_descuento']) && $offer['porcentaje_descuento'] !== '' && $offer['porcentaje_descuento'] !== null) {
                $discount = (float) $offer['porcentaje_descuento'];
            }

            $expires = '';
            if (isset($offer['caducidad_iso']) && $offer['caducidad_iso'] !== '') {
                $expires = sanitize_text_field((string) $offer['caducidad_iso']);
            } elseif (isset($offer['caducidad_oferta']) && $offer['caducidad_oferta'] !== '') {
                $expires = sanitize_text_field((string) $offer['caducidad_oferta']);
            }

            $formatted[] = [
                'type'      => [
                    'value' => $type_value,
                    'label' => $type_label,
                ],
                'name'      => self::clean_text($offer['nombre_oferta'] ?? ''),
                'scope'     => [
                    'value' => $scope_value,
                    'label' => $scope_label,
                ],
                'discount'  => $discount,
                'expires'   => $expires,
                'selection' => $selection,
                'active'    => isset($offer['estado']) ? (bool) $offer['estado'] : true,
            ];
        }

        return array_values($formatted);
    }

    private static function get_assigned_commercial_ids(int $client_id): array
    {
        if ($client_id <= 0) {
            return [];
        }

        $stored = get_user_meta($client_id, 'ajustes_usuarios_comercial_asignado', true);

        $ids = [];
        if (is_array($stored)) {
            foreach ($stored as $value) {
                $value = (int) $value;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        } elseif (is_numeric($stored)) {
            $value = (int) $stored;
            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function build_commercial_log_entries(array $commercial_ids): array
    {
        $entries = [];

        foreach ($commercial_ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }

            $commercial = get_user_by('id', $id);
            if (! $commercial instanceof WP_User) {
                continue;
            }

            $entries[] = [
                'id'       => $id,
                'name'     => self::clean_text($commercial->display_name),
                'email'    => sanitize_email($commercial->user_email),
                'username' => sanitize_user($commercial->user_login, true),
            ];
        }

        return array_values($entries);
    }

    private static function prepare_item(WP_User $user): array
    {
        $account   = AccountViewModel::from_user($user);
        $user_data = $account['user'] ?? [];

        $raw_personal_name = self::clean_text($user_data['name'] ?? $user->display_name);
        $first_name       = self::clean_text($user_data['first_name'] ?? '');
        $last_name        = self::clean_text($user_data['last_name'] ?? '');
        $full_name_meta   = self::clean_text($user_data['full_name'] ?? '');
        $full_name        = trim($first_name . ' ' . $last_name);
        if ($full_name === '') {
            $full_name = $full_name_meta;
        }
        $personal_name = $full_name !== '' ? $full_name : $raw_personal_name;
        $company_name  = self::clean_text($user_data['company']['name'] ?? '');
        $registered    = self::format_registered($user_data['registered'] ?? $user->user_registered);

        $profile_image = $user_data['profile_image']['url'] ?? '';
        $avatar_url    = $profile_image !== ''
            ? $profile_image
            : ($user_data['avatar_url'] ?? get_avatar_url($user->ID));

        $sales_channel = self::resolve_sales_channel($user_data['company']['type'] ?? [], $user);
        $offers        = self::get_active_offers((int) $user->ID);
        $guarantees    = self::count_guarantees((int) $user->ID);
        $commercials   = self::format_commercials($account['commercials'] ?? []);

        $payments     = $account['payments'] ?? [];
        $payment_info = self::format_payment($payments);
        $sepa_details = self::format_sepa_details($payments);
        $documents    = self::format_documents($account['documents'] ?? []);
        $web360       = self::format_web_service((int) $user->ID);

        $login_email = sanitize_email($user->user_email);
        $primary_email = sanitize_email($user_data['email'] ?? $login_email);
        if ($primary_email === '') {
            $primary_email = $login_email;
        }

        $contact = [
            'login_email'        => $login_email,
            'notification_email' => sanitize_email($user_data['notification_email'] ?? $primary_email ?: $login_email),
            'phone'              => self::clean_text($user_data['phone'] ?? ''),
        ];

        $address = $user_data['address'] ?? [];
        $address = [
            'street'  => self::clean_text($address['street'] ?? ''),
            'city'    => self::clean_text($address['city'] ?? ''),
            'state'   => self::clean_text($address['state'] ?? ''),
            'zip'     => self::clean_text($address['zip'] ?? ''),
            'country' => self::clean_text($address['country'] ?? ''),
        ];

        $links = [];
        if (current_user_can('manage_options')) {
            $links['admin'] = esc_url_raw(get_edit_user_link($user->ID));
        }

        return [
            'id'      => (int) $user->ID,
            'username' => sanitize_user($user->user_login, true),
            'slug'      => sanitize_user($user->user_nicename !== '' ? $user->user_nicename : $user->user_login, true),
            'nicename'  => sanitize_user($user->user_nicename, true),
            'profile' => [
                'avatar'   => esc_url_raw($avatar_url),
                'initials' => self::initials($personal_name !== '' ? $personal_name : $company_name),
            ],
            'name'       => [
                'personal' => $personal_name,
                'first'    => $first_name,
                'last'     => $last_name,
                'full'     => $full_name,
                'company'  => $company_name,
            ],
            'registered'   => $registered,
            'sales_channel'=> $sales_channel,
            'offers'       => $offers,
            'guarantees'   => [
                'count' => $guarantees,
            ],
            'payment'      => $payment_info,
            'commercials'  => $commercials,
            'contact'      => $contact,
            'company'      => [
                'name'  => $company_name,
                'legal_name' => self::clean_text($user_data['company']['legal_name'] ?? ''),
                'tax_id'=> self::clean_text($user_data['company']['tax_id'] ?? ''),
                'type'  => $sales_channel,
            ],
            'address'      => $address,
            'sepa'         => $sepa_details,
            'workshop'     => self::format_workshop($account['workshop'] ?? []),
            'documents'    => $documents,
            'services'     => [
                'web360' => $web360,
            ],
            'links'        => $links,
            'status'       => [
                'online' => self::is_user_online((int) $user->ID),
            ],
        ];
    }

    public static function record_heartbeat(WP_REST_Request $request): WP_REST_Response
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response([
                'message' => __('No se ha podido validar la sesión del usuario.', 'garantias-online-360vo'),
            ], 401);
        }

        $status_param = $request->get_param('status');
        $status = 'active';
        if (is_string($status_param)) {
            $candidate = strtolower(trim($status_param));
            if (in_array($candidate, ['active', 'inactive'], true)) {
                $status = $candidate;
            }
        }

        $timestamp = time();
        self::set_presence_state($user_id, $status, $timestamp);

        return new WP_REST_Response([
            'status'    => $status,
            'timestamp' => $timestamp,
        ], 200);
    }

    public static function heartbeat_permissions_check(): bool
    {
        return is_user_logged_in();
    }

    private static function get_presence_transient_key(int $user_id): string
    {
        return self::PRESENCE_TRANSIENT_PREFIX . $user_id;
    }

    /**
     * @return array{status:string,timestamp:int}
     */
    private static function get_presence_state(int $user_id): array
    {
        if ($user_id <= 0) {
            return [
                'status'    => 'unknown',
                'timestamp' => 0,
            ];
        }

        if (isset(self::$presence_cache[$user_id])) {
            return self::$presence_cache[$user_id];
        }

        $stored = get_transient(self::get_presence_transient_key($user_id));
        if (! is_array($stored)) {
            self::$presence_cache[$user_id] = [
                'status'    => 'inactive',
                'timestamp' => time(),
            ];

            return self::$presence_cache[$user_id];
        }

        $status = isset($stored['status']) ? (string) $stored['status'] : 'inactive';
        $timestamp = isset($stored['timestamp']) ? (int) $stored['timestamp'] : 0;

        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = 'inactive';
        }

        if ($timestamp <= 0) {
            $timestamp = time();
        }

        self::$presence_cache[$user_id] = [
            'status'    => $status,
            'timestamp' => $timestamp,
        ];

        return self::$presence_cache[$user_id];
    }

    private static function set_presence_state(int $user_id, string $status, int $timestamp): void
    {
        if ($user_id <= 0) {
            return;
        }

        $normalized_status = in_array($status, ['active', 'inactive'], true) ? $status : 'unknown';
        $normalized_timestamp = $timestamp > 0 ? $timestamp : time();

        $data = [
            'status'    => $normalized_status,
            'timestamp' => $normalized_timestamp,
        ];

        self::$presence_cache[$user_id] = $data;
        set_transient(self::get_presence_transient_key($user_id), $data, self::PRESENCE_STORAGE_TTL);

        unset(self::$session_status_cache[$user_id]);
    }

    private static function is_user_online(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $presence = self::get_presence_state($user_id);
        $timestamp = (int) $presence['timestamp'];
        $status = (string) $presence['status'];

        if ($timestamp > 0 && in_array($status, ['active', 'inactive'], true)) {
            $age = time() - $timestamp;

            if ($status === 'active') {
                return $age <= self::PRESENCE_ACTIVE_GRACE;
            }

            return false;
        }

        return self::has_active_session($user_id);
    }

    private static function has_active_session(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (isset(self::$session_status_cache[$user_id])) {
            return self::$session_status_cache[$user_id];
        }

        if (! class_exists('\\WP_Session_Tokens')) {
            self::$session_status_cache[$user_id] = false;

            return false;
        }

        $manager = \WP_Session_Tokens::get_instance($user_id);
        if (! $manager) {
            self::$session_status_cache[$user_id] = false;

            return false;
        }

        $tokens = $manager->get_all();
        if (! is_array($tokens) || empty($tokens)) {
            self::$session_status_cache[$user_id] = false;

            return false;
        }

        $now = time();
        foreach ($tokens as $token) {
            $expiration = isset($token['expiration']) ? (int) $token['expiration'] : 0;
            if ($expiration > $now) {
                self::$session_status_cache[$user_id] = true;

                return true;
            }
        }

        self::$session_status_cache[$user_id] = false;

        return false;
    }

    private static function get_commercial_roles(): array
    {
        return ['go_comercial', 'go_director_comercial'];
    }

    private static function is_valid_commercial(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return false;
        }

        $roles = array_map('strval', (array) $user->roles);
        foreach (self::get_commercial_roles() as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private static function format_commercial_user(WP_User $user): array
    {
        $first_name = self::clean_text(get_user_meta($user->ID, 'first_name', true));
        $last_name  = self::clean_text(get_user_meta($user->ID, 'last_name', true));
        $full_name  = trim($first_name . ' ' . $last_name);
        if ($full_name === '') {
            $full_name = self::clean_text($user->display_name);
        }

        $email = sanitize_email($user->user_email);
        $phone = self::clean_text(get_user_meta($user->ID, 'phone_contacto', true));
        if ($phone === '') {
            $phone = self::clean_text(get_user_meta($user->ID, 'ajustes_usuarios_telefono', true));
        }

        $initials_source = $full_name !== '' ? $full_name : $user->display_name;

        return [
            'id'         => (int) $user->ID,
            'name'       => $full_name !== '' ? $full_name : $user->display_name,
            'full_name'  => $full_name,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'phone'      => $phone,
            'avatar'     => esc_url_raw(get_avatar_url($user->ID)),
            'initials'   => self::initials($initials_source),
            'client_count' => self::get_commercial_client_count((int) $user->ID),
        ];
    }

    private static function reset_commercial_client_counts(): void
    {
        self::$commercial_client_counts = null;
    }

    private static function get_commercial_client_counts(): array
    {
        if (is_array(self::$commercial_client_counts)) {
            return self::$commercial_client_counts;
        }

        global $wpdb;

        if (! isset($wpdb->usermeta)) {
            self::$commercial_client_counts = [];

            return self::$commercial_client_counts;
        }

        $table    = $wpdb->usermeta;
        $meta_key = 'ajustes_usuarios_comercial_asignado';
        $values   = $wpdb->get_col($wpdb->prepare("SELECT meta_value FROM {$table} WHERE meta_key = %s", $meta_key));

        $counts = [];

        if (is_array($values)) {
            foreach ($values as $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $decoded = maybe_unserialize($value);

                if (is_array($decoded)) {
                    $ids = array_unique(array_map('intval', $decoded));
                    foreach ($ids as $id) {
                        if ($id > 0) {
                            $counts[$id] = ($counts[$id] ?? 0) + 1;
                        }
                    }
                    continue;
                }

                $id = (int) $decoded;
                if ($id > 0) {
                    $counts[$id] = ($counts[$id] ?? 0) + 1;
                }
            }
        }

        self::$commercial_client_counts = $counts;

        return self::$commercial_client_counts;
    }

    private static function get_commercial_client_count(int $commercial_id): int
    {
        if ($commercial_id <= 0) {
            return 0;
        }

        $counts = self::get_commercial_client_counts();

        return isset($counts[$commercial_id]) ? (int) $counts[$commercial_id] : 0;
    }

    private static function format_workshop($workshop): array
    {
        if (! is_array($workshop)) {
            $workshop = [];
        }

        return [
            'has_workshop'   => ! empty($workshop['has_workshop']),
            'name'           => self::clean_text($workshop['name'] ?? ''),
            'fiscal_name'    => self::clean_text($workshop['fiscal_name'] ?? ''),
            'tax_id'         => self::clean_text($workshop['tax_id'] ?? ''),
            'contact_person' => self::clean_text($workshop['contact_person'] ?? ''),
            'phone'          => self::clean_text($workshop['phone'] ?? ''),
            'email'          => sanitize_email($workshop['email'] ?? ''),
            'address'        => self::clean_text($workshop['address'] ?? ''),
        ];
    }

    private static function clean_text($value): string
    {
        if (is_string($value)) {
            return trim(wp_strip_all_tags($value));
        }

        return '';
    }

    private static function format_registered($value): array
    {
        if (! is_string($value) || $value === '') {
            return [
                'raw'     => '',
                'display' => '',
                'iso'     => '',
            ];
        }

        $display = mysql2date('d/m/y', $value);
        $iso     = mysql2date('c', $value);

        return [
            'raw'     => $value,
            'display' => $display ?: '',
            'iso'     => $iso ?: '',
        ];
    }

    private static function format_sales_channel($type): array
    {
        if (is_array($type)) {
            $value = sanitize_key($type['value'] ?? '');
            $label = self::clean_text($type['label'] ?? '');

            if ($label === '' && $value !== '') {
                $label = ucwords(str_replace(['_', '-'], ' ', $value));
            }

            return [
                'value' => $value,
                'label' => $label,
            ];
        }

        $value = self::clean_text((string) $type);

        return [
            'value' => sanitize_key($value),
            'label' => $value,
        ];
    }

    private static function resolve_sales_channel($type, WP_User $user): array
    {
        $formatted = self::format_sales_channel($type);
        $roles = array_map('strval', (array) $user->roles);
        $channel_slug = self::map_role_to_channel_slug($roles);

        if ($channel_slug !== '') {
            $formatted['category'] = $channel_slug;

            if (($formatted['value'] ?? '') === '') {
                $formatted['value'] = $channel_slug;
            }

            if (($formatted['label'] ?? '') === '') {
                $formatted['label'] = self::get_channel_label($channel_slug);
            }
        } else {
            $formatted['category'] = '';
        }

        if (! isset($formatted['value'])) {
            $formatted['value'] = '';
        }

        if (! isset($formatted['label'])) {
            $formatted['label'] = '';
        }

        return $formatted;
    }

    public static function get_supported_roles(): array
    {
        return ['go_profesional', 'go_particular', 'go_gestoria'];
    }

    private static function get_channel_role_map(): array
    {
        return [
            'profesional' => 'go_profesional',
            'particular'  => 'go_particular',
            'gestoria'    => 'go_gestoria',
        ];
    }

    private static function map_channel_to_role(string $channel): string
    {
        $map = self::get_channel_role_map();

        return $map[$channel] ?? '';
    }

    private static function map_role_to_channel_slug(array $roles): string
    {
        $map = self::get_channel_role_map();

        foreach ($map as $channel => $role) {
            if (in_array($role, $roles, true)) {
                return $channel;
            }
        }

        return '';
    }

    private static function get_channel_label(string $channel): string
    {
        switch ($channel) {
            case 'gestoria':
                return __('Gestoría', 'garantias-online-360vo');
            case 'particular':
                return __('Particular', 'garantias-online-360vo');
            case 'profesional':
                return __('Profesional', 'garantias-online-360vo');
            default:
                return '';
        }
    }

    private static function get_channel_filters(): array
    {
        $filters = [];
        $counts = count_users();
        $available_roles = is_array($counts) && isset($counts['avail_roles']) ? (array) $counts['avail_roles'] : [];
        $map = self::get_channel_role_map();

        foreach ($map as $channel => $role) {
            $count = (int) ($available_roles[$role] ?? 0);

            if ($count <= 0) {
                continue;
            }

            $label = self::get_channel_label($channel);

            if ($label === '') {
                continue;
            }

            $filters[] = [
                'value' => $channel,
                'label' => $label,
                'count' => $count,
            ];
        }

        return $filters;
    }


    private static function format_payment(array $payments): array
    {
        $selected = isset($payments['selected_method']) ? sanitize_key((string) $payments['selected_method']) : '';
        if ($selected === '') {
            $selected = 'transferencia';
        }

        $label = self::clean_text($payments['method_label'] ?? '');
        if ($label === '' && ! empty($payments['raw_method'])) {
            $label = self::clean_text((string) $payments['raw_method']);
        }

        if ($label === '') {
            $label = $selected === 'domiciliacion'
                ? __('Domiciliación bancaria', 'garantias-online-360vo')
                : __('Transferencia bancaria', 'garantias-online-360vo');
        }

        return [
            'method' => $selected,
            'label'  => $label,
        ];
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>|WP_Error
     */
    private static function handle_admin_signed_upload(int $user_id, array $file)
    {
        if (! isset($file['tmp_name']) || ! is_string($file['tmp_name']) || $file['tmp_name'] === '') {
            return new WP_Error(
                'go_client_sepa_upload',
                __('No se ha podido procesar el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if (! empty($file['error'])) {
            return new WP_Error(
                'go_client_sepa_upload',
                __('No se ha podido subir el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size > 5 * 1024 * 1024) {
            return new WP_Error(
                'go_client_sepa_size',
                __('El mandato SEPA firmado supera el tamaño permitido (5MB).', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if (! function_exists('wp_check_filetype_and_ext')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $original_name = isset($file['name']) ? (string) $file['name'] : 'mandato-sepa-firmado.pdf';
        $check = wp_check_filetype_and_ext($file['tmp_name'], $original_name, ['pdf' => 'application/pdf']);
        if (! is_array($check) || ($check['ext'] ?? '') !== 'pdf') {
            return new WP_Error(
                'go_client_sepa_type',
                __('El mandato SEPA debe estar en formato PDF.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $binary = file_get_contents($file['tmp_name']);
        if (! is_string($binary) || $binary === '') {
            return new WP_Error(
                'go_client_sepa_binary',
                __('No se ha podido leer el mandato SEPA firmado.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $filename = sanitize_file_name($original_name);
        if ($filename === '') {
            $filename = 'mandato-sepa-firmado.pdf';
        }

        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.pdf';
        } elseif (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
            $filename = sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME) ?: 'mandato-sepa-firmado') . '.pdf';
        }

        $pending_meta = SepaMandateService::get_document_meta($user_id, SepaMandateService::TYPE_PENDING);
        $context = [
            'filename'     => $filename,
            'generated_at' => gmdate('c'),
        ];

        if (! empty($pending_meta['reference'])) {
            $context['reference'] = $pending_meta['reference'];
        }

        $stored = SepaMandateService::store_signed_mandate($user_id, $binary, $context);
        if (is_wp_error($stored)) {
            return $stored;
        }

        SepaMandateService::set_activation_flag(
            $user_id,
            true,
            SepaMandateService::ACTIVATION_ENABLED
        );
        SepaMandateService::clear_disabled_message($user_id);

        $stored['submitted_at'] = current_time('timestamp');

        return $stored;
    }


    private static function format_sepa_details(array $payments): array
    {
        $sepa = $payments['sepa'] ?? [];
        if (! is_array($sepa)) {
            $sepa = [];
        }

        $status_code = isset($sepa['status_code']) ? sanitize_key((string) $sepa['status_code']) : '';
        $label = self::clean_text($sepa['status_label'] ?? '');
        if ($label === '') {
            $label = $status_code !== ''
                ? SepaMandateService::status_label($status_code)
                : __('Sin información del mandato', 'garantias-online-360vo');
        }

        $requested = ! empty($sepa['requested']);
        $activated = ! empty($sepa['activated']);
        $activation_state = isset($sepa['activation_state'])
            ? sanitize_key((string) $sepa['activation_state'])
            : '';

        if (! $activated && $activation_state === SepaMandateService::ACTIVATION_ENABLED) {
            $activated = true;
        }

        $payment_method = '';
        if (! empty($payments['selected_method'])) {
            $payment_method = sanitize_key((string) $payments['selected_method']);
        }
        if (! $activated && $payment_method === 'domiciliacion') {
            $activated = true;
        }
        $disabled_message = self::clean_text($sepa['disabled_message'] ?? '');

        $documents = [
            'pending' => [],
            'signed'  => [],
        ];

        if (! empty($sepa['documents']) && is_array($sepa['documents'])) {
            foreach (['pending', 'signed'] as $doc_type) {
                if (empty($sepa['documents'][$doc_type]) || ! is_array($sepa['documents'][$doc_type])) {
                    continue;
                }

                $doc = $sepa['documents'][$doc_type];
                $documents[$doc_type] = [
                    'id'           => isset($doc['id']) ? (int) $doc['id'] : 0,
                    'url'          => esc_url_raw((string) ($doc['url'] ?? '')),
                    'filename'     => sanitize_file_name((string) ($doc['filename'] ?? '')),
                    'hash'         => self::clean_text($doc['hash'] ?? ''),
                    'reference'    => self::clean_text($doc['reference'] ?? ''),
                    'generated_at' => self::clean_text($doc['generated_at'] ?? ''),
                    'private'      => ! empty($doc['private']),
                ];
            }
        }

        return [
            'label'            => $label,
            'status_code'      => $status_code,
            'requested'        => $requested,
            'activated'        => $activated,
            'activation_state' => $activation_state,
            'documents'        => $documents,
            'disabled_message' => $disabled_message,
        ];
    }

    private static function format_documents($documents): array
    {
        if (! is_array($documents)) {
            $documents = [];
        }

        $add = isset($documents['add_to_certificates']) ? (bool) $documents['add_to_certificates'] : false;
        $signature = $documents['signature'] ?? [];
        $seal = $documents['seal'] ?? [];

        return [
            'add_to_certificates' => $add,
            'signature'           => [
                'id'  => isset($signature['id']) ? (int) $signature['id'] : 0,
                'url' => esc_url_raw($signature['url'] ?? ''),
            ],
            'seal'                => [
                'id'  => isset($seal['id']) ? (int) $seal['id'] : 0,
                'url' => esc_url_raw($seal['url'] ?? ''),
            ],
        ];
    }

    private static function format_web_service(int $user_id): array
    {
        $enabled = false;
        $url = '';

        if ($user_id > 0 && function_exists('get_field')) {
            $services = get_field('servicios', 'user_' . $user_id);
            if (is_array($services) && isset($services['web']) && is_array($services['web'])) {
                if (isset($services['web']['web_360'])) {
                    $enabled = (bool) $services['web']['web_360'];
                }
                if (! empty($services['web']['url_web'])) {
                    $url = esc_url_raw((string) $services['web']['url_web']);
                }
            }
        }

        if (! $enabled) {
            $meta_enabled = get_user_meta($user_id, 'servicios_web_web_360', true);
            if ($meta_enabled !== '') {
                $enabled = (bool) $meta_enabled;
            }
        }

        if ($url === '') {
            $meta_url = get_user_meta($user_id, 'servicios_web_url_web', true);
            if (is_string($meta_url) && $meta_url !== '') {
                $url = esc_url_raw($meta_url);
            }
        }

        return [
            'enabled' => $enabled,
            'url'     => $url,
        ];
    }

    private static function format_commercials(array $commercials): array
    {
        $formatted = [];

        foreach ($commercials as $commercial) {
            if (! is_array($commercial)) {
                continue;
            }

            $first_name = self::clean_text($commercial['first_name'] ?? '');
            $last_name  = self::clean_text($commercial['last_name'] ?? '');
            $full_name  = self::clean_text($commercial['full_name'] ?? '');
            $composed   = trim($first_name . ' ' . $last_name);
            $display    = $full_name !== '' ? $full_name : ($composed !== '' ? $composed : self::clean_text($commercial['name'] ?? ''));

            $avatar = '';
            if (! empty($commercial['profile_image']['url'])) {
                $avatar = esc_url_raw((string) $commercial['profile_image']['url']);
            }

            $commercial_id = isset($commercial['id']) ? (int) $commercial['id'] : 0;
            $initials_source = $display !== '' ? $display : ($commercial['email'] ?? '');

            $formatted[] = [
                'id'           => $commercial_id,
                'name'         => $display,
                'full_name'    => $full_name !== '' ? $full_name : $display,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email'        => sanitize_email($commercial['email'] ?? ''),
                'phone'        => self::clean_text($commercial['phone'] ?? ''),
                'avatar'       => $avatar,
                'initials'     => self::initials($initials_source),
                'client_count' => $commercial_id > 0 ? self::get_commercial_client_count($commercial_id) : 0,
            ];
        }

        return $formatted;
    }

    private static function initials(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        if (! is_array($parts) || empty($parts)) {
            return strtoupper(mb_substr($name, 0, 2));
        }

        $initials = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials;
    }

    private static function get_active_offers(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        if (isset(self::$active_offers_cache[$user_id])) {
            return self::$active_offers_cache[$user_id];
        }

        $offers = [];

        if (! function_exists('get_field')) {
            self::$active_offers_cache[$user_id] = $offers;

            return $offers;
        }

        $group = get_field('ofertas_y_descuentos', 'user_' . $user_id);
        if (empty($group) || ! is_array($group) || empty($group['ofertas']) || ! is_array($group['ofertas'])) {
            self::$active_offers_cache[$user_id] = $offers;

            return $offers;
        }

        $now = current_time('timestamp');

        foreach ($group['ofertas'] as $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $is_active = isset($offer['estado']) ? (bool) $offer['estado'] : true;
            if (! $is_active) {
                continue;
            }

            $expiry_raw = isset($offer['caducidad_oferta']) ? (string) $offer['caducidad_oferta'] : '';
            if ($expiry_raw !== '') {
                $parts = explode('/', $expiry_raw);
                if (count($parts) === 3) {
                    $timestamp = strtotime(sprintf('%s-%s-%s 23:59:59', $parts[2], $parts[1], $parts[0]));
                    if ($timestamp !== false && $timestamp < $now) {
                        continue;
                    }
                }
            }

            $type_raw = $offer['tipo_oferta'] ?? '';
            $type_label = '';
            if (is_array($type_raw)) {
                $type_label = self::clean_text($type_raw['label'] ?? $type_raw['value'] ?? '');
            } else {
                $type_label = self::clean_text((string) $type_raw);
            }

            $custom_name = isset($offer['nombre_oferta']) ? self::clean_text($offer['nombre_oferta']) : '';
            $label = $type_label;

            if ($label === '' && $custom_name !== '') {
                $label = $custom_name;
            }

            if ($type_label !== '') {
                $type_key = strtolower($type_label);
                if ($type_key === 'personalizar' && $custom_name !== '') {
                    $label = $custom_name;
                }
            }

            if ($label === '') {
                $label = __('Oferta personalizada', 'garantias-online-360vo');
            }

            $discount = isset($offer['porcentaje_descuento']) ? (float) $offer['porcentaje_descuento'] : 0.0;

            $offers[] = [
                'label'    => $label,
                'discount' => $discount,
                'expires'  => $expiry_raw !== '' ? $expiry_raw : '',
                'type'     => $type_label,
                'name'     => $custom_name,
            ];
        }

        self::$active_offers_cache[$user_id] = $offers;

        return $offers;
    }

    private static function prepare_offers_response(int $user_id): array
    {
        return [
            'offers'      => self::collect_user_offers($user_id),
            'choices'     => [
                'tipo_oferta' => array_values(self::get_offer_type_choices()),
                'aplicacion'  => array_values(self::get_offer_scope_choices()),
            ],
            'modalidades' => self::get_modalidad_options(),
            'summary'     => self::get_active_offers($user_id),
        ];
    }

    private static function collect_user_offers(int $user_id): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        $group = get_field('ofertas_y_descuentos', 'user_' . $user_id);
        if (empty($group) || ! is_array($group) || empty($group['ofertas']) || ! is_array($group['ofertas'])) {
            return [];
        }

        $choices_map = self::get_offer_type_map();
        $scope_map   = self::get_offer_scope_map();

        $offers = [];
        foreach ($group['ofertas'] as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }

            $type_raw   = $offer['tipo_oferta'] ?? '';
            $type_value = is_array($type_raw) ? sanitize_key($type_raw['value'] ?? '') : sanitize_key((string) $type_raw);
            $type_label = $choices_map[$type_value] ?? ($type_raw['label'] ?? '');

            $scope_raw   = $offer['aplicacion'] ?? '';
            $scope_value = is_array($scope_raw) ? sanitize_key($scope_raw['value'] ?? '') : sanitize_key((string) $scope_raw);
            $scope_label = $scope_map[$scope_value] ?? ($scope_raw['label'] ?? '');

            $expiry_local = isset($offer['caducidad_oferta']) ? (string) $offer['caducidad_oferta'] : '';
            $expiry_iso   = self::format_date_for_input($expiry_local);

            $selection = [];
            if (! empty($offer['seleccion_modalidad']) && is_array($offer['seleccion_modalidad'])) {
                foreach ($offer['seleccion_modalidad'] as $modalidad_id) {
                    $modalidad_id = (int) $modalidad_id;
                    if ($modalidad_id > 0) {
                        $selection[] = $modalidad_id;
                    }
                }
            }

            $discount = null;
            if ($type_value !== 'sin_suplementos') {
                $raw_discount = $offer['porcentaje_descuento'] ?? null;
                if ($raw_discount !== null && $raw_discount !== '') {
                    $discount = (float) $raw_discount;
                }
            }

            $offers[] = [
                'index'               => $index,
                'tipo_oferta'         => [
                    'value' => $type_value,
                    'label' => (string) $type_label,
                ],
                'nombre_oferta'       => self::clean_text($offer['nombre_oferta'] ?? ''),
                'porcentaje_descuento'=> $discount,
                'aplicacion'          => [
                    'value' => $scope_value,
                    'label' => (string) $scope_label,
                ],
                'caducidad_oferta'    => $expiry_local,
                'caducidad_iso'       => $expiry_iso,
                'seleccion_modalidad' => $selection,
                'estado'              => isset($offer['estado']) ? (bool) $offer['estado'] : true,
            ];
        }

        return $offers;
    }

    private static function get_offer_type_choices(): array
    {
        return [
            [
                'value' => 'cliente_nuevo',
                'label' => __('Nuevo Cliente', 'garantias-online-360vo'),
            ],
            [
                'value' => 'sin_suplementos',
                'label' => __('Sin suplementos', 'garantias-online-360vo'),
            ],
            [
                'value' => 'personalizar',
                'label' => __('Personalizar', 'garantias-online-360vo'),
            ],
        ];
    }

    private static function get_offer_scope_choices(): array
    {
        return [
            [
                'value' => 'todas',
                'label' => __('Todas las garantías', 'garantias-online-360vo'),
            ],
            [
                'value' => 'essential',
                'label' => __('Todas las Essential', 'garantias-online-360vo'),
            ],
            [
                'value' => 'confort',
                'label' => __('Todas las Confort', 'garantias-online-360vo'),
            ],
            [
                'value' => 'exclusive',
                'label' => __('Todas las Exclusive', 'garantias-online-360vo'),
            ],
            [
                'value' => 'seleccion',
                'label' => __('Seleccionar manualmente', 'garantias-online-360vo'),
            ],
        ];
    }

    private static function get_offer_type_map(): array
    {
        $map = [];
        foreach (self::get_offer_type_choices() as $choice) {
            $map[$choice['value']] = $choice['label'];
        }

        return $map;
    }

    private static function get_offer_scope_map(): array
    {
        $map = [];
        foreach (self::get_offer_scope_choices() as $choice) {
            $map[$choice['value']] = $choice['label'];
        }

        return $map;
    }

    private static function format_date_for_storage(string $iso_date): string
    {
        $iso_date = trim($iso_date);
        if ($iso_date === '') {
            return '';
        }

        $timestamp = strtotime($iso_date);
        if ($timestamp === false) {
            return '';
        }

        return date('d/m/Y', $timestamp);
    }

    private static function format_date_for_input(string $stored_date): string
    {
        $stored_date = trim($stored_date);
        if ($stored_date === '') {
            return '';
        }

        $parts = explode('/', $stored_date);
        if (count($parts) !== 3) {
            return '';
        }

        $day   = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $year  = str_pad($parts[2], 4, '0', STR_PAD_LEFT);

        $timestamp = strtotime(sprintf('%s-%s-%s', $year, $month, $day));
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d', $timestamp);
    }

    private static function get_modalidad_options(): array
    {
        $options = [];

        $query = new WP_Query([
            'post_type'      => 'modalidad_garantia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        if (! $query->have_posts()) {
            return $options;
        }

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            if (! $post_id) {
                continue;
            }

            $options[] = [
                'id'    => (int) $post_id,
                'title' => get_the_title($post_id),
                'slug'  => get_post_field('post_name', $post_id),
                'nivel' => self::clean_text(self::get_first_term_label($post_id, 'nivel_garantia')),
                'tipo'  => self::clean_text(self::get_first_term_label($post_id, 'tipo_garantia')),
            ];
        }

        wp_reset_postdata();

        return $options;
    }

    private static function get_first_term_label(int $post_id, string $taxonomy): string
    {
        $terms = get_the_terms($post_id, $taxonomy);
        if (is_wp_error($terms) || empty($terms) || ! is_array($terms)) {
            return '';
        }

        $term = array_shift($terms);
        if (! $term) {
            return '';
        }

        return self::clean_text($term->name ?? '');
    }

    private static function count_guarantees(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $query = new WP_Query([
            'post_type'              => GuaranteeCPT::POST_TYPE,
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [
                [
                    'key'   => 'garantia_contratada_concesionario_empresa_profesional',
                    'value' => $user_id,
                ],
            ],
        ]);

        return isset($query->found_posts) ? (int) $query->found_posts : 0;
    }

    private static function build_empty_clients_response(int $page, int $per_page, string $quick_filter = ''): WP_REST_Response
    {
        return new WP_REST_Response([
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => 0,
            'total_pages' => 0,
            'items'       => [],
            'filters'     => [
                'channels' => self::get_channel_filters(),
            ],
            'quick_actions' => self::get_quick_filter_counts(),
            'quick_filter' => $quick_filter,
        ], 200);
    }

    private static function get_quick_filter_counts(): array
    {
        $client_ids = self::get_all_client_ids();
        $client_lookup = [];

        foreach ($client_ids as $client_id) {
            $client_lookup[(int) $client_id] = true;
        }

        $total_clients = count($client_ids);

        $pending_payment = self::count_ids_in_lookup(
            self::get_client_ids_with_guarantee_states(['pendiente_pago']),
            $client_lookup
        );

        $drafts = self::count_ids_in_lookup(
            self::get_client_ids_with_guarantee_states(['sin_finalizar']),
            $client_lookup
        );

        $assigned_commercials = self::count_ids_in_lookup(
            self::get_client_ids_with_commercial_assignments(),
            $client_lookup
        );

        $active_offers = self::count_ids_in_lookup(
            self::get_client_ids_with_active_offers(),
            $client_lookup
        );

        return [
            'pendiente_pago' => $pending_payment,
            'sin_finalizar'  => $drafts,
            'sin_comercial'  => max(0, $total_clients - $assigned_commercials),
            'sin_ofertas'    => max(0, $total_clients - $active_offers),
        ];
    }

    private static function get_all_client_ids(): array
    {
        if (is_array(self::$all_client_ids)) {
            return self::$all_client_ids;
        }

        $query = new WP_User_Query([
            'role__in' => self::get_supported_roles(),
            'fields'   => 'ID',
            'number'   => -1,
            'orderby'  => 'ID',
            'order'    => 'ASC',
        ]);

        $results = $query->get_results();
        $ids = [];

        if (is_array($results)) {
            foreach ($results as $candidate) {
                $user_id = (int) $candidate;
                if ($user_id > 0) {
                    $ids[$user_id] = $user_id;
                }
            }
        }

        self::$all_client_ids = array_values($ids);

        return self::$all_client_ids;
    }

    /**
     * @param array<int|string> $ids
     * @param array<int,bool>   $lookup
     */
    private static function count_ids_in_lookup(array $ids, array $lookup): int
    {
        if (empty($lookup) || empty($ids)) {
            return 0;
        }

        $count = 0;

        foreach ($ids as $value) {
            $id = (int) $value;
            if ($id > 0 && isset($lookup[$id])) {
                $count++;
            }
        }

        return $count;
    }

    private static function normalize_quick_filter($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $candidate = sanitize_key($value);

        return in_array($candidate, self::QUICK_FILTERS, true) ? $candidate : '';
    }

    private static function get_client_ids_with_guarantee_states(array $states): array
    {
        $states = array_values(array_filter(array_map('sanitize_key', $states)));
        if (empty($states)) {
            return [];
        }

        $cache_key = implode('|', $states);
        if (isset(self::$guarantee_state_client_cache[$cache_key])) {
            return self::$guarantee_state_client_cache[$cache_key];
        }

        global $wpdb;

        if (! isset($wpdb->postmeta, $wpdb->posts)) {
            self::$guarantee_state_client_cache[$cache_key] = [];

            return [];
        }

        $placeholders = implode(',', array_fill(0, count($states), '%s'));
        $sql = "
            SELECT DISTINCT client_meta.meta_value
            FROM {$wpdb->postmeta} AS state_meta
            INNER JOIN {$wpdb->postmeta} AS client_meta
                ON client_meta.post_id = state_meta.post_id
            INNER JOIN {$wpdb->posts} AS posts
                ON posts.ID = state_meta.post_id
            WHERE posts.post_type = %s
                AND state_meta.meta_key = 'estado_garantia_estado_contratacion'
                AND client_meta.meta_key = 'garantia_contratada_concesionario_empresa_profesional'
                AND state_meta.meta_value IN ($placeholders)
        ";

        $prepared = $wpdb->prepare($sql, array_merge([GuaranteeCPT::POST_TYPE], $states));
        $raw_ids  = $wpdb->get_col($prepared);

        $normalized = [];
        if (is_array($raw_ids)) {
            foreach ($raw_ids as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $normalized[$id] = $id;
                }
            }
        }

        self::$guarantee_state_client_cache[$cache_key] = array_values($normalized);

        return self::$guarantee_state_client_cache[$cache_key];
    }

    private static function get_client_ids_with_commercial_assignments(): array
    {
        if (is_array(self::$clients_with_commercial_assignments)) {
            return self::$clients_with_commercial_assignments;
        }

        global $wpdb;

        if (! isset($wpdb->usermeta)) {
            self::$clients_with_commercial_assignments = [];

            return [];
        }

        $meta_key = 'ajustes_usuarios_comercial_asignado';
        $rows     = $wpdb->get_results(
            $wpdb->prepare("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key),
            ARRAY_A
        );

        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $user_id = isset($row['user_id']) ? (int) $row['user_id'] : 0;
                if ($user_id <= 0) {
                    continue;
                }

                $value = maybe_unserialize($row['meta_value']);

                if (is_array($value)) {
                    foreach ($value as $candidate) {
                        if ((int) $candidate > 0) {
                            $ids[$user_id] = $user_id;
                            break;
                        }
                    }
                    continue;
                }

                if ((int) $value > 0) {
                    $ids[$user_id] = $user_id;
                }
            }
        }

        self::$clients_with_commercial_assignments = array_values($ids);

        return self::$clients_with_commercial_assignments;
    }

    private static function get_client_ids_with_active_offers(): array
    {
        if (is_array(self::$clients_with_active_offers)) {
            return self::$clients_with_active_offers;
        }

        $client_ids = self::get_all_client_ids();

        if (empty($client_ids)) {
            self::$clients_with_active_offers = [];

            return [];
        }

        $ids = [];

        foreach ($client_ids as $client_id) {
            $user_id = (int) $client_id;
            if ($user_id <= 0) {
                continue;
            }

            $offers = self::get_active_offers($user_id);
            if (! empty($offers)) {
                $ids[$user_id] = $user_id;
            }
        }

        self::$clients_with_active_offers = array_values($ids);

        return self::$clients_with_active_offers;
    }

    private static function is_offer_expired(string $raw_date, int $now): bool
    {
        $parts = explode('/', $raw_date);
        if (count($parts) !== 3) {
            return false;
        }

        $timestamp = strtotime(sprintf('%s-%s-%s 23:59:59', $parts[2], $parts[1], $parts[0]));

        if ($timestamp === false) {
            return false;
        }

        return $timestamp < $now;
    }
}
