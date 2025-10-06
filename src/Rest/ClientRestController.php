<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Account\AccountViewModel;
use GarantiasOnline360VO\GuaranteeCPT;
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
    }

    public static function permissions_check($request = null): bool
    {
        return current_user_can('manage_options');
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

        $account     = AccountViewModel::from_user($user);
        $commercials = self::format_commercials($account['commercials'] ?? []);

        return new WP_REST_Response([
            'commercials' => $commercials,
        ], 200);
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
            'email'              => $primary_email,
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

        return [
            'id'         => (int) $user->ID,
            'name'       => $full_name !== '' ? $full_name : $user->display_name,
            'full_name'  => $full_name,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'phone'      => $phone,
            'avatar'     => esc_url_raw(get_avatar_url($user->ID)),
        ];
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

        return [
            'label'   => $label !== '' ? $label : __('Sin información del mandato', 'garantias-online-360vo'),
            'variant' => $variant !== '' ? $variant : 'info',
            'fields'  => $fields,
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

            $formatted[] = [
                'id'         => isset($commercial['id']) ? (int) $commercial['id'] : 0,
                'name'       => $display,
                'full_name'  => $full_name !== '' ? $full_name : $display,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => sanitize_email($commercial['email'] ?? ''),
                'phone'      => self::clean_text($commercial['phone'] ?? ''),
                'avatar'     => $avatar,
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
