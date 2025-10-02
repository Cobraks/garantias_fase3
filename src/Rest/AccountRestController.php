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

        $notifications_data = self::get_array_param($request, 'notifications');
        $workshop_data      = self::get_array_param($request, 'workshop');

        $notifications = self::update_notifications($user_id, $notifications_data);
        if (is_wp_error($notifications)) {
            return $notifications;
        }

        $workshop = self::update_workshop($user_id, $workshop_data);
        if (is_wp_error($workshop)) {
            return $workshop;
        }

        $profile_image = self::update_profile_image($user_id, $request);
        if (is_wp_error($profile_image)) {
            return $profile_image;
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
            'profile_image' => $profile_image,
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

    /**
     * @return array<string, mixed>|WP_Error
     */
    private static function update_profile_image(int $user_id, WP_REST_Request $request)
    {
        $files = $request->get_file_params();
        $profile_file = is_array($files) ? ($files['profile_image'] ?? null) : null;

        if (! is_array($profile_file) || empty($profile_file['tmp_name'])) {
            return self::prepare_profile_image_response($user_id, self::resolve_profile_image_id($user_id));
        }

        if (! empty($profile_file['error'])) {
            return new WP_Error(
                'go_account_profile_image_upload',
                __('No se ha podido subir la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $overrides = ['test_form' => false];
        $handled = wp_handle_upload($profile_file, $overrides);

        if (! is_array($handled) || isset($handled['error'])) {
            return new WP_Error(
                'go_account_profile_image_upload',
                $handled['error'] ?? __('Error al subir la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 400]
            );
        }

        $attachment = [
            'post_mime_type' => $handled['type'] ?? 'image/jpeg',
            'post_title'     => sanitize_file_name($profile_file['name'] ?? 'profile-image'),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $handled['file']);
        if (is_wp_error($attachment_id) || ! $attachment_id) {
            if (isset($handled['file']) && file_exists($handled['file'])) {
                @unlink($handled['file']);
            }

            return new WP_Error(
                'go_account_profile_image_upload',
                __('No se ha podido guardar la imagen de perfil.', 'garantias-online-360vo'),
                ['status' => 500]
            );
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $handled['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);

        update_user_meta($user_id, 'profile_image', (int) $attachment_id);
        if (function_exists('update_field')) {
            update_field('profile_image', (int) $attachment_id, 'user_' . $user_id);
        }

        return self::prepare_profile_image_response($user_id, (int) $attachment_id);
    }

    private static function get_array_param(WP_REST_Request $request, string $key): array
    {
        $value = $request->get_param($key);
        if (is_array($value)) {
            return $value;
        }

        $json = $request->get_json_params();
        if (is_array($json) && isset($json[$key]) && is_array($json[$key])) {
            return $json[$key];
        }

        return [];
    }

    private static function resolve_profile_image_id(int $user_id): int
    {
        $scope = 'user_' . $user_id;
        $attachment_id = 0;

        if (function_exists('get_field')) {
            $field_value = get_field('profile_image', $scope);
            if (is_array($field_value)) {
                if (isset($field_value['ID'])) {
                    $attachment_id = (int) $field_value['ID'];
                } elseif (isset($field_value['id'])) {
                    $attachment_id = (int) $field_value['id'];
                }
            } elseif ($field_value) {
                $attachment_id = (int) $field_value;
            }
        }

        if (! $attachment_id) {
            $attachment_id = (int) get_user_meta($user_id, 'profile_image', true);
        }

        return $attachment_id;
    }

    /**
     * @return array{id:int,url:string,filename:string}
     */
    private static function prepare_profile_image_response(int $user_id, int $attachment_id): array
    {
        if ($attachment_id <= 0) {
            return [
                'id'       => 0,
                'url'      => get_avatar_url($user_id, ['size' => 256]),
                'filename' => '',
            ];
        }

        $url = wp_get_attachment_image_url($attachment_id, [256, 256]);
        if (! $url) {
            $url = wp_get_attachment_url($attachment_id) ?: '';
        }

        $file = get_attached_file($attachment_id);

        return [
            'id'       => $attachment_id,
            'url'      => $url,
            'filename' => $file ? basename($file) : '',
        ];
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
