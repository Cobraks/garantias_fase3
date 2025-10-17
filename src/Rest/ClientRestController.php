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

if (! defined('ABSPATH')) {
    exit;
}

class ClientRestController
{
    public const NAMESPACE = 'go/v1';
    public const REST_BASE = 'clientes';

    /**
     * @var array<int,int>|null
     */
    private static $commercial_client_counts = null;

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
                    ],
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
    }

    public static function permissions_check($request = null): bool
    {
        return current_user_can('manage_options');
    }

    public static function get_user_offers(WP_REST_Request $request)
    {
        if (! self::permissions_check()) {
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
        if (! self::permissions_check()) {
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

        $result = update_field('ofertas_y_descuentos', ['ofertas' => $normalized], 'user_' . $user_id);
        if ($result === false) {
            return new WP_Error(
                'go_offers_save_failed',
                __('Ha ocurrido un error al guardar las ofertas.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $updated_snapshot = self::format_offers_for_log($normalized);

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

    public static function activate_sepa(WP_REST_Request $request)
    {
        if (! self::permissions_check()) {
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

        SepaMandateService::set_status($user_id, SepaMandateService::STATUS_SIGNED);
        SepaMandateService::set_activation_flag($user_id, true);
        SepaMandateService::set_payment_method($user_id, 'domiciliacion');

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
                'document_reference' => $reference,
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

        return new WP_REST_Response(
            [
                'message' => __('Domiciliación bancaria activada.', 'garantias-online-360vo'),
                'sepa'    => $sepa_details,
                'payment' => $payment_info,
            ],
            200
        );
    }

    public static function get_items(WP_REST_Request $request)
    {
        if (! self::permissions_check()) {
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
        ];

        return new WP_REST_Response($response, 200);
    }

    public static function search_commercials(WP_REST_Request $request)
    {
        if (! self::permissions_check()) {
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
        if (! self::permissions_check()) {
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
            'links'        => [
                'admin' => esc_url_raw(get_edit_user_link($user->ID)),
            ],
        ];
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

    private static function get_supported_roles(): array
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

        $label = '';
        if (! empty($payments['raw_method'])) {
            $label = self::clean_text((string) $payments['raw_method']);
        }

        if ($label === '' && $selected !== '') {
            $label = $selected === 'domiciliacion'
                ? __('Domiciliación bancaria', 'garantias-online-360vo')
                : __('Transferencia bancaria', 'garantias-online-360vo');
        }

        return [
            'method' => $selected,
            'label'  => $label,
        ];
    }

    private static function format_sepa_details(array $payments): array
    {
        $sepa = $payments['sepa'] ?? [];
        if (! is_array($sepa)) {
            $sepa = [];
        }

        $label = self::clean_text($sepa['status_label'] ?? '');
        $variant = self::clean_text($sepa['status_variant'] ?? '');

        $fields = [];
        if (! empty($sepa['fields']) && is_array($sepa['fields'])) {
            foreach ($sepa['fields'] as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $value = self::clean_text($field['value'] ?? '');
                if ($value === '') {
                    continue;
                }

                $fields[] = [
                    'label' => self::clean_text($field['label'] ?? ''),
                    'value' => $value,
                ];
            }
        }

        $requested = isset($sepa['requested']) ? (bool) $sepa['requested'] : false;
        $awaiting_validation = isset($sepa['awaiting_validation']) ? (bool) $sepa['awaiting_validation'] : false;
        $locked = isset($sepa['locked']) ? (bool) $sepa['locked'] : false;
        $status_code = isset($sepa['status_code']) ? sanitize_key((string) $sepa['status_code']) : '';
        $activated = isset($sepa['activated']) ? (bool) $sepa['activated'] : false;

        $status_value = null;
        if ($status_code !== '') {
            $status_value = ($status_code === SepaMandateService::STATUS_SIGNED) && $activated;
        } elseif (array_key_exists('status', $sepa)) {
            if ($sepa['status'] === true) {
                $status_value = true;
            } elseif ($sepa['status'] === false) {
                $status_value = false;
            }
        }

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
            'label'               => $label !== '' ? $label : __('Sin información del mandato', 'garantias-online-360vo'),
            'variant'             => $variant !== '' ? $variant : 'info',
            'fields'              => $fields,
            'requested'           => $requested,
            'awaiting_validation' => $awaiting_validation,
            'locked'              => $locked,
            'status'              => $status_value,
            'status_code'         => $status_code,
            'activated'           => $activated,
            'documents'           => $documents,
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
        if ($user_id <= 0 || ! function_exists('get_field')) {
            return [];
        }

        $group = get_field('ofertas_y_descuentos', 'user_' . $user_id);
        if (empty($group) || ! is_array($group) || empty($group['ofertas']) || ! is_array($group['ofertas'])) {
            return [];
        }

        $now    = current_time('timestamp');
        $offers = [];

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
}
