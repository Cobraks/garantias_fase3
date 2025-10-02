<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Support\NotificationEmailResolver;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class AccountRestController
{
    private const NAMESPACE = 'go/v1';
    private const REST_BASE = 'account';

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [__CLASS__, 'update_account'],
                    'permission_callback' => [__CLASS__, 'can_update_account'],
                ],
            ]
        );
    }

    public static function can_update_account(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $user = wp_get_current_user();

        return $user instanceof WP_User && in_array('go_profesional', (array) $user->roles, true);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public static function update_account(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error(
                'go_account_forbidden',
                __('No tienes permisos para actualizar esta información.', 'garantias-online-360vo'),
                ['status' => 403]
            );
        }

        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            $payload = [];
        }

        $notifications = self::update_notifications($user_id, $payload['notifications'] ?? []);
        if (is_wp_error($notifications)) {
            return $notifications;
        }

        $workshop = self::update_workshop($user_id, $payload['workshop'] ?? []);
        if (is_wp_error($workshop)) {
            return $workshop;
        }

        $resolved_notification = NotificationEmailResolver::resolve_with_details($user_id);

        return rest_ensure_response([
            'success'       => true,
            'notifications' => array_merge(
                $notifications,
                [
                    'resolved_email'     => $resolved_notification['email'],
                    'uses_registration'  => $resolved_notification['uses_registration'],
                ]
            ),
            'workshop'      => $workshop,
        ]);
    }

    /**
     * @param int                      $user_id
     * @param array<string, mixed>|mixed $data
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function update_notifications(int $user_id, $data)
    {
        $data = is_array($data) ? $data : [];
        $raw_email = isset($data['email']) ? (string) $data['email'] : '';
        $email = sanitize_email($raw_email);
        $use_registration = array_key_exists('use_registration', $data)
            ? (bool) $data['use_registration']
            : ($email === '');

        if (! $use_registration && $raw_email !== '' && ! is_email($email)) {
            return new WP_Error(
                'go_account_invalid_notification_email',
                __('El correo de notificaciones no es válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        if ($use_registration) {
            $email = '';
        }

        $scope = 'user_' . $user_id;
        $group = self::get_acf_group($scope, 'ajustes_de_notificaciones');
        $group['misma_direccion_registro'] = $use_registration ? 1 : 0;
        $group['correo_electronico_notificaciones'] = $email;

        if (function_exists('update_field')) {
            update_field('ajustes_de_notificaciones', $group, $scope);
        }

        update_user_meta($user_id, 'ajustes_de_notificaciones_misma_direccion_registro', $use_registration ? 1 : 0);
        update_user_meta($user_id, 'ajustes_de_notificaciones_correo_electronico_notificaciones', $email);
        update_user_meta($user_id, 'ajustes_de_notificaciones_correo_electronico', $email);

        return [
            'email'            => $email,
            'use_registration' => $use_registration,
        ];
    }

    /**
     * @param int                      $user_id
     * @param array<string, mixed>|mixed $data
     *
     * @return array<string, mixed>|WP_Error
     */
    private static function update_workshop(int $user_id, $data)
    {
        $data = is_array($data) ? $data : [];
        $has_workshop = ! empty($data['has_workshop']);

        $fields = [
            'name'           => self::sanitize_text($data['name'] ?? ''),
            'fiscal_name'    => self::sanitize_text($data['fiscal_name'] ?? ''),
            'tax_id'         => self::sanitize_text($data['tax_id'] ?? ''),
            'contact_person' => self::sanitize_text($data['contact_person'] ?? ''),
            'phone'          => self::sanitize_text($data['phone'] ?? ''),
            'address'        => self::sanitize_text($data['address'] ?? ''),
            'email'          => '',
        ];

        $raw_email = isset($data['email']) ? (string) $data['email'] : '';
        $email = sanitize_email($raw_email);
        if ($has_workshop && $raw_email !== '' && ! is_email($email)) {
            return new WP_Error(
                'go_account_invalid_workshop_email',
                __('El correo del taller no es válido.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }
        $fields['email'] = $has_workshop ? $email : '';

        if (! $has_workshop) {
            foreach ($fields as $key => $value) {
                $fields[$key] = '';
            }
        }

        $scope = 'user_' . $user_id;
        $services = self::get_acf_group($scope, 'servicios');
        $workshop_group = [];
        if (isset($services['taller']) && is_array($services['taller'])) {
            $workshop_group = $services['taller'];
        }

        $workshop_group['tiene_taller'] = $has_workshop ? 1 : 0;
        $workshop_group['nombre_taller'] = $fields['name'];
        $workshop_group['denominacion_fiscal'] = $fields['fiscal_name'];
        $workshop_group['cif_taller'] = $fields['tax_id'];
        $workshop_group['persona_contacto_taller'] = $fields['contact_person'];
        $workshop_group['telefono_taller'] = $fields['phone'];
        $workshop_group['correo_taller'] = $fields['email'];
        $workshop_group['direccion_taller'] = $fields['address'];

        $services['taller'] = $workshop_group;

        if (function_exists('update_field')) {
            update_field('servicios', $services, $scope);
        }

        update_user_meta($user_id, 'servicios_taller_tiene_taller', $has_workshop ? 1 : 0);
        update_user_meta($user_id, 'servicios_taller_nombre_taller', $fields['name']);
        update_user_meta($user_id, 'servicios_taller_denominacion_fiscal', $fields['fiscal_name']);
        update_user_meta($user_id, 'servicios_taller_cif_taller', $fields['tax_id']);
        update_user_meta($user_id, 'servicios_taller_persona_contacto_taller', $fields['contact_person']);
        update_user_meta($user_id, 'servicios_taller_telefono_taller', $fields['phone']);
        update_user_meta($user_id, 'servicios_taller_correo_taller', $fields['email']);
        update_user_meta($user_id, 'servicios_taller_direccion_taller', $fields['address']);

        $fields['has_workshop'] = $has_workshop;

        return $fields;
    }

    private static function get_acf_group(string $scope, string $field): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        $group = get_field($field, $scope);

        return is_array($group) ? $group : [];
    }

    private static function sanitize_text($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return sanitize_text_field((string) $value);
        }

        return '';
    }
}
