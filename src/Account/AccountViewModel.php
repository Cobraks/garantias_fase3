<?php

namespace GarantiasOnline360VO\Account;

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

        $notification_settings = self::get_meta_group($scope, 'ajustes_de_notificaciones');
        $same_as_registration  = isset($notification_settings['misma_direccion_registro'])
            ? (bool) $notification_settings['misma_direccion_registro']
            : null;
        $custom_notification   = $notification_settings['correo_electronico_notificaciones'] ?? '';

        if ($custom_notification === '') {
            $custom_notification = get_user_meta(
                $user_id,
                'ajustes_de_notificaciones_correo_electronico_notificaciones',
                true
            );
        }

        $custom_notification = sanitize_email((string) $custom_notification);

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

            $commercials[] = [
                'id'    => (int) $commercial->ID,
                'name'  => $commercial->display_name ?: $commercial->user_login,
                'email' => sanitize_email($commercial->user_email),
                'phone' => self::sanitize_optional_text(
                    get_user_meta($commercial_id, 'datos_usuario_telefono', true)
                ),
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
        $payments = [
            'holder'            => '',
            'address'           => '',
            'iban'              => '',
            'swift'             => '',
            'payment_type'      => '',
            'status'            => null,
            'signed_document'   => [],
            'pending_document'  => [],
        ];

        $group = [];
        if (function_exists('get_field')) {
            $group = get_field('gestion_pagos', $scope);
        }

        if (is_array($group) && isset($group['gestion_sepa'])) {
            $sepa = $group['gestion_sepa'];
            if (is_array($sepa)) {
                if (! empty($sepa['datos_deudor'])) {
                    $debtor = $sepa['datos_deudor'];
                    $payments['holder'] = self::sanitize_optional_text($debtor['nombre_deudor'] ?? '')
                        ?: self::sanitize_optional_text(get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_nombre_deudor', true));
                    $payments['address'] = self::sanitize_optional_text($debtor['direccion_deudor'] ?? '');
                    $payments['iban'] = self::sanitize_optional_text($debtor['numero_cienta'] ?? '');
                    $payments['swift'] = self::sanitize_optional_text($debtor['swift_bic'] ?? '');
                    $payments['payment_type'] = self::sanitize_optional_text(
                        is_array($debtor['tipo_pago'] ?? null)
                            ? ($debtor['tipo_pago']['label'] ?? $debtor['tipo_pago']['value'] ?? '')
                            : ($debtor['tipo_pago'] ?? '')
                    );
                }

                if (! empty($sepa['estado_documentos'])) {
                    $status = $sepa['estado_documentos'];
                    if (is_array($status)) {
                        if (isset($status['estado_sepa'])) {
                            $payments['status'] = (bool) $status['estado_sepa'];
                        }
                        if (! empty($status['documento_sepa_firmado'])) {
                            $payments['signed_document'] = self::normalize_media($status['documento_sepa_firmado']);
                        }
                        if (! empty($status['documento_sepa_sin_firmar'])) {
                            $payments['pending_document'] = self::normalize_media($status['documento_sepa_sin_firmar']);
                        }
                    }
                }
            }
        }

        if ($payments['address'] === '') {
            $payments['address'] = self::sanitize_optional_text(
                get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_direccion_deudor', true)
            );
        }
        if ($payments['payment_type'] === '') {
            $type_meta = get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_tipo_pago', true);
            if (is_array($type_meta)) {
                $payments['payment_type'] = self::sanitize_optional_text($type_meta['label'] ?? $type_meta['value'] ?? '');
            } else {
                $payments['payment_type'] = self::sanitize_optional_text($type_meta);
            }
        }
        if ($payments['iban'] === '') {
            $payments['iban'] = self::sanitize_optional_text(
                get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_numero_cienta', true)
            );
        }
        if ($payments['swift'] === '') {
            $payments['swift'] = self::sanitize_optional_text(
                get_user_meta($user_id, 'gestion_pagos_gestion_sepa_datos_deudor_swift_bic', true)
            );
        }
        if ($payments['status'] === null) {
            $status_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_estado_sepa',
                true
            );
            if ($status_meta !== '') {
                $payments['status'] = (bool) $status_meta;
            }
        }
        if (! $payments['signed_document']) {
            $signed_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_firmado',
                true
            );
            $payments['signed_document'] = self::normalize_media($signed_meta);
        }
        if (! $payments['pending_document']) {
            $pending_meta = get_user_meta(
                $user_id,
                'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_sin_firmar',
                true
            );
            $payments['pending_document'] = self::normalize_media($pending_meta);
        }

        return $payments;
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
