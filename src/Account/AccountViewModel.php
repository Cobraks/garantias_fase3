<?php

namespace GarantiasOnline360VO\Account;

use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

class AccountViewModel
{
    public static function for_current_user(): array
    {
        $user = wp_get_current_user();

        if (! $user instanceof WP_User || ! $user->exists()) {
            return [];
        }

        return self::from_user($user);
    }

    public static function from_user(WP_User $user): array
    {
        $user_id = (int) $user->ID;
        $scope   = 'user_' . $user_id;

        $profile_image_id = 0;
        if (function_exists('get_field')) {
            $profile_image_id = (int) (get_field('profile_image', $scope) ?: 0);
        }

        $profile_image = self::normalize_media($profile_image_id);
        if (! $profile_image['url']) {
            $profile_image['url'] = get_avatar_url($user_id, ['size' => 256]);
        }

        $contact_meta = self::get_meta_group($scope, 'datos_usuario');

        $trade_name = '';
        if (isset($contact_meta['nombre_comercial'])) {
            $trade_name = $contact_meta['nombre_comercial'];
        } elseif (isset($contact_meta['nombre_empresa'])) {
            $trade_name = $contact_meta['nombre_empresa'];
        }

        if ($trade_name === '') {
            $trade_name = get_user_meta($user_id, 'datos_usuario_nombre_comercial', true);
        }
        if ($trade_name === '') {
            $trade_name = get_user_meta($user_id, 'datos_usuario_nombre_empresa', true);
        }

        $legal_name = '';
        if (isset($contact_meta['razon_social'])) {
            $legal_name = $contact_meta['razon_social'];
        } elseif (isset($contact_meta['razon'])) {
            $legal_name = $contact_meta['razon'];
        }

        if ($legal_name === '') {
            $legal_name = get_user_meta($user_id, 'datos_usuario_razon_social', true);
        }
        if ($legal_name === '') {
            $legal_name = get_user_meta($user_id, 'datos_usuario_razon', true);
        }

        $company = [
            'trade_name' => self::sanitize_optional_text($trade_name),
            'legal_name' => self::sanitize_optional_text($legal_name),
        ];

        $notification_settings = self::get_meta_group($scope, 'ajustes_de_notificaciones');
        $same_as_registration  = isset($notification_settings['misma_direccion_registro'])
            ? (bool) $notification_settings['misma_direccion_registro']
            : null;

        $custom_candidates = [
            $notification_settings['correo_electronico_notificaciones'] ?? '',
            $notification_settings['correo_electronico'] ?? '',
        ];

        $custom_candidates[] = get_user_meta(
            $user_id,
            'ajustes_de_notificaciones_correo_electronico_notificaciones',
            true
        );
        $custom_candidates[] = get_user_meta(
            $user_id,
            'ajustes_de_notificaciones_correo_electronico',
            true
        );
        $custom_candidates[] = $contact_meta['correo_electronico'] ?? '';
        $custom_candidates[] = get_user_meta($user_id, 'datos_usuario_correo_electronico', true);

        $custom_notification = '';
        foreach ($custom_candidates as $candidate) {
            $candidate = sanitize_email((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $custom_notification = $candidate;
            break;
        }

        $notification_email = NotificationEmailResolver::resolve($user_id);

        $phone = isset($contact_meta['telefono'])
            ? $contact_meta['telefono']
            : get_user_meta($user_id, 'datos_usuario_telefono', true);

        $address = [
            'street'  => $contact_meta['direccion'] ?? get_user_meta($user_id, 'datos_usuario_direccion', true),
            'city'    => $contact_meta['localidad'] ?? get_user_meta($user_id, 'datos_usuario_localidad', true),
            'state'   => $contact_meta['provincia'] ?? get_user_meta($user_id, 'datos_usuario_provincia', true),
            'zip'     => $contact_meta['codigo_postal'] ?? get_user_meta($user_id, 'datos_usuario_codigo_postal', true),
            'country' => $contact_meta['pais'] ?? get_user_meta($user_id, 'datos_usuario_pais', true),
        ];

        $assigned = self::extract_commercials($scope, $user_id);

        $documents = self::extract_documents($scope, $user_id);
        $payments  = self::extract_payments($scope, $user_id);

        return [
            'user' => [
                'id'                   => $user_id,
                'name'                 => $user->display_name ?: $user->user_login,
                'username'             => $user->user_login,
                'email'                => sanitize_email($user->user_email),
                'notification_email'   => $notification_email,
                'custom_notification'  => $custom_notification,
                'same_as_registration' => $same_as_registration,
                'phone'                => self::sanitize_optional_text($phone),
                'address'              => array_map([self::class, 'sanitize_optional_text'], $address),
                'profile_image'        => $profile_image,
                'avatar_url'           => get_avatar_url($user_id, ['size' => 96]),
                'registered'           => $user->user_registered,
                'roles'                => array_map('sanitize_key', (array) $user->roles),
                'company'              => $company,
            ],
            'commercials'   => $assigned,
            'documents'     => $documents,
            'payments'      => $payments,
        ];
    }

    private static function get_meta_group(string $scope, string $field): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        $group = get_field($field, $scope);

        return is_array($group) ? $group : [];
    }

    private static function sanitize_optional_text($value): string
    {
        if (is_string($value)) {
            return trim(wp_strip_all_tags($value));
        }

        return '';
    }

    private static function extract_commercials(string $scope, int $user_id): array
    {
        $commercials = [];
        $ids         = [];

        if (function_exists('get_field')) {
            $group = get_field('ajustes_usuarios', $scope);
            if (is_array($group) && ! empty($group['comercial_asignado'])) {
                $ids = self::normalize_user_ids($group['comercial_asignado']);
            }
        }

        if (! $ids) {
            $meta_value = get_user_meta($user_id, 'ajustes_usuarios_comercial_asignado', true);
            $ids = self::normalize_user_ids($meta_value);
        }

        foreach ($ids as $commercial_id) {
            $commercial = get_user_by('id', $commercial_id);
            if (! $commercial instanceof WP_User) {
                continue;
            }

            $profile_image = ['id' => 0, 'url' => '', 'filename' => ''];
            if (function_exists('get_field')) {
                $image = get_field('profile_image', 'user_' . $commercial_id);
                if ($image) {
                    $profile_image = self::normalize_media($image);
                }
            }

            if (! $profile_image['url']) {
                $profile_image['url'] = get_avatar_url($commercial_id, ['size' => 96]);
            }

            $commercials[] = [
                'id'    => (int) $commercial->ID,
                'name'  => $commercial->display_name ?: $commercial->user_login,
                'email' => sanitize_email($commercial->user_email),
                'phone' => self::sanitize_optional_text(
                    get_user_meta($commercial_id, 'datos_usuario_telefono', true)
                ),
                'profile_image' => $profile_image,
            ];
        }

        return $commercials;
    }

    private static function extract_documents(string $scope, int $user_id): array
    {
        $group = [];

        if (function_exists('get_field')) {
            $documents = get_field('documentos', $scope);
            if (is_array($documents) && isset($documents['firma_y_sello'])) {
                $group = is_array($documents['firma_y_sello']) ? $documents['firma_y_sello'] : [];
            }
        }

        $add_signature = null;
        if (isset($group['add_firma_sello'])) {
            $add_signature = (bool) $group['add_firma_sello'];
        } else {
            $meta_value = get_user_meta($user_id, 'documentos_firma_y_sello_add_firma_sello', true);
            if ($meta_value !== '') {
                $add_signature = (bool) $meta_value;
            }
        }

        $signature_media = [];
        $seal_media      = [];

        if (! empty($group['firma'])) {
            $signature_media = self::normalize_media($group['firma']);
        }
        if (! $signature_media) {
            $signature_meta = get_user_meta($user_id, 'documentos_firma_y_sello_firma', true);
            $signature_media = self::normalize_media($signature_meta);
        }

        if (! empty($group['sello'])) {
            $seal_media = self::normalize_media($group['sello']);
        }
        if (! $seal_media) {
            $seal_meta = get_user_meta($user_id, 'documentos_firma_y_sello_sello', true);
            $seal_media = self::normalize_media($seal_meta);
        }

        return [
            'add_to_certificates' => $add_signature,
            'signature'           => $signature_media,
            'seal'                => $seal_media,
        ];
    }

    private static function extract_payments(string $scope, int $user_id): array
    {
        $sepa = [
            'status'           => null,
            'status_label'     => 'Sin información del mandato',
            'status_variant'   => 'info',
            'locked'           => false,
            'documents'        => [
                'signed'  => [],
                'pending' => [],
            ],
            'fields'           => [],
        ];
        $payment_type = '';

        $debtor_fields = [
            'nombre_deudor' => [
                'label' => 'Nombre completo',
                'value' => '',
                'name'  => 'nombre_deudor',
            ],
            'direccion_deudor' => [
                'label' => 'Dirección',
                'value' => '',
                'name'  => 'direccion_deudor',
            ],
            'codigo_postal' => [
                'label' => 'Código postal',
                'value' => '',
                'name'  => 'codigo_postal',
            ],
            'poblacion' => [
                'label' => 'Población',
                'value' => '',
                'name'  => 'poblacion',
            ],
            'provincia' => [
                'label' => 'Provincia',
                'value' => '',
                'name'  => 'provincia',
            ],
            'pais_deudor' => [
                'label' => 'País',
                'value' => '',
                'name'  => 'pais_deudor',
            ],
            'numero_cuenta' => [
                'label' => 'Número de cuenta IBAN',
                'value' => '',
                'name'  => 'numero_cuenta',
            ],
            'swift_bic' => [
                'label' => 'SWIFT / BIC',
                'value' => '',
                'name'  => 'swift_bic',
            ],
        ];

        $group = [];
        if (function_exists('get_field')) {
            $group = get_field('gestion_pagos', $scope);
        }

        if (is_array($group) && isset($group['gestion_sepa'])) {
            $sepa_group = $group['gestion_sepa'];
            if (is_array($sepa_group)) {
                if (! empty($sepa_group['datos_deudor']) && is_array($sepa_group['datos_deudor'])) {
                    $debtor = $sepa_group['datos_deudor'];

                    foreach ($debtor_fields as $key => $field) {
                        $value = self::resolve_debtor_array_value($debtor, $key);
                        if ($value !== '') {
                            $debtor_fields[$key]['value'] = self::sanitize_optional_text($value);
                        }
                    }

                    if (! empty($debtor['cp_poblacion_provincia'])) {
                        $location_parts = self::parse_debtor_location((string) $debtor['cp_poblacion_provincia']);
                        foreach ($location_parts as $location_key => $location_value) {
                            if ($location_value === '') {
                                continue;
                            }

                            if (isset($debtor_fields[$location_key]) && $debtor_fields[$location_key]['value'] === '') {
                                $debtor_fields[$location_key]['value'] = self::sanitize_optional_text($location_value);
                            }
                        }
                    }
                }

                if (! empty($sepa_group['estado_documentos'])) {
                    $status_group = $sepa_group['estado_documentos'];
                    if (is_array($status_group)) {
                        if (isset($status_group['estado_sepa'])) {
                            $sepa['status'] = (bool) $status_group['estado_sepa'];
                        }
                        if (isset($status_group['metodo_de_pago'])) {
                            $payment_type = self::extract_payment_label($status_group['metodo_de_pago']);
                        }
                        if (! empty($status_group['documento_sepa_firmado'])) {
                            $sepa['documents']['signed'] = self::normalize_media($status_group['documento_sepa_firmado']);
                        }
                        if (! empty($status_group['documento_sepa_sin_firmar'])) {
                            $sepa['documents']['pending'] = self::normalize_media($status_group['documento_sepa_sin_firmar']);
                        }
                    }
                }
            }
        }

        $debtor_fields = self::hydrate_debtor_meta($debtor_fields, $user_id);

        if ($payment_type === '') {
            $type_meta = get_user_meta($user_id, 'gestion_pagos_gestion_sepa_estado_documentos_metodo_de_pago', true);
            if ($type_meta === '') {
                $type_meta = get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_tipo_pago', true);
            }
            $payment_type = self::extract_payment_label($type_meta);
        }
        if ($sepa['status'] === null) {
            $status_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_estado_sepa',
                true
            );
            if ($status_meta !== '') {
                $sepa['status'] = (bool) $status_meta;
            }
        }
        if (! $sepa['documents']['signed']) {
            $signed_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_firmado',
                true
            );
            $sepa['documents']['signed'] = self::normalize_media($signed_meta);
        }
        if (! $sepa['documents']['pending']) {
            $pending_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_sin_firmar',
                true
            );
            $sepa['documents']['pending'] = self::normalize_media($pending_meta);
        }

        $selected_method = self::normalize_payment_method($payment_type);
        if ($selected_method === '') {
            $selected_method = 'domiciliacion';
        }

        $sepa['locked'] = $selected_method === 'domiciliacion' && $sepa['status'] === true;
        $sepa = self::decorate_sepa_status($sepa);
        $sepa['fields'] = array_values($debtor_fields);

        return [
            'selected_method' => $selected_method,
            'raw_method'      => $payment_type,
            'methods'         => self::available_payment_methods(),
            'sepa'            => $sepa,
            'transfer'        => self::extract_transfer_details(),
        ];
    }

    private static function decorate_sepa_status(array $sepa): array
    {
        if ($sepa['status'] === true) {
            $sepa['status_label'] = 'SEPA válido y activo';
            $sepa['status_variant'] = 'success';
        } elseif ($sepa['status'] === false) {
            $sepa['status_label'] = 'Pendiente de validar';
            $sepa['status_variant'] = 'warning';
        } else {
            $sepa['status_label'] = 'Sin información del mandato';
            $sepa['status_variant'] = 'info';
        }

        return $sepa;
    }

    private static function hydrate_debtor_meta(array $fields, int $user_id): array
    {
        $combined_meta = '';

        foreach ($fields as $key => $field) {
            if ($field['value'] === '') {
                $value = self::get_debtor_meta_value($user_id, $key);

                if ($value === '' && self::is_location_field($key)) {
                    if ($combined_meta === '') {
                        $combined_meta = self::get_debtor_meta_value($user_id, 'cp_poblacion_provincia');
                    }

                    if ($combined_meta !== '') {
                        $parsed = self::parse_debtor_location($combined_meta);
                        $value = $parsed[$key] ?? '';
                    }
                }

                $fields[$key]['value'] = self::sanitize_optional_text($value);
            }
        }

        return $fields;
    }

    private static function resolve_debtor_array_value(array $data, string $key): string
    {
        $candidates = array_merge([$key], self::alternate_debtor_field_names($key));

        foreach ($candidates as $candidate) {
            if (! array_key_exists($candidate, $data)) {
                continue;
            }

            $value = $data[$candidate];
            if ($value === '' || $value === null) {
                continue;
            }

            return (string) $value;
        }

        return '';
    }

    private static function get_debtor_meta_value(int $user_id, string $key): string
    {
        $base_key = sprintf('gestion_pagos_gestion_sepa_datos_deudor_%s', $key);
        $candidates = array_merge([$base_key], self::alternate_debtor_meta_keys($key));

        foreach ($candidates as $candidate) {
            $value = get_user_meta($user_id, $candidate, true);
            if ($value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private static function alternate_debtor_field_names(string $key): array
    {
        switch ($key) {
            case 'numero_cuenta':
                return ['numero_cienta', 'iban'];
            case 'codigo_postal':
                return ['cp', 'codigo_postal_deudor', 'cp_deudor'];
            case 'poblacion':
                return ['ciudad', 'localidad', 'poblacion_deudor'];
            case 'provincia':
                return ['region', 'provincia_deudor'];
            case 'pais_deudor':
                return ['pais', 'pais_deudor_nombre'];
            default:
                return [];
        }
    }

    private static function alternate_debtor_meta_keys(string $key): array
    {
        $suffixes = [];

        switch ($key) {
            case 'numero_cuenta':
                $suffixes = ['numero_cienta', 'iban'];
                break;
            case 'codigo_postal':
                $suffixes = ['cp', 'codigo_postal_deudor'];
                break;
            case 'poblacion':
                $suffixes = ['ciudad', 'localidad', 'poblacion_deudor'];
                break;
            case 'provincia':
                $suffixes = ['region', 'provincia_deudor'];
                break;
            case 'pais_deudor':
                $suffixes = ['pais', 'pais_deudor_nombre'];
                break;
            default:
                $suffixes = [];
                break;
        }

        return array_map(
            static function ($suffix) {
                return sprintf('gestion_pagos_gestion_sepa_datos_deudor_%s', $suffix);
            },
            $suffixes
        );
    }

    private static function parse_debtor_location(string $value): array
    {
        $result = [
            'codigo_postal' => '',
            'poblacion'     => '',
            'provincia'     => '',
        ];

        $clean = trim(preg_replace('/\s+/', ' ', $value));
        if ($clean === '') {
            return $result;
        }

        $rest = $clean;

        if (preg_match('/^(\d{4,5})[\s,.-]*(.+)$/u', $clean, $matches)) {
            $result['codigo_postal'] = trim($matches[1]);
            $rest = trim($matches[2]);
        }

        if ($rest === '') {
            return $result;
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/u', $rest, $matches)) {
            $result['poblacion'] = trim($matches[1]);
            $result['provincia'] = trim($matches[2]);
            return $result;
        }

        foreach ([',', '·', ' - ', ' / '] as $delimiter) {
            if (strpos($rest, $delimiter) !== false) {
                $parts = array_map('trim', explode($delimiter, $rest, 2));
                $result['poblacion'] = $parts[0] ?? '';
                $result['provincia'] = $parts[1] ?? '';
                return $result;
            }
        }

        $result['poblacion'] = $rest;

        return $result;
    }

    private static function is_location_field(string $key): bool
    {
        return in_array($key, ['codigo_postal', 'poblacion', 'provincia'], true);
    }

    private static function extract_payment_label($value): string
    {
        if (is_array($value)) {
            $candidate = $value['label'] ?? $value['value'] ?? '';
            return self::sanitize_optional_text((string) $candidate);
        }

        if (is_object($value) && isset($value->label)) {
            return self::sanitize_optional_text((string) $value->label);
        }

        return self::sanitize_optional_text((string) $value);
    }

    private static function available_payment_methods(): array
    {
        return [
            [
                'value' => 'domiciliacion',
                'label' => 'Domiciliación bancaria',
            ],
            [
                'value' => 'transferencia',
                'label' => 'Transferencia bancaria',
            ],
        ];
    }

    private static function normalize_payment_method(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = strtolower($value);
        $normalized = str_replace([' ', '-'], '_', $normalized);

        if (strpos($normalized, 'transfer') !== false) {
            return 'transferencia';
        }

        if (strpos($normalized, 'domic') !== false) {
            return 'domiciliacion';
        }

        return '';
    }

    private static function extract_transfer_details(): array
    {
        $details = [
            'holder'   => '',
            'bank'     => '',
            'iban'     => '',
            'bic'      => '',
        ];

        $options = [];
        if (function_exists('get_field')) {
            $options = get_field('datos_bancarios', SettingsPage::SUBMENU_SLUG);
        }

        if (! is_array($options) || empty($options)) {
            $options = [];
        }

        $details['holder'] = self::sanitize_optional_text(self::first_non_empty($options, [
            'titular',
            'titular_cuenta',
            'titular_360vo',
        ]));

        $details['bank'] = self::sanitize_optional_text(self::first_non_empty($options, [
            'entidad',
            'banco',
            'nombre_banco',
        ]));

        $iban = self::sanitize_optional_text(self::first_non_empty($options, [
            'iban_360vo',
            'iban',
        ]));
        if ($iban === '' && ! empty($options)) {
            $single = self::sanitize_optional_text(get_field('datos_bancarios_iban_360vo', SettingsPage::SUBMENU_SLUG));
            if ($single !== '') {
                $iban = $single;
            }
        }
        if ($iban === '') {
            $option = get_option('options_datos_bancarios_iban_360vo');
            if (is_string($option) && $option !== '') {
                $iban = self::sanitize_optional_text($option);
            }
        }

        if ($iban !== '') {
            $details['iban'] = trim(chunk_split(preg_replace('/[^A-Z0-9]/i', '', strtoupper($iban)), 4, ' '));
        }

        $details['bic'] = self::sanitize_optional_text(self::first_non_empty($options, [
            'bic',
            'swift',
            'swift_bic',
        ]));

        return $details;
    }

    private static function first_non_empty(array $source, array $keys)
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function normalize_media($value): array
    {
        if (is_array($value)) {
            $id  = isset($value['ID']) ? (int) $value['ID'] : (isset($value['id']) ? (int) $value['id'] : 0);
            $url = isset($value['url']) ? (string) $value['url'] : '';
            $filename = isset($value['filename']) ? (string) $value['filename'] : '';

            if ($id && $url === '') {
                $url = wp_get_attachment_url($id) ?: '';
            }
            if ($id && $filename === '') {
                $file = get_attached_file($id);
                if ($file) {
                    $filename = basename($file);
                }
            }

            return [
                'id'       => $id,
                'url'      => $url,
                'filename' => $filename,
            ];
        }

        $id = is_numeric($value) ? (int) $value : 0;
        if (! $id) {
            return [
                'id'       => 0,
                'url'      => '',
                'filename' => '',
            ];
        }

        $url  = wp_get_attachment_url($id) ?: '';
        $file = get_attached_file($id);

        return [
            'id'       => $id,
            'url'      => $url,
            'filename' => $file ? basename($file) : '',
        ];
    }

    private static function normalize_user_ids($value): array
    {
        $ids = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    if (isset($item['ID'])) {
                        $ids[] = (int) $item['ID'];
                    } elseif (isset($item['id'])) {
                        $ids[] = (int) $item['id'];
                    }
                } elseif (is_numeric($item)) {
                    $ids[] = (int) $item;
                }
            }
        } elseif (is_numeric($value)) {
            $ids[] = (int) $value;
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
