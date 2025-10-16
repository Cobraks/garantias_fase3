<?php

namespace GarantiasOnline360VO\Account;

use GarantiasOnline360VO\Register\SepaMandateService;
use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;
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

        $profile = UserProfileResolver::build_from_user($user);
        $company_profile = $profile['company'];

        $company = [
            'name'       => $company_profile['name'] ?? '',
            'trade_name' => $company_profile['trade_name'] ?? '',
            'legal_name' => $company_profile['legal_name'] ?? '',
            'tax_id'     => $company_profile['tax_id'] ?? '',
            'type'       => $company_profile['type'] ?? ['value' => '', 'label' => ''],
            'address'    => $company_profile['address'] ?? [
                'street' => '',
                'city'   => '',
                'state'  => '',
                'zip'    => '',
                'country'=> '',
            ],
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

        $address = $company['address'];
        if (! array_filter($address)) {
            $address = [
                'street'  => $contact_meta['direccion'] ?? get_user_meta($user_id, 'datos_usuario_direccion', true),
                'city'    => $contact_meta['localidad'] ?? get_user_meta($user_id, 'datos_usuario_localidad', true),
                'state'   => $contact_meta['provincia'] ?? get_user_meta($user_id, 'datos_usuario_provincia', true),
                'zip'     => $contact_meta['codigo_postal'] ?? get_user_meta($user_id, 'datos_usuario_codigo_postal', true),
                'country' => $contact_meta['pais'] ?? get_user_meta($user_id, 'datos_usuario_pais', true),
            ];
        }

        $assigned = self::extract_commercials($scope, $user_id);

        $documents = self::extract_documents($scope, $user_id);
        $payments  = self::extract_payments($scope, $user_id);
        $role_keys = array_map('sanitize_key', (array) $user->roles);
        $is_commercial_account = in_array('go_comercial', $role_keys, true);

        return [
            'user' => [
                'id'                   => $user_id,
                'name'                 => $profile['personal_name'],
                'full_name'            => $profile['personal_full_name'],
                'first_name'           => $profile['personal_first_name'],
                'last_name'            => $profile['personal_last_name'],
                'username'             => $profile['username'],
                'email'                => $profile['email'],
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
            'workshop'      => self::extract_workshop($scope, $user_id),
            'clients'       => $is_commercial_account
                ? self::extract_commercial_clients($user_id)
                : [],
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
                'name'  => UserProfileResolver::get_personal_name($commercial),
                'full_name' => UserProfileResolver::get_full_name($commercial),
                'email' => sanitize_email($commercial->user_email),
                'phone' => self::sanitize_optional_text(
                    get_user_meta($commercial_id, 'datos_usuario_telefono', true)
                ),
                'profile_image' => $profile_image,
            ];
        }

        return $commercials;
    }

    private static function extract_commercial_clients(int $commercial_id): array
    {
        $clients = [];

        $query = new \WP_User_Query([
            'role__in' => ['go_profesional'],
            'number'   => -1,
            'fields'   => 'ids',
        ]);

        $candidate_ids = [];
        if ($query instanceof \WP_User_Query) {
            $candidate_ids = array_filter(
                array_map('intval', (array) $query->get_results()),
                static function ($id): bool {
                    return is_int($id) && $id > 0;
                }
            );
        }

        if (! $candidate_ids) {
            return $clients;
        }

        foreach ($candidate_ids as $client_id) {

            $assigned_ids = [];

            if (function_exists('get_field')) {
                $assigned_field = get_field('ajustes_usuarios_comercial_asignado', 'user_' . $client_id);
                $assigned_ids   = self::normalize_user_ids($assigned_field);
            }

            if (! $assigned_ids) {
                $meta_value   = get_user_meta($client_id, 'ajustes_usuarios_comercial_asignado', true);
                $assigned_ids = self::normalize_user_ids($meta_value);
            }

            if (! in_array($commercial_id, $assigned_ids, true)) {
                continue;
            }

            $client_user = get_user_by('id', $client_id);
            if (! $client_user instanceof WP_User) {
                continue;
            }

            $profile = UserProfileResolver::build_from_user($client_user);

            $profile_image = ['id' => 0, 'url' => '', 'filename' => ''];
            if (function_exists('get_field')) {
                $image = get_field('profile_image', 'user_' . $client_id);
                if ($image) {
                    $profile_image = self::normalize_media($image);
                }
            }

            if (! $profile_image['url']) {
                $profile_image['url'] = get_avatar_url($client_id, ['size' => 128]);
            }

            $contact_meta = self::get_meta_group('user_' . $client_id, 'datos_usuario');
            $phone        = '';
            $phone_candidates = [
                $contact_meta['telefono'] ?? '',
                get_user_meta($client_id, 'datos_usuario_telefono', true),
            ];

            foreach ($phone_candidates as $candidate) {
                $candidate = self::sanitize_optional_text($candidate);
                if ($candidate !== '') {
                    $phone = $candidate;
                    break;
                }
            }

            $clients[] = [
                'id'            => $client_id,
                'company_name'  => $profile['company']['name'] ?? '',
                'username'      => $profile['username'] ?? '',
                'email'         => sanitize_email($profile['email'] ?? $client_user->user_email),
                'phone'         => $phone,
                'profile_image' => $profile_image,
                'contact_name'  => $profile['personal_full_name'] ?: $profile['personal_name'],
            ];
        }

        if ($clients) {
            usort(
                $clients,
                static function (array $a, array $b): int {
                    $labelA = strtolower(trim((string) ($a['company_name'] ?? '')));
                    $labelB = strtolower(trim((string) ($b['company_name'] ?? '')));

                    if ($labelA === '') {
                        $labelA = strtolower(trim((string) ($a['contact_name'] ?? '')));
                    }

                    if ($labelB === '') {
                        $labelB = strtolower(trim((string) ($b['contact_name'] ?? '')));
                    }

                    return $labelA <=> $labelB;
                }
            );
        }

        return $clients;
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

    private static function extract_workshop(string $scope, int $user_id): array
    {
        $defaults = [
            'has_workshop'   => false,
            'name'           => '',
            'fiscal_name'    => '',
            'tax_id'         => '',
            'contact_person' => '',
            'phone'          => '',
            'email'          => '',
            'address'        => '',
        ];

        $group = [];

        if (function_exists('get_field')) {
            $services = get_field('servicios', $scope);
            if (is_array($services) && isset($services['taller']) && is_array($services['taller'])) {
                $group = $services['taller'];
            }
        }

        $has_workshop_candidates = [
            $group['tiene_taller'] ?? null,
            get_user_meta($user_id, 'servicios_taller_tiene_taller', true),
        ];

        foreach ($has_workshop_candidates as $candidate) {
            $normalized = self::normalize_bool($candidate);
            if ($normalized !== null) {
                $defaults['has_workshop'] = $normalized;
                break;
            }
        }

        $field_map = [
            'name'           => ['nombre_taller'],
            'fiscal_name'    => ['denominacion_fiscal'],
            'tax_id'         => ['cif_taller'],
            'contact_person' => ['persona_contacto_taller'],
            'phone'          => ['telefono_taller'],
            'email'          => ['correo_taller'],
            'address'        => ['direccion_taller'],
        ];

        foreach ($field_map as $key => $field_names) {
            $value = '';

            foreach ($field_names as $field_name) {
                if (isset($group[$field_name]) && $group[$field_name] !== '') {
                    $value = (string) $group[$field_name];
                    break;
                }

                $meta_value = get_user_meta($user_id, 'servicios_taller_' . $field_name, true);
                if (is_string($meta_value) && trim($meta_value) !== '') {
                    $value = (string) $meta_value;
                    break;
                }
            }

            if ($key === 'email') {
                $defaults[$key] = sanitize_email((string) $value);
            } else {
                $defaults[$key] = self::sanitize_optional_text($value);
            }
        }

        return $defaults;
    }

    private static function extract_payments(string $scope, int $user_id): array
    {
        $sepa = [
            'status'           => null,
            'status_label'     => 'Sin información del mandato',
            'status_variant'   => 'info',
            'locked'           => false,
            'requested'        => false,
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

                    $combined_location = self::resolve_debtor_array_value($debtor, 'cp_poblacion_provincia');
                    if ($combined_location !== '') {
                        $location_parts = self::parse_debtor_location($combined_location);
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
                            $sepa['documents']['signed'] = SepaMandateService::normalize_document(
                                $status_group['documento_sepa_firmado'],
                                $user_id,
                                SepaMandateService::TYPE_SIGNED
                            );
                        }
                        if (! empty($status_group['documento_sepa_sin_firmar'])) {
                            $sepa['documents']['pending'] = SepaMandateService::normalize_document(
                                $status_group['documento_sepa_sin_firmar'],
                                $user_id,
                                SepaMandateService::TYPE_PENDING
                            );
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
            $sepa['documents']['signed'] = SepaMandateService::normalize_document(
                $signed_meta,
                $user_id,
                SepaMandateService::TYPE_SIGNED
            );
        }
        if (! $sepa['documents']['pending']) {
            $pending_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_sin_firmar',
                true
            );
            $sepa['documents']['pending'] = SepaMandateService::normalize_document(
                $pending_meta,
                $user_id,
                SepaMandateService::TYPE_PENDING
            );
        }

        $selected_method = self::normalize_payment_method($payment_type);
        if ($selected_method === '') {
            $selected_method = 'domiciliacion';
        }

        $pending_document = $sepa['documents']['pending'];
        $has_pending_request = is_array($pending_document)
            && isset($pending_document['hash'])
            && $pending_document['hash'] !== '';

        if ($has_pending_request && $sepa['status'] !== true) {
            $sepa['requested'] = true;
            $selected_method   = 'transferencia';
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
        $candidates = array_merge(
            [
                sprintf('gestion_pagos_gestion_sepa_datos_deudor_%s', $key),
                sprintf('gestion_pagos_gestion_sepa_%s', $key),
            ],
            self::alternate_debtor_meta_keys($key)
        );

        $seen = [];

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            $value = get_user_meta($user_id, $candidate, true);
            if ($value !== '' && $value !== null) {
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
        switch ($key) {
            case 'numero_cuenta':
                return [
                    'gestion_pagos_gestion_sepa_datos_deudor_numero_cuenta',
                    'gestion_pagos_gestion_sepa_numero_cuenta',
                    'gestion_pagos_gestion_sepa_numero_cienta',
                    'gestion_pagos_gestion_sepa_iban',
                    'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta',
                    'gestion_pagos_gestion_sepa_datos_deudor_iban',
                ];
            case 'codigo_postal':
                return [
                    'gestion_pagos_gestion_sepa_codigo_postal',
                    'gestion_pagos_gestion_sepa_cp',
                    'gestion_pagos_gestion_sepa_cp_deudor',
                    'gestion_pagos_gestion_sepa_codigo_postal_deudor',
                    'gestion_pagos_gestion_sepa_datos_deudor_cp',
                    'gestion_pagos_gestion_sepa_datos_deudor_codigo_postal',
                ];
            case 'poblacion':
                return [
                    'gestion_pagos_gestion_sepa_poblacion',
                    'gestion_pagos_gestion_sepa_ciudad',
                    'gestion_pagos_gestion_sepa_localidad',
                    'gestion_pagos_gestion_sepa_poblacion_deudor',
                    'gestion_pagos_gestion_sepa_datos_deudor_poblacion',
                    'gestion_pagos_gestion_sepa_datos_deudor_ciudad',
                ];
            case 'provincia':
                return [
                    'gestion_pagos_gestion_sepa_provincia',
                    'gestion_pagos_gestion_sepa_region',
                    'gestion_pagos_gestion_sepa_provincia_deudor',
                    'gestion_pagos_gestion_sepa_datos_deudor_provincia',
                    'gestion_pagos_gestion_sepa_datos_deudor_region',
                ];
            case 'pais_deudor':
                return [
                    'gestion_pagos_gestion_sepa_pais_deudor',
                    'gestion_pagos_gestion_sepa_pais',
                    'gestion_pagos_gestion_sepa_pais_deudor_nombre',
                    'gestion_pagos_gestion_sepa_datos_deudor_pais',
                    'gestion_pagos_gestion_sepa_datos_deudor_pais_deudor',
                ];
            case 'cp_poblacion_provincia':
                return [
                    'gestion_pagos_gestion_sepa_cp_poblacion_provincia',
                ];
            case 'direccion_deudor':
                return ['gestion_pagos_gestion_sepa_direccion_deudor'];
            case 'nombre_deudor':
                return ['gestion_pagos_gestion_sepa_nombre_deudor'];
            case 'swift_bic':
                return ['gestion_pagos_gestion_sepa_swift_bic'];
            default:
                return [];
        }
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

    private static function normalize_bool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'si', 'sí', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
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
