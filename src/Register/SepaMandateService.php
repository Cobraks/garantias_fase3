<?php

namespace GarantiasOnline360VO\Register;

use GarantiasOnline360VO\Docs\PrivateDocsManager;
use GarantiasOnline360VO\Rest\UserRestController;
use GarantiasOnline360VO\SettingsPage;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

class SepaMandateService
{
    public const TYPE_PENDING = 'pending';
    public const TYPE_SIGNED  = 'signed';

    private const OPTION_GROUP_DOCUMENTATION = 'documentacion';
    private const OPTION_FIELD_TEMPLATE      = 'base_sepa_vacio';

    private const OPTION_GROUP_BANKING   = 'datos_bancarios';
    private const OPTION_GROUP_CREDITOR  = 'datos_sepa_acreedor';
    private const OPTION_FIELD_PAYMENT   = 'tipo_pago_deudor';

    private const META_PENDING_FIELD = 'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_sin_firmar';
    private const META_SIGNED_FIELD  = 'gestion_pagos_gestion_sepa_estado_documentos_documento_sepa_firmado';

    private const META_PENDING_HASH      = '_go360_sepa_pending_hash';
    private const META_SIGNED_HASH       = '_go360_sepa_signed_hash';
    private const META_PENDING_FILENAME  = '_go360_sepa_pending_filename';
    private const META_SIGNED_FILENAME   = '_go360_sepa_signed_filename';
    private const META_PENDING_GENERATED = '_go360_sepa_pending_generated_at';
    private const META_SIGNED_GENERATED  = '_go360_sepa_signed_generated_at';
    private const META_PENDING_REFERENCE = '_go360_sepa_pending_reference';
    private const META_SIGNED_REFERENCE  = '_go360_sepa_signed_reference';

    private const DEFAULT_REFERENCE_PREFIX = 'GO';

    /**
     * Configuration exposed to the registration flow.
     *
     * @return array<string, mixed>
     */
    public static function get_frontend_config(): array
    {
        $template = self::get_template_source();
        $creditor = self::get_creditor_data();

        return [
            'templateUrl'     => $template['url'] ?? '',
            'templateName'    => $template['filename'] ?? '',
            'creditor'        => $creditor,
            'referencePrefix' => self::DEFAULT_REFERENCE_PREFIX,
            'fontkitUrl'      => esc_url_raw(plugins_url('assets/js/fontkit.umd.min.js', GARANTIAS360VO__FILE__)),
            'fontUrl'         => esc_url_raw(plugins_url('assets/fonts/RobotoMono-Regular.ttf', GARANTIAS360VO__FILE__)),
        ];
    }

    /**
     * Retrieve the SEPA base template configured in options.
     *
     * @return array{url: string, id: int, filename: string}|array<string, mixed>
     */
    public static function get_template_source(): array
    {
        $result = [
            'url'      => '',
            'id'       => 0,
            'filename' => '',
        ];

        if (! function_exists('get_field')) {
            return $result;
        }

        $file = get_field(self::OPTION_FIELD_TEMPLATE, SettingsPage::SUBMENU_SLUG);
        if (! $file) {
            $documentation = get_field(self::OPTION_GROUP_DOCUMENTATION, SettingsPage::SUBMENU_SLUG);
            if (is_array($documentation) && isset($documentation[self::OPTION_FIELD_TEMPLATE])) {
                $file = $documentation[self::OPTION_FIELD_TEMPLATE];
            }
        }

        if (! $file) {
            $file = get_field(self::OPTION_FIELD_TEMPLATE, 'option');
        }
        if (! $file) {
            $documentation = get_field(self::OPTION_GROUP_DOCUMENTATION, 'option');
            if (is_array($documentation) && isset($documentation[self::OPTION_FIELD_TEMPLATE])) {
                $file = $documentation[self::OPTION_FIELD_TEMPLATE];
            }
        }

        if (is_array($file)) {
            if (! empty($file['url'])) {
                $result['url'] = esc_url_raw((string) $file['url']);
            }
            if (! empty($file['ID'])) {
                $result['id'] = (int) $file['ID'];
                if ($result['url'] === '') {
                    $url = wp_get_attachment_url($result['id']);
                    if ($url) {
                        $result['url'] = esc_url_raw($url);
                    }
                }
            }
            if (! empty($file['filename'])) {
                $result['filename'] = sanitize_file_name((string) $file['filename']);
            } elseif (! empty($file['title'])) {
                $result['filename'] = sanitize_file_name((string) $file['title']);
            } elseif ($result['id']) {
                $path = get_attached_file($result['id']);
                if ($path) {
                    $result['filename'] = sanitize_file_name(basename($path));
                }
            }
        } elseif (is_string($file) && $file !== '') {
            $result['url'] = esc_url_raw($file);
        }

        return $result;
    }

    /**
     * Retrieve creditor configuration stored in options.
     *
     * @return array<string, string>
     */
    public static function get_creditor_data(): array
    {
        $data = [
            'reference'            => '',
            'id'                   => '',
            'name'                 => '',
            'address'              => '',
            'country'              => '',
            'postal_code'          => '',
            'city'                 => '',
            'province'             => '',
            'payment_type'         => 'recurrente',
            'payment_type_label'   => 'Recurrente',
        ];

        if (! function_exists('get_field')) {
            return $data;
        }

        $banking = get_field(self::OPTION_GROUP_BANKING, SettingsPage::SUBMENU_SLUG);
        if (! $banking) {
            $banking = get_field(self::OPTION_GROUP_BANKING, 'option');
        }
        $creditor = null;
        if (is_array($banking) && isset($banking[self::OPTION_GROUP_CREDITOR])) {
            $creditor = $banking[self::OPTION_GROUP_CREDITOR];
        }
        if (! $creditor) {
            $creditor = get_field(self::OPTION_GROUP_CREDITOR, SettingsPage::SUBMENU_SLUG);
        }
        if (! $creditor) {
            $creditor = get_field(self::OPTION_GROUP_CREDITOR, 'option');
        }

        if (is_array($creditor)) {
            $data['id']          = self::string_value($creditor, 'identificador_acreedor');
            $data['name']        = self::string_value($creditor, 'nombre_acreedor');
            $data['address']     = self::string_value($creditor, 'direccion_acreedor');
            $data['country']     = self::string_value($creditor, 'pais_acreedor');
            $data['postal_code'] = self::string_value($creditor, 'cp_acreedor');
            $data['city']        = self::string_value($creditor, 'poblacion_acreedor');
            $data['province']    = self::string_value($creditor, 'provincia_acreedor');
            if (! empty($creditor['referencia_acreedor'])) {
                $data['reference'] = sanitize_text_field((string) $creditor['referencia_acreedor']);
            }
        }

        $payment = null;
        if (is_array($banking) && isset($banking[self::OPTION_FIELD_PAYMENT])) {
            $payment = $banking[self::OPTION_FIELD_PAYMENT];
        }
        if (! $payment) {
            $payment = get_field(self::OPTION_FIELD_PAYMENT, SettingsPage::SUBMENU_SLUG);
        }
        if (! $payment) {
            $payment = get_field(self::OPTION_FIELD_PAYMENT, 'option');
        }

        if (is_array($payment)) {
            if (! empty($payment['value'])) {
                $data['payment_type'] = sanitize_key((string) $payment['value']);
            }
            if (! empty($payment['label'])) {
                $data['payment_type_label'] = sanitize_text_field((string) $payment['label']);
            }
        } elseif (is_string($payment) && $payment !== '') {
            $data['payment_type'] = sanitize_key($payment);
        }

        if ($data['payment_type'] === '') {
            $data['payment_type'] = 'recurrente';
        }

        return $data;
    }

    /**
     * Store a newly generated pending mandate in the private storage.
     *
     * @param array<string, mixed> $context
     * @return array<string, string|bool>|WP_Error
     */
    public static function store_pending_mandate(int $user_id, string $binary, array $context = [])
    {
        return self::store_document($user_id, self::TYPE_PENDING, $binary, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string|bool>|WP_Error
     */
    public static function store_signed_mandate(int $user_id, string $binary, array $context = [])
    {
        return self::store_document($user_id, self::TYPE_SIGNED, $binary, $context);
    }

    public static function clear_pending_mandate(int $user_id): void
    {
        self::clear_document($user_id, self::TYPE_PENDING);
    }

    public static function clear_signed_mandate(int $user_id): void
    {
        self::clear_document($user_id, self::TYPE_SIGNED);
    }

    /**
     * Build a normalized representation of a SEPA document entry.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    public static function normalize_document($value, int $user_id, string $type): array
    {
        if (is_array($value) && isset($value['hash'])) {
            $hash = (string) $value['hash'];
            $filename = sanitize_file_name((string) ($value['filename'] ?? 'mandato-sepa.pdf'));
            $reference = isset($value['reference']) ? sanitize_text_field((string) $value['reference']) : '';
            $generated = isset($value['generated_at']) ? (string) $value['generated_at'] : '';
            $meta = self::get_document_meta($user_id, $type);
            if ($reference === '' && $meta['reference'] !== '') {
                $reference = $meta['reference'];
            }
            if ($generated === '' && $meta['generated_at'] !== '') {
                $generated = $meta['generated_at'];
            }

            return [
                'id'           => 0,
                'url'          => $hash !== '' ? self::build_download_url($user_id, $type) : '',
                'filename'     => $filename,
                'hash'         => $hash,
                'reference'    => $reference,
                'generated_at' => $generated,
                'private'      => true,
            ];
        }

        if (is_numeric($value) && (int) $value > 0) {
            $id = (int) $value;
            return [
                'id'       => $id,
                'url'      => wp_get_attachment_url($id) ?: '',
                'filename' => ($file = get_attached_file($id)) ? basename($file) : '',
                'hash'     => '',
                'reference'=> '',
                'generated_at' => '',
                'private'  => false,
            ];
        }

        if (is_array($value)) {
            $id = isset($value['ID']) ? (int) $value['ID'] : (isset($value['id']) ? (int) $value['id'] : 0);
            $url = isset($value['url']) ? esc_url_raw((string) $value['url']) : '';
            $filename = isset($value['filename']) ? sanitize_file_name((string) $value['filename']) : '';
            return [
                'id'       => $id,
                'url'      => $url,
                'filename' => $filename,
                'hash'     => '',
                'reference'=> '',
                'generated_at' => '',
                'private'  => false,
            ];
        }

        return [
            'id'       => 0,
            'url'      => '',
            'filename' => '',
            'hash'     => '',
            'reference'=> '',
            'generated_at' => '',
            'private'  => false,
        ];
    }

    /**
     * Retrieve metadata about a stored mandate.
     *
     * @return array{hash: string, filename: string, generated_at: string, reference: string}
     */
    public static function get_document_meta(int $user_id, string $type): array
    {
        $keys = self::meta_keys_for_type($type);

        return [
            'hash'        => (string) get_user_meta($user_id, $keys['hash'], true),
            'filename'    => (string) get_user_meta($user_id, $keys['filename'], true),
            'generated_at'=> (string) get_user_meta($user_id, $keys['generated'], true),
            'reference'   => (string) get_user_meta($user_id, $keys['reference'], true),
        ];
    }

    /**
     * Retrieve the binary contents of a stored mandate.
     */
    public static function retrieve_document(int $user_id, string $type): ?string
    {
        $meta = self::get_document_meta($user_id, $type);
        if ($meta['hash'] === '') {
            return null;
        }

        return PrivateDocsManager::retrieve($meta['hash'], 'pdf');
    }

    /**
     * Build a download URL for a stored mandate.
     */
    public static function build_download_url(int $user_id, string $type, bool $with_nonce = true): string
    {
        $type = $type === self::TYPE_SIGNED ? self::TYPE_SIGNED : self::TYPE_PENDING;
        $url  = rest_url(UserRestController::NAMESPACE . '/usuarios/' . $user_id . '/sepa/' . $type);
        if ($with_nonce) {
            $url = add_query_arg('_wpnonce', wp_create_nonce('wp_rest'), $url);
        }

        $scheme = wp_parse_url(home_url(), PHP_URL_SCHEME);
        return set_url_scheme($url, $scheme ? $scheme : 'https');
    }

    /**
     * Normalize a reference string.
     */
    public static function sanitize_reference(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9\-_.]/', '', $value ?? '');
        $value = $value !== null ? $value : '';

        if ($value === '') {
            return '';
        }

        return substr($value, 0, 64);
    }

    /**
     * Persist document metadata for a specific type.
     *
     * @param array<string, mixed> $data
     */
    public static function persist_document_meta(int $user_id, string $type, array $data): void
    {
        $keys = self::meta_keys_for_type($type);

        $hash      = (string) ($data['hash'] ?? '');
        $filename  = sanitize_file_name((string) ($data['filename'] ?? 'mandato-sepa.pdf'));
        $generated = (string) ($data['generated_at'] ?? '');
        $reference = self::sanitize_reference((string) ($data['reference'] ?? ''));

        update_user_meta($user_id, $keys['hash'], $hash);
        update_user_meta($user_id, $keys['filename'], $filename);
        update_user_meta($user_id, $keys['generated'], $generated);
        update_user_meta($user_id, $keys['reference'], $reference);

        $field_key = $type === self::TYPE_SIGNED ? self::META_SIGNED_FIELD : self::META_PENDING_FIELD;
        $payload = [
            'hash'         => $hash,
            'filename'     => $filename,
            'generated_at' => $generated,
            'reference'    => $reference,
            'private'      => true,
            'url'          => '',
        ];

        update_user_meta($user_id, $field_key, $payload);
    }

    /**
     * Internal helper to read option values safely.
     *
     * @param array<string, mixed> $data
     */
    private static function string_value(array $data, string $key): string
    {
        if (! isset($data[$key])) {
            return '';
        }

        $value = $data[$key];
        if (is_array($value) && isset($value['value'])) {
            $value = $value['value'];
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Store a mandate for a given type.
     *
     * @param array<string, mixed> $context
     * @return array<string, string|bool>|WP_Error
     */
    private static function store_document(int $user_id, string $type, string $binary, array $context)
    {
        $hash = PrivateDocsManager::store($binary, 'pdf');
        if ($hash === '') {
            return new WP_Error('go_sepa_store_failed', __('No se pudo guardar el documento SEPA.', 'garantias-online-360vo'));
        }

        $filename = isset($context['filename']) ? sanitize_file_name((string) $context['filename']) : '';
        if ($filename === '') {
            $filename = 'mandato-sepa.pdf';
        }

        $generated = isset($context['generated_at']) && $context['generated_at'] !== ''
            ? (string) $context['generated_at']
            : gmdate('c');

        $reference = isset($context['reference']) ? self::sanitize_reference((string) $context['reference']) : '';
        if ($reference === '') {
            $reference = self::DEFAULT_REFERENCE_PREFIX . '-' . strtoupper(dechex(time())) . '-' . strtoupper(substr($hash, 0, 6));
        }

        self::persist_document_meta($user_id, $type, [
            'hash'         => $hash,
            'filename'     => $filename,
            'generated_at' => $generated,
            'reference'    => $reference,
        ]);

        return [
            'hash'         => $hash,
            'filename'     => $filename,
            'generated_at' => $generated,
            'reference'    => $reference,
            'url'          => self::build_download_url($user_id, $type),
            'private'      => true,
        ];
    }

    /**
     * Resolve meta keys for a specific document type.
     *
     * @return array{hash: string, filename: string, generated: string, reference: string}
     */
    private static function meta_keys_for_type(string $type): array
    {
        if ($type === self::TYPE_SIGNED) {
            return [
                'hash'      => self::META_SIGNED_HASH,
                'filename'  => self::META_SIGNED_FILENAME,
                'generated' => self::META_SIGNED_GENERATED,
                'reference' => self::META_SIGNED_REFERENCE,
            ];
        }

        return [
            'hash'      => self::META_PENDING_HASH,
            'filename'  => self::META_PENDING_FILENAME,
            'generated' => self::META_PENDING_GENERATED,
            'reference' => self::META_PENDING_REFERENCE,
        ];
    }

    private static function clear_document(int $user_id, string $type): void
    {
        $keys = self::meta_keys_for_type($type);

        foreach ($keys as $meta_key) {
            update_user_meta($user_id, $meta_key, '');
        }

        $field_key = $type === self::TYPE_SIGNED ? self::META_SIGNED_FIELD : self::META_PENDING_FIELD;
        update_user_meta($user_id, $field_key, []);
    }
}
