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
    private const META_STATUS_FIELD           = 'gestion_pagos_gestion_sepa_estado_documentos_estado_sepa';
    private const META_ACTIVATE_FIELD         = 'gestion_pagos_gestion_sepa_estado_documentos_activar_sepa';
    private const META_ACTIVATE_STATE_FIELD   = '_go360_sepa_activation_state';
    private const META_PAYMENT_FIELD          = 'gestion_pagos_gestion_sepa_estado_documentos_metodo_de_pago';
    private const META_DISABLED_MESSAGE_FIELD = 'gestion_pagos_gestion_sepa_estado_documentos_mensaje_deshabilitado';

    public const ACTIVATION_ENABLED  = 'activada';
    public const ACTIVATION_DISABLED = 'desactivada';
    public const ACTIVATION_PENDING  = 'pendiente';

    private const META_PENDING_HASH      = '_go360_sepa_pending_hash';
    private const META_SIGNED_HASH       = '_go360_sepa_signed_hash';
    private const META_PENDING_FILENAME  = '_go360_sepa_pending_filename';
    private const META_SIGNED_FILENAME   = '_go360_sepa_signed_filename';
    private const META_PENDING_GENERATED = '_go360_sepa_pending_generated_at';
    private const META_SIGNED_GENERATED  = '_go360_sepa_signed_generated_at';
    private const META_PENDING_REFERENCE = '_go360_sepa_pending_reference';
    private const META_SIGNED_REFERENCE  = '_go360_sepa_signed_reference';

    public const STATUS_UNFILLED           = 'sin_rellenar_sepa';
    public const STATUS_PENDING_SIGNATURE  = 'pendiente_firma';
    public const STATUS_PENDING_VALIDATION = 'pendiente_validacion';
    public const STATUS_SIGNED             = 'firmado';
    public const STATUS_DISABLED           = 'deshabilitado';

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
     * @return array<string, string>
     */
    public static function get_status_options(): array
    {
        return [
            self::STATUS_UNFILLED           => __('Sin rellenar SEPA', 'garantias-online-360vo'),
            self::STATUS_PENDING_SIGNATURE  => __('Pendiente de firma', 'garantias-online-360vo'),
            self::STATUS_PENDING_VALIDATION => __('Pendiente de validación', 'garantias-online-360vo'),
            self::STATUS_SIGNED             => __('SEPA firmado', 'garantias-online-360vo'),
            self::STATUS_DISABLED           => __('Deshabilitado', 'garantias-online-360vo'),
        ];
    }

    public static function status_label(string $status): string
    {
        $options = self::get_status_options();
        if (isset($options[$status])) {
            return $options[$status];
        }

        return $options[self::STATUS_UNFILLED];
    }

    public static function normalize_status(string $value): string
    {
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        if ($normalized === '') {
            return self::STATUS_UNFILLED;
        }

        switch ($normalized) {
            case '1':
            case 'true':
            case 'firmado':
            case self::STATUS_SIGNED:
                return self::STATUS_SIGNED;
            case 'pendiente_validacion':
            case 'pending_validation':
                return self::STATUS_PENDING_VALIDATION;
            case 'pendiente_firma':
            case 'pending_signature':
                return self::STATUS_PENDING_SIGNATURE;
            case 'deshabilitado':
            case 'inhabilitado':
            case 'disabled':
            case 'deactivated':
                return self::STATUS_DISABLED;
            case 'sin_rellenar_sepa':
            case 'sin_rellenar':
            case 'unfilled':
                return self::STATUS_UNFILLED;
            default:
                return self::STATUS_UNFILLED;
        }
    }

    /**
     * @param mixed $value
     * @return array{value: string, label: string}
     */
    public static function parse_status_field($value, int $user_id = 0): array
    {
        $raw   = '';
        $label = '';

        if (is_array($value)) {
            $raw   = isset($value['value']) ? (string) $value['value'] : (string) ($value['label'] ?? '');
            $label = isset($value['label']) ? (string) $value['label'] : '';
        } elseif (is_object($value) && isset($value->value)) {
            $raw   = (string) $value->value;
            $label = isset($value->label) ? (string) $value->label : '';
        } elseif (is_bool($value)) {
            $raw = $value ? '1' : '0';
        } elseif (is_scalar($value)) {
            $raw = (string) $value;
        }

        $status = self::normalize_status($raw);

        if ($status === self::STATUS_UNFILLED && $raw !== '') {
            if ($raw === '1') {
                $status = self::STATUS_SIGNED;
            } elseif ($raw === '0' && $user_id > 0) {
                $signed_meta = self::get_document_meta($user_id, self::TYPE_SIGNED);
                $pending_meta = self::get_document_meta($user_id, self::TYPE_PENDING);
                if (! empty($signed_meta['hash'])) {
                    $status = self::STATUS_PENDING_VALIDATION;
                } elseif (! empty($pending_meta['hash'])) {
                    $status = self::STATUS_PENDING_SIGNATURE;
                }
            }
        }

        if ($label === '') {
            $label = self::status_label($status);
        }

        return [
            'value' => $status,
            'label' => $label,
        ];
    }

    public static function build_status_payload(string $status): array
    {
        $normalized = self::normalize_status($status);

        return [
            'value' => $normalized,
            'label' => self::status_label($normalized),
        ];
    }

    public static function get_status(int $user_id): array
    {
        $stored = get_user_meta($user_id, self::META_STATUS_FIELD, true);
        $payload = self::parse_status_field($stored, $user_id);

        update_user_meta($user_id, self::META_STATUS_FIELD, $payload);

        return $payload;
    }

    public static function set_status(int $user_id, string $status): void
    {
        $payload = self::build_status_payload($status);
        update_user_meta($user_id, self::META_STATUS_FIELD, $payload);

        if ($payload['value'] !== self::STATUS_DISABLED) {
            self::clear_disabled_message($user_id);
        }
    }

    /**
     * @param mixed $value
     */
    public static function parse_activation_field($value): array
    {
        $label = '';
        $source = $value;

        if (is_array($value)) {
            if (isset($value['label'])) {
                $label = (string) $value['label'];
            }

            if (isset($value['state'])) {
                $source = $value['state'];
            } elseif (isset($value['value'])) {
                $source = $value['value'];
            }
        } elseif (is_object($value)) {
            if (isset($value->label)) {
                $label = (string) $value->label;
            }

            if (isset($value->state)) {
                $source = $value->state;
            } elseif (isset($value->value)) {
                $source = $value->value;
            }
        }

        $state = self::normalize_activation_state($source);
        $label = $label !== '' ? $label : self::get_activation_label($state);

        return [
            'value' => $state,
            'label' => $label,
            'state' => $state,
        ];
    }

    /**
     * @param mixed $value
     */
    public static function normalize_activation_state($value): string
    {
        if (is_bool($value)) {
            return $value ? self::ACTIVATION_ENABLED : self::ACTIVATION_DISABLED;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? self::ACTIVATION_ENABLED : self::ACTIVATION_DISABLED;
        }

        if (is_array($value)) {
            if (isset($value['state'])) {
                return self::normalize_activation_state($value['state']);
            }

            if (isset($value['value'])) {
                return self::normalize_activation_state($value['value']);
            }
        }

        if (is_object($value)) {
            if (isset($value->state)) {
                return self::normalize_activation_state($value->state);
            }

            if (isset($value->value)) {
                return self::normalize_activation_state($value->value);
            }
        }

        if (! is_scalar($value)) {
            return self::ACTIVATION_DISABLED;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return self::ACTIVATION_DISABLED;
        }

        $normalized = str_replace([' ', '-'], '_', $normalized);

        $enabled_values = [
            '1',
            'true',
            'yes',
            'on',
            'si',
            'sí',
            'activada',
            'activa',
            'habilitada',
            'enabled',
        ];

        if (in_array($normalized, $enabled_values, true)) {
            return self::ACTIVATION_ENABLED;
        }

        $pending_values = [
            'pendiente',
            'pendiente_de_domiciliacion',
            'pendiente_de_domiciliación',
            'pendiente_de_activacion',
            'pendiente_de_activación',
            'pending',
            'pending_activation',
            'pending_activation_review',
        ];

        if (in_array($normalized, $pending_values, true)) {
            return self::ACTIVATION_PENDING;
        }

        return self::ACTIVATION_DISABLED;
    }

    public static function build_activation_payload(string $state): array
    {
        $normalized = self::normalize_activation_state($state);

        return [
            'value' => $normalized,
            'label' => self::get_activation_label($normalized),
            'flag'  => $normalized === self::ACTIVATION_ENABLED ? 1 : 0,
        ];
    }

    private static function get_activation_label(string $state): string
    {
        switch ($state) {
            case self::ACTIVATION_ENABLED:
                return __('Activada', 'garantias-online-360vo');
            case self::ACTIVATION_PENDING:
                return __('Pendiente de domiciliación', 'garantias-online-360vo');
            default:
                return __('Desactivada', 'garantias-online-360vo');
        }
    }

    public static function get_activation_payload(int $user_id): array
    {
        $raw_flag   = get_user_meta($user_id, self::META_ACTIVATE_FIELD, true);
        $raw_state  = get_user_meta($user_id, self::META_ACTIVATE_STATE_FIELD, true);

        $source = [];
        if ($raw_state !== '') {
            $source['state'] = $raw_state;
        }
        if ($raw_flag !== '') {
            $source['value'] = $raw_flag;
        }
        if ($source === []) {
            $source = $raw_flag;
        }

        $payload = self::parse_activation_field($source);
        $storage = self::build_activation_payload($payload['value']);

        if ((string) $raw_flag !== (string) $storage['flag']) {
            update_user_meta($user_id, self::META_ACTIVATE_FIELD, $storage['flag']);
        }

        if ($raw_state !== $payload['value']) {
            update_user_meta($user_id, self::META_ACTIVATE_STATE_FIELD, $payload['value']);
        }

        $payload['flag'] = $storage['flag'];

        return $payload;
    }

    public static function get_activation_flag(int $user_id): bool
    {
        $payload = self::get_activation_payload($user_id);

        return $payload['value'] === self::ACTIVATION_ENABLED;
    }

    public static function set_activation_flag(int $user_id, bool $active, string $state = ''): void
    {
        if ($state === '') {
            $state = $active ? self::ACTIVATION_ENABLED : self::ACTIVATION_DISABLED;
        }

        $payload = self::build_activation_payload($state);

        update_user_meta($user_id, self::META_ACTIVATE_FIELD, $payload['flag']);
        update_user_meta($user_id, self::META_ACTIVATE_STATE_FIELD, $payload['value']);
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function build_payment_payload(string $method): array
    {
        $value = strtolower(trim($method));
        if ($value === 'domiciliacion' || $value === 'domiciliación') {
            return [
                'value' => 'domiciliacion',
                'label' => __('Domiciliación bancaria', 'garantias-online-360vo'),
            ];
        }

        return [
            'value' => 'transferencia',
            'label' => __('Transferencia bancaria', 'garantias-online-360vo'),
        ];
    }

    public static function set_payment_method(int $user_id, string $method): void
    {
        $payload = self::build_payment_payload($method);
        update_user_meta($user_id, self::META_PAYMENT_FIELD, $payload);
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function get_payment_method(int $user_id): array
    {
        $stored = get_user_meta($user_id, self::META_PAYMENT_FIELD, true);
        if (is_array($stored) && isset($stored['value'])) {
            $value = (string) $stored['value'];
            $label = isset($stored['label']) ? (string) $stored['label'] : '';

            if ($label === '') {
                return self::build_payment_payload($value);
            }

            return [
                'value' => strtolower(trim($value)),
                'label' => $label,
            ];
        }

        if (is_scalar($stored) && (string) $stored !== '') {
            return self::build_payment_payload((string) $stored);
        }

        return self::build_payment_payload('transferencia');
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
        $stored = self::store_document($user_id, self::TYPE_PENDING, $binary, $context);

        if (! is_wp_error($stored)) {
            self::set_status($user_id, self::STATUS_PENDING_SIGNATURE);
            self::set_activation_flag($user_id, false, self::ACTIVATION_PENDING);
            self::set_payment_method($user_id, 'transferencia');
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string|bool>|WP_Error
     */
    public static function store_signed_mandate(int $user_id, string $binary, array $context = [])
    {
        $stored = self::store_document($user_id, self::TYPE_SIGNED, $binary, $context);

        if (! is_wp_error($stored)) {
            self::set_status($user_id, self::STATUS_PENDING_VALIDATION);
            self::set_activation_flag($user_id, false, self::ACTIVATION_PENDING);
            self::set_payment_method($user_id, 'transferencia');
        }

        return $stored;
    }

    public static function clear_pending_mandate(int $user_id): void
    {
        self::clear_document($user_id, self::TYPE_PENDING);
        $status = self::get_status($user_id);
        if ($status['value'] === self::STATUS_PENDING_SIGNATURE) {
            $signed = self::get_document_meta($user_id, self::TYPE_SIGNED);
            if (empty($signed['hash'])) {
                self::set_status($user_id, self::STATUS_UNFILLED);
            }
        }
        self::set_payment_method($user_id, 'transferencia');
        self::set_activation_flag($user_id, false, self::ACTIVATION_DISABLED);
    }

    public static function clear_signed_mandate(int $user_id): void
    {
        self::clear_document($user_id, self::TYPE_SIGNED);
        self::set_activation_flag($user_id, false, self::ACTIVATION_DISABLED);

        $pending = self::get_document_meta($user_id, self::TYPE_PENDING);
        if (! empty($pending['hash'])) {
            self::set_status($user_id, self::STATUS_PENDING_SIGNATURE);
        } else {
            self::set_status($user_id, self::STATUS_UNFILLED);
        }
        self::set_payment_method($user_id, 'transferencia');
    }

    public static function set_disabled_message(int $user_id, string $message): void
    {
        $sanitized = sanitize_textarea_field($message);
        update_user_meta($user_id, self::META_DISABLED_MESSAGE_FIELD, $sanitized);
    }

    public static function get_disabled_message(int $user_id): string
    {
        $value = get_user_meta($user_id, self::META_DISABLED_MESSAGE_FIELD, true);
        if (is_string($value)) {
            return trim($value);
        }

        return '';
    }

    public static function clear_disabled_message(int $user_id): void
    {
        delete_user_meta($user_id, self::META_DISABLED_MESSAGE_FIELD);
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
