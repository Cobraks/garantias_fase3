<?php

namespace GarantiasOnline360VO\Support;

use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class UserProfileResolver
{
    /**
     * Build a normalized profile for a given user.
     *
     * @return array{
     *     id:int,
     *     personal_name:string,
     *     username:string,
     *     email:string,
     *     company:array{
     *         name:string,
     *         trade_name:string,
     *         legal_name:string,
     *         tax_id:string,
     *         type:array{value:string,label:string},
     *         address:array{street:string,city:string,state:string,zip:string,country:string}
     *     }
     * }
     */
    public static function build_from_user(WP_User $user): array
    {
        $user_id = (int) $user->ID;
        $personal_name = self::get_personal_name($user);
        $company = self::get_company_profile($user_id);
        $company_name = self::resolve_company_name($company, $personal_name);

        return [
            'id'            => $user_id,
            'personal_name' => $personal_name,
            'username'      => $user->user_login,
            'email'         => sanitize_email($user->user_email),
            'company'       => array_merge($company, ['name' => $company_name]),
        ];
    }

    public static function get_personal_name($user): string
    {
        if ($user instanceof WP_User) {
            $candidate = trim((string) $user->display_name);
            if ($candidate !== '') {
                return $candidate;
            }

            return (string) $user->user_login;
        }

        $user_id = is_numeric($user) ? (int) $user : 0;
        if ($user_id <= 0) {
            return '';
        }

        $object = get_user_by('id', $user_id);
        if (! $object instanceof WP_User) {
            return '';
        }

        return self::get_personal_name($object);
    }

    public static function get_personal_name_by_id(int $user_id): string
    {
        return self::get_personal_name($user_id);
    }

    public static function get_company_profile(int $user_id): array
    {
        $scope = 'user_' . $user_id;

        $company_group = self::get_field_group($scope, 'datos_empresa');
        $legacy_group  = self::get_field_group($scope, 'datos_usuario');

        $trade_name = self::first_text([
            $company_group['nombre_comercial'] ?? '',
            $legacy_group['nombre_comercial'] ?? '',
            $legacy_group['nombre_empresa'] ?? '',
            get_user_meta($user_id, 'datos_usuario_nombre_comercial', true),
            get_user_meta($user_id, 'datos_usuario_nombre_empresa', true),
        ]);

        $legal_name = self::first_text([
            $company_group['razon_social'] ?? '',
            $legacy_group['razon_social'] ?? '',
            $legacy_group['razon'] ?? '',
            get_user_meta($user_id, 'datos_usuario_razon_social', true),
            get_user_meta($user_id, 'datos_usuario_razon', true),
        ]);

        $tax_id = self::first_text([
            $company_group['cif'] ?? '',
            $legacy_group['cif'] ?? '',
            get_user_meta($user_id, 'datos_usuario_cif', true),
        ]);

        $type_raw = $company_group['tipo_profesional'] ?? ($legacy_group['tipo_profesional'] ?? '');
        if ($type_raw === '' && $legacy_group) {
            $type_raw = $legacy_group['tipo'] ?? '';
        }
        $type = self::normalize_professional_type($type_raw);

        $company_address_group = [];
        if (isset($company_group['direccion_empresa']) && is_array($company_group['direccion_empresa'])) {
            $company_address_group = $company_group['direccion_empresa'];
        }

        $address = [
            'street'  => self::first_text([
                $company_address_group['direccion'] ?? '',
                $legacy_group['direccion'] ?? '',
                get_user_meta($user_id, 'datos_usuario_direccion', true),
            ]),
            'city'    => self::first_text([
                $company_address_group['poblacion'] ?? '',
                $legacy_group['localidad'] ?? '',
                get_user_meta($user_id, 'datos_usuario_localidad', true),
            ]),
            'state'   => self::first_text([
                $company_address_group['provincia'] ?? '',
                $legacy_group['provincia'] ?? '',
                get_user_meta($user_id, 'datos_usuario_provincia', true),
            ]),
            'zip'     => self::first_text([
                $company_address_group['codigo_postal'] ?? '',
                $legacy_group['codigo_postal'] ?? '',
                get_user_meta($user_id, 'datos_usuario_codigo_postal', true),
            ]),
            'country' => self::first_text([
                $company_address_group['pais'] ?? '',
                $legacy_group['pais'] ?? '',
                get_user_meta($user_id, 'datos_usuario_pais', true),
            ]),
        ];

        return [
            'trade_name' => $trade_name,
            'legal_name' => $legal_name,
            'tax_id'     => $tax_id,
            'type'       => $type,
            'address'    => array_map([self::class, 'sanitize_text'], $address),
        ];
    }

    public static function get_company_name(int $user_id): string
    {
        $profile = self::get_company_profile($user_id);
        return self::resolve_company_name($profile, '');
    }

    public static function get_vendor_labels(int $user_id): array
    {
        $personal = self::get_personal_name_by_id($user_id);
        $company  = self::get_company_profile($user_id);
        $company_name = self::resolve_company_name($company, $personal);

        return [
            'company_name'  => $company_name,
            'personal_name' => $personal,
            'company'       => array_merge($company, ['name' => $company_name]),
            'username'      => self::resolve_username($user_id),
        ];
    }

    private static function resolve_company_name(array $company, string $fallback): string
    {
        if (! empty($company['trade_name'])) {
            return $company['trade_name'];
        }
        if (! empty($company['legal_name'])) {
            return $company['legal_name'];
        }

        return $fallback;
    }

    private static function resolve_username(int $user_id): string
    {
        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return '';
        }

        return (string) $user->user_login;
    }

    private static function normalize_professional_type($value): array
    {
        $type_value = '';
        $type_label = '';

        if (is_array($value)) {
            $type_value = sanitize_key((string) ($value['value'] ?? ''));
            $type_label = self::sanitize_text($value['label'] ?? '');
        } elseif (is_string($value)) {
            $type_value = sanitize_key($value);
        }

        if ($type_label === '' && $type_value !== '') {
            $type_label = ucwords(str_replace(['_', '-'], ' ', $type_value));
        }

        return [
            'value' => $type_value,
            'label' => $type_label,
        ];
    }

    private static function get_field_group(string $scope, string $field): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        $group = get_field($field, $scope);

        return is_array($group) ? $group : [];
    }

    private static function first_text(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                continue;
            }

            $candidate = self::sanitize_text($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private static function sanitize_text($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(wp_strip_all_tags($value));
    }
}
