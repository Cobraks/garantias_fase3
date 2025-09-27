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
            'role'    => 'go_profesional',
            'number'  => $per_page,
            'offset'  => ($page - 1) * $per_page,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ];

        $search = $request->get_param('search');
        if (is_string($search) && $search !== '') {
            $search = trim($search);
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
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
        ];

        return new WP_REST_Response($response, 200);
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

        $sales_channel = self::format_sales_channel($user_data['company']['type'] ?? []);
        $offers        = self::get_active_offers((int) $user->ID);
        $guarantees    = self::count_guarantees((int) $user->ID);
        $commercials   = self::format_commercials($account['commercials'] ?? []);

        $payments     = $account['payments'] ?? [];
        $payment_info = self::format_payment($payments);

        $contact = [
            'email'              => sanitize_email($user_data['email'] ?? $user->user_email),
            'notification_email' => sanitize_email($user_data['notification_email'] ?? $user->user_email),
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

        $sepa = $payments['sepa'] ?? [];
        $sepa_status = [
            'label'   => self::clean_text($sepa['status_label'] ?? ''),
            'variant' => self::clean_text($sepa['status_variant'] ?? ''),
        ];

        return [
            'id'      => (int) $user->ID,
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
                'tax_id'=> self::clean_text($user_data['company']['tax_id'] ?? ''),
                'type'  => $sales_channel,
            ],
            'address'      => $address,
            'sepa'         => $sepa_status,
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

        $display = mysql2date(get_option('date_format', 'd/m/Y'), $value);
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

            $formatted[] = [
                'id'         => isset($commercial['id']) ? (int) $commercial['id'] : 0,
                'name'       => $display,
                'full_name'  => $full_name !== '' ? $full_name : $display,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => sanitize_email($commercial['email'] ?? ''),
                'phone'      => self::clean_text($commercial['phone'] ?? ''),
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

            $type  = $offer['tipo_oferta'] ?? '';
            $label = '';
            if (is_array($type)) {
                $label = self::clean_text($type['label'] ?? $type['value'] ?? '');
            } else {
                $label = self::clean_text((string) $type);
            }

            if ($label === '' && isset($offer['nombre_oferta'])) {
                $label = self::clean_text($offer['nombre_oferta']);
            } elseif ($label === 'personalizar' && isset($offer['nombre_oferta'])) {
                $label = self::clean_text($offer['nombre_oferta']);
            }

            $discount = isset($offer['porcentaje_descuento']) ? (float) $offer['porcentaje_descuento'] : 0.0;

            $offers[] = [
                'label'    => $label,
                'discount' => $discount,
                'expires'  => $expiry_raw !== '' ? $expiry_raw : '',
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
