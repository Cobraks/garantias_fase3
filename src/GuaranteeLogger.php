<?php

namespace GarantiasOnline360VO;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use GarantiasOnline360VO\Support\UserProfileResolver;
use function __;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeLogger
{
    const TABLE = 'guarantee_logs';

    public static function ensure_table(): void
    {
        ActivityLogger::ensure_table();
    }

    public static function create_table(): void
    {
        ActivityLogger::create_table();
    }

    public static function log(int $user_id, int $guarantee_id, string $event_type, string $details = ''): void
    {
        if ($event_type === 'updated') {
            return;
        }

        if ($event_type === 'status_changed') {
            $transition = self::parse_status_transition($details);
            $new_status = $transition['new_status'] ?? '';
            if (($transition['old_status'] ?? '') === 'new' && $new_status === 'draft') {
                return;
            }
            if (
                $new_status !== ''
                && in_array(
                    $new_status,
                    [
                        'pendiente_pago',
                        'validacion_pendiente',
                        'pendiente_cobro',
                        'pending_payment',
                        'pending_cobro',
                        'publish',
                        'publicada',
                        'activada',
                        'activated',
                    ],
                    true
                )
            ) {
                return;
            }
        }

        if ($event_type === 'document_downloaded') {
            return;
        }

        if (in_array($event_type, ['document_uploaded', 'document_generated'], true)) {
            $document_type = sanitize_key($details);
            if (in_array($document_type, ['condicionado', 'cobertura'], true)) {
                return;
            }
        }

        $mapped  = self::map_event_type($event_type);
        $vendor  = self::get_vendor_context($guarantee_id);
        $actor   = self::get_user_details($user_id);
        $context = self::build_context($event_type, $details, $guarantee_id, $vendor, $actor);

        if ($vendor['channel'] !== '') {
            $context['channel'] = $vendor['channel'];
            $context['channel_label'] = $vendor['channel_label'];
        }
        if ($vendor['vendor_id']) {
            $context['vendor_id'] = $vendor['vendor_id'];
            $context['vendor_name'] = $vendor['vendor_name'];
            if (! empty($vendor['vendor_person_name'])) {
                $context['vendor_person_name'] = $vendor['vendor_person_name'];
            }
        }

        $data = [
            'actor_id'     => $user_id,
            'guarantee_id' => $guarantee_id,
            'target_type'  => 'guarantee',
            'target_id'    => $guarantee_id,
            'context'      => $context,
        ];

        if ($vendor['channel'] !== '') {
            $data['channel'] = $vendor['channel'];
        }
        if ($vendor['vendor_id']) {
            $data['vendor_id'] = $vendor['vendor_id'];
        }
        if ($vendor['vendor_name'] !== '') {
            $data['vendor_name'] = $vendor['vendor_name'];
        }
        if (! empty($vendor['vendor_person_name'])) {
            $data['vendor_person_name'] = $vendor['vendor_person_name'];
        }

        ActivityLogger::log($mapped, $data);
    }

    public static function get_logs(array $args = []): array
    {
        $result = ActivityLogger::query([
            'actor_id'     => isset($args['user_id']) ? (int) $args['user_id'] : 0,
            'guarantee_id' => isset($args['guarantee_id']) ? (int) $args['guarantee_id'] : 0,
            'per_page'     => isset($args['per_page']) ? (int) $args['per_page'] : 20,
            'page'         => isset($args['page']) ? (int) $args['page'] : 1,
            'category'     => 'guarantee',
        ]);

        return $result['data'] ?? [];
    }

    private static function map_event_type(string $legacy): string
    {
        $legacy = sanitize_key(str_replace(' ', '_', $legacy));
        $map = [
            'created'             => 'guarantee.created',
            'updated'             => 'guarantee.updated',
            'status_changed'      => 'guarantee.status_changed',
            'document_downloaded' => 'document.downloaded',
            'document_uploaded'   => 'document.uploaded',
            'document_generated'  => 'document.generated',
            'email_sent'          => 'email.sent',
            'email_failed'        => 'email.failed',
            'email_skipped'       => 'email.skipped',
            'contracted'          => 'guarantee.contracted',
            'contract_notice_dispatched' => 'guarantee.contracted',
            'payment_recorded'     => 'payment.recorded',
            'transfer_reported'    => 'payment.reported',
        ];

        return $map[$legacy] ?? 'guarantee.updated';
    }
    private static function build_context(
        string $event_type,
        string $details,
        int $guarantee_id,
        array $vendor,
        array $actor
    ): array
    {
        $context = [
            'legacy_event' => $event_type,
            'guarantee_id' => $guarantee_id,
        ];

        $initiator = $vendor['vendor_name'] !== ''
            ? $vendor['vendor_name']
            : ($actor['name'] ?? '');

        if ($initiator === '') {
            $initiator = __('Usuario sin identificar', 'garantias-online-360vo');
        }

        $context['initiator_label'] = $initiator;

        if (! empty($actor['email'])) {
            $context['initiator_email'] = $actor['email'];
        }

        $title = get_the_title($guarantee_id);
        if ($title) {
            $context['guarantee_label'] = $title;
        }

        $vehicle = self::get_vehicle_context($guarantee_id);
        if ($vehicle) {
            $context = array_merge($context, $vehicle);
        }

        switch ($event_type) {
            case 'status_changed':
                $context = array_merge($context, self::parse_status_transition($details));
                break;
            case 'contract_notice_dispatched':
            case 'contracted':
                $context = array_merge($context, self::parse_contract_details($details));
                break;
            case 'email_sent':
            case 'email_failed':
            case 'email_skipped':
                $context = array_merge(
                    $context,
                    self::parse_email_details(
                        $details,
                        $vendor['vendor_name'] ?? '',
                        $context['guarantee_label'] ?? ''
                    )
                );
                break;
            case 'document_downloaded':
            case 'document_uploaded':
            case 'document_generated':
                $context = array_merge(
                    $context,
                    self::parse_document_details($details, $guarantee_id)
                );
                break;
            case 'payment_recorded':
            case 'transfer_reported':
                $context = array_merge(
                    $context,
                    self::parse_payment_details($details, $vendor, $actor)
                );
                break;
            default:
                if ($details !== '') {
                    $context['legacy_details'] = $details;
                }
                break;
        }

        return $context;
    }

    /**
     * @return array{
     *     channel: string,
     *     channel_label: string,
     *     vendor_id: int,
     *     vendor_name: string,
     *     vendor_person_name: string
     * }
     */
    private static function get_vendor_context(int $guarantee_id): array
    {
        $channel_meta = get_post_meta($guarantee_id, 'garantia_contratada_canal_venta', true);
        $channel_value = '';
        $channel_label = '';
        if (is_array($channel_meta)) {
            $channel_value = sanitize_key($channel_meta['value'] ?? '');
            $channel_label = (string) ($channel_meta['label'] ?? '');
        } elseif (is_string($channel_meta) && $channel_meta !== '') {
            $channel_value = sanitize_key($channel_meta);
        }

        if ($channel_label === '' && $channel_value !== '') {
            $channel_label = self::translate_channel($channel_value);
        }

        $vendor_id = 0;
        if ($channel_value === 'profesional') {
            $vendor_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_concesionario_empresa_profesional', true);
        } elseif ($channel_value === 'gestoria') {
            $gestoria = get_post_meta($guarantee_id, 'garantia_contratada_gestoria', true);
            if (is_array($gestoria)) {
                $vendor_id = (int) ($gestoria['ID'] ?? $gestoria['id'] ?? 0);
            } else {
                $vendor_id = (int) $gestoria;
            }
        }

        $vendor_name = '';
        $vendor_person = '';
        if ($vendor_id) {
            $labels = UserProfileResolver::get_vendor_labels($vendor_id);
            $vendor_name = $labels['company_name'] ?? '';
            $vendor_person = $labels['personal_name'] ?? '';
            if ($vendor_name === '' && $vendor_person !== '') {
                $vendor_name = $vendor_person;
            }
        }

        return [
            'channel'       => $channel_value,
            'channel_label' => $channel_label,
            'vendor_id'     => $vendor_id,
            'vendor_name'   => $vendor_name,
            'vendor_person_name' => $vendor_person,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function parse_status_transition(string $details): array
    {
        $details = trim($details);
        if ($details === '') {
            return [];
        }

        if (stripos($details, 'De ') === 0) {
            $parts = explode(' a ', substr($details, 3));
            if (count($parts) === 2) {
                $old = sanitize_key(trim($parts[0]));
                $new = sanitize_key(trim($parts[1]));
                return [
                    'old_status'       => $old,
                    'old_status_label' => self::status_label($old),
                    'new_status'       => $new,
                    'new_status_label' => self::status_label($new),
                ];
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private static function parse_contract_details(string $details): array
    {
        $context = [];
        $decoded = json_decode($details, true);
        if (is_array($decoded)) {
            $previous = sanitize_key((string) ($decoded['previous_state'] ?? ''));
            $current = sanitize_key((string) ($decoded['current_state'] ?? ''));
            if ($previous !== '') {
                $context['previous_state'] = $previous;
                $context['previous_state_label'] = self::status_label($previous);
            }
            if ($current !== '') {
                $context['current_state'] = $current;
                $context['current_state_label'] = self::status_label($current);
            }
            if (! empty($decoded['initiator'])) {
                $context['initiator'] = (int) $decoded['initiator'];
            }
            return $context;
        }

        $status = sanitize_key($details);
        if ($status !== '') {
            $context['current_state'] = $status;
            $context['current_state_label'] = self::status_label($status);
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parse_email_details(string $details, string $vendor_name, string $guarantee_label): array
    {
        $parts = array_filter(array_map('trim', explode('|', $details)));
        $template = sanitize_key(array_shift($parts) ?: '');
        $to = [];
        $bcc = [];
        $reply_to = '';
        $reason = '';

        foreach ($parts as $part) {
            if (strpos($part, ':') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $part, 2));
            $key = sanitize_key($key);
            if ($key === 'to') {
                $to = array_filter(array_map('trim', explode(',', $value)));
            } elseif ($key === 'bcc') {
                $bcc = array_filter(array_map('trim', explode(',', $value)));
            } elseif ($key === 'reply-to' || $key === 'reply_to') {
                $reply_to = $value;
            } elseif ($key === 'reason') {
                $reason = sanitize_key($value);
            }
        }

        $to_string = implode(', ', $to);
        if ($to_string === '') {
            $to_string = '—';
        }

        return [
            'email_template'       => $template,
            'email_primary_label'  => self::email_recipient_label($template, $vendor_name),
            'email_to'             => $to_string,
            'email_to_list'        => $to,
            'email_bcc'            => $bcc,
            'email_reply_to'       => $reply_to,
            'skip_reason_label'    => $reason ? self::skip_reason_label($reason) : '',
            'email_template_label' => self::email_template_label($template, $guarantee_label),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function parse_document_details(string $details, int $guarantee_id): array
    {
        $type = sanitize_key($details);
        if ($type === '') {
            return [];
        }

        $data = [
            'document_type'   => $type,
            'document_label'  => self::document_label($type),
        ];

        if ($guarantee_id && function_exists('rest_url')) {
            $data['document_endpoint'] = rest_url(sprintf('go/v1/guarantees/%d/document/%s', $guarantee_id, $type));
        }

        if ($type === 'certificado') {
            $data['event_label'] = __('Certificado generado', 'garantias-online-360vo');
        }

        return $data;
    }

    private static function status_label(string $status): string
    {
        $status = sanitize_key($status);
        $map = [
            'new'              => __('Nuevo', 'garantias-online-360vo'),
            'draft'            => __('Borrador', 'garantias-online-360vo'),
            'pending'          => __('Pendiente', 'garantias-online-360vo'),
            'pending_payment'  => __('Pendiente de pago', 'garantias-online-360vo'),
            'pending_cobro'    => __('Pendiente de domiciliación', 'garantias-online-360vo'),
            'pendiente_pago'   => __('Pendiente de pago', 'garantias-online-360vo'),
            'validacion_pendiente' => __('Validación pendiente', 'garantias-online-360vo'),
            'pendiente_cobro'  => __('Pendiente de domiciliación', 'garantias-online-360vo'),
            'sin_finalizar'    => __('Sin finalizar', 'garantias-online-360vo'),
            'publish'          => __('Publicada', 'garantias-online-360vo'),
            'active'           => __('Activa', 'garantias-online-360vo'),
            'completed'        => __('Completada', 'garantias-online-360vo'),
            'cancelled'        => __('Cancelada', 'garantias-online-360vo'),
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        $status = str_replace(['_', '-'], ' ', $status);
        return ucfirst($status);
    }

    private static function translate_channel(string $channel): string
    {
        $channel = sanitize_key($channel);
        $map = [
            'profesional' => __('Profesional', 'garantias-online-360vo'),
            'gestoria'    => __('Gestoría', 'garantias-online-360vo'),
            'particular'  => __('Particular', 'garantias-online-360vo'),
            'interno'     => __('Interno', 'garantias-online-360vo'),
        ];

        if (isset($map[$channel])) {
            return $map[$channel];
        }

        $channel = str_replace(['_', '-'], ' ', $channel);
        return ucfirst($channel);
    }

    private static function email_recipient_label(string $template, string $vendor_name): string
    {
        $template = sanitize_key($template);
        $map = [
            'contracted_admin'        => __('la administración', 'garantias-online-360vo'),
            'contracted_professional' => $vendor_name !== '' ? $vendor_name : __('el profesional', 'garantias-online-360vo'),
        ];

        return $map[$template] ?? __('destinatarios', 'garantias-online-360vo');
    }

    private static function email_template_label(string $template, string $guarantee_label): string
    {
        $template = sanitize_key($template);
        $label = trim($guarantee_label);
        $fallback = $label !== '' ? $label : __('la garantía', 'garantias-online-360vo');

        $map = [
            'contracted_admin'        => __('Contratación %s', 'garantias-online-360vo'),
            'contracted_professional' => __('Contratación %s', 'garantias-online-360vo'),
        ];

        if (isset($map[$template])) {
            return sprintf($map[$template], $fallback);
        }

        return sprintf(__('Aviso para %s', 'garantias-online-360vo'), $fallback);
    }

    /**
     * @return array<string, string>
     */
    private static function get_vehicle_context(int $guarantee_id): array
    {
        if ($guarantee_id <= 0) {
            return [];
        }

        $brand = get_post_meta($guarantee_id, 'datos_vehiculo_marca', true);
        $model = get_post_meta($guarantee_id, 'datos_vehiculo_modelo', true);
        $plate = get_post_meta($guarantee_id, 'datos_vehiculo_matricula', true);

        $brand = is_string($brand) ? sanitize_text_field($brand) : '';
        $model = is_string($model) ? sanitize_text_field($model) : '';
        $plate = is_string($plate) ? sanitize_text_field($plate) : '';

        $vehicle = trim($brand . ' ' . $model);

        $context = [];
        if ($brand !== '') {
            $context['vehicle_brand'] = $brand;
        }
        if ($model !== '') {
            $context['vehicle_model'] = $model;
        }
        if ($vehicle !== '') {
            $context['vehicle_label'] = $vehicle;
        }
        if ($plate !== '') {
            $context['vehicle_plate'] = strtoupper($plate);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $vendor
     * @param array<string, mixed> $actor
     * @return array<string, string>
     */
    private static function parse_payment_details(string $details, array $vendor, array $actor): array
    {
        $context = [];
        $decoded = json_decode($details, true);
        $method = '';
        $state = '';
        $actor_type = '';
        $manual_actor = '';

        if (is_array($decoded)) {
            $method = sanitize_key((string) ($decoded['method'] ?? ''));
            $state = sanitize_key((string) ($decoded['state'] ?? ''));
            $actor_type = sanitize_key((string) ($decoded['actor_type'] ?? ''));
            if (! empty($decoded['actor_label'])) {
                $manual_actor = wp_strip_all_tags((string) $decoded['actor_label']);
            }
        } else {
            $method = sanitize_key($details);
        }

        if ($method !== '') {
            $context['payment_method'] = $method;
            $context['payment_method_label'] = self::payment_method_label($method);
        }

        if ($state !== '') {
            $context['current_state'] = $state;
            $context['current_state_label'] = self::status_label($state);
        }

        $actor_label = $manual_actor;
        if ($actor_label === '') {
            if ($actor_type === 'vendor' && ! empty($vendor['vendor_name'])) {
                $actor_label = (string) $vendor['vendor_name'];
            } elseif ($actor_type === 'platform') {
                $actor_label = '360VO*';
            } elseif ($actor_type === 'actor' && ! empty($actor['name'])) {
                $actor_label = (string) $actor['name'];
            }
        }

        if ($actor_label === '' && ! empty($vendor['vendor_name'])) {
            $actor_label = (string) $vendor['vendor_name'];
        }

        if ($actor_label === '' && ! empty($actor['name'])) {
            $actor_label = (string) $actor['name'];
        }

        if ($actor_label !== '') {
            $context['payment_actor_label'] = $actor_label;
        }

        if (is_array($decoded)) {
            if (! empty($decoded['concept'])) {
                $context['transfer_concept'] = wp_strip_all_tags((string) $decoded['concept']);
            }
            if (! empty($decoded['amount'])) {
                $context['transfer_amount'] = wp_strip_all_tags((string) $decoded['amount']);
            }
            if (! empty($decoded['account'])) {
                $context['transfer_account'] = wp_strip_all_tags((string) $decoded['account']);
            }
        }

        return $context;
    }

    private static function payment_method_label(string $method): string
    {
        $method = sanitize_key($method);
        $map = [
            'transferencia'          => __('Transferencia pagada', 'garantias-online-360vo'),
            'domiciliacion'          => __('Pago de domiciliación bancaria', 'garantias-online-360vo'),
            'domiciliacion_bancaria' => __('Pago de domiciliación bancaria', 'garantias-online-360vo'),
        ];

        if (isset($map[$method])) {
            return $map[$method];
        }

        $method = str_replace(['_', '-'], ' ', $method);
        return ucfirst($method);
    }

    private static function skip_reason_label(string $reason): string
    {
        $reason = sanitize_key($reason);
        $map = [
            'invalid_message' => __('mensaje no válido', 'garantias-online-360vo'),
            'no_recipients'   => __('sin destinatarios', 'garantias-online-360vo'),
            'empty_data'      => __('datos incompletos', 'garantias-online-360vo'),
        ];

        if (isset($map[$reason])) {
            return $map[$reason];
        }

        $reason = str_replace(['_', '-'], ' ', $reason);
        return strtolower($reason);
    }

    private static function document_label(string $type): string
    {
        $type = sanitize_key($type);
        $map = [
            'certificado'  => __('Certificado', 'garantias-online-360vo'),
            'condicionado' => __('Condicionado', 'garantias-online-360vo'),
            'cobertura'    => __('Cobertura', 'garantias-online-360vo'),
        ];

        if (isset($map[$type])) {
            return $map[$type];
        }

        $type = str_replace(['_', '-'], ' ', $type);
        return ucfirst($type);
    }

    /**
     * @return array{name: string, email: string}
     */
    private static function get_user_details(int $user_id): array
    {
        if (! $user_id) {
            return ['name' => '', 'email' => ''];
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return ['name' => '', 'email' => ''];
        }

        $name = UserProfileResolver::get_personal_name($user);

        return [
            'name'  => $name,
            'email' => $user->user_email ?: '',
        ];
    }

}
