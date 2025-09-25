<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\Rest\GuaranteeRestController;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeEmailDataFactory
{
    public function build(int $guarantee_id): array
    {
        if ($guarantee_id <= 0) {
            return [];
        }

        $detail = GuaranteeRestController::collect_detail_snapshot($guarantee_id, true);
        if (! is_array($detail) || empty($detail)) {
            return [];
        }

        $plate = sanitize_text_field($detail['matricula'] ?? '');
        $plan = sanitize_text_field($detail['plan'] ?? '');

        $vehicle_summary = $this->build_vehicle_summary($detail);
        $vehicle = [
            'type'     => sanitize_text_field($detail['tipo'] ?? ''),
            'brand'    => sanitize_text_field($detail['marca'] ?? ''),
            'model'    => sanitize_text_field($detail['modelo'] ?? ''),
            'summary'  => $vehicle_summary,
            'sentence' => $this->build_vehicle_sentence($detail, $vehicle_summary),
        ];

        $price_value = $this->parse_number($detail['precio'] ?? '');
        $price_formatted = $price_value === null
            ? ''
            : $this->format_currency($price_value);

        $payment_slug = sanitize_text_field($detail['metodo_pago'] ?? '');
        $payment_label = $this->resolve_payment_label($payment_slug);

        $from_date_raw = sanitize_text_field($detail['desde'] ?? '');
        $to_date_raw   = sanitize_text_field($detail['hasta'] ?? '');

        $dates = [
            'raw_from' => $from_date_raw,
            'raw_to'   => $to_date_raw,
            'from'     => $this->format_date($from_date_raw),
            'to'       => $this->format_date($to_date_raw),
        ];

        $deadline = $this->compute_payment_deadline($from_date_raw);
        $customer_name = sanitize_text_field($detail['nombre_comprador'] ?? '');
        $customer_email = sanitize_email($detail['email_comprador'] ?? '');
        $vendor_company_name = sanitize_text_field($detail['concesionario'] ?? '');
        $vendor_personal_name = sanitize_text_field($detail['concesionario_personal'] ?? '');
        $vendor_first_name = sanitize_text_field($detail['concesionario_personal_first'] ?? '');
        $vendor_last_name = sanitize_text_field($detail['concesionario_personal_last'] ?? '');
        $vendor_contact_name = $vendor_personal_name !== '' ? $vendor_personal_name : $vendor_company_name;
        $vendor_greeting_name = $vendor_first_name !== '' ? $vendor_first_name : $vendor_contact_name;
        $vendor_name = $vendor_company_name !== '' ? $vendor_company_name : $vendor_contact_name;
        $vendor_id = isset($detail['vendor_id']) ? (int) $detail['vendor_id'] : 0;
        $vendor_email = sanitize_email($detail['email_vendedor'] ?? '');
        $vendor_email_source = sanitize_key($detail['email_vendedor_source'] ?? 'registration');
        $vendor_registration_email = sanitize_email($detail['email_vendedor_registro'] ?? '');
        if ($vendor_email === '' && $vendor_id > 0) {
            $vendor_user = get_user_by('id', $vendor_id);
            if ($vendor_user && $vendor_user->user_email) {
                $vendor_email = sanitize_email($vendor_user->user_email);
                $vendor_email_source = 'registration';
                $vendor_registration_email = $vendor_email;
            }
        }
        error_log('[EMAIL] data_factory vendor ' . wp_json_encode([
            'name'  => $vendor_name,
            'id'    => $vendor_id,
            'email' => $vendor_email,
        ]));
        $vendor_company = [];
        if (! empty($detail['vendor_company']) && is_array($detail['vendor_company'])) {
            $vendor_company = $detail['vendor_company'];
        }
        $vendor_type_label = sanitize_text_field($detail['vendor_company_type_label'] ?? ($vendor_company['type']['label'] ?? ''));
        $vendor_channel_label = sanitize_text_field($detail['canal_venta'] ?? '');
        $vendor_channel_summary = sanitize_text_field($detail['canal_venta_summary'] ?? '');
        if ($vendor_channel_summary === '' && $vendor_channel_label !== '' && $vendor_type_label !== '') {
            $vendor_channel_summary = sprintf('%s (%s)', $vendor_channel_label, $vendor_type_label);
        } elseif ($vendor_channel_summary === '') {
            $vendor_channel_summary = $vendor_channel_label;
        }
        $state_value = sanitize_text_field($detail['estado']['value'] ?? '');
        $state_label = sanitize_text_field($detail['estado']['label'] ?? '');

        $permalink = home_url('/garantias-online/mis-garantias/');
        if ($plate !== '') {
            $permalink = add_query_arg('matricula', rawurlencode($plate), $permalink);
        }

        return [
            'id'          => $guarantee_id,
            'plate'       => $plate,
            'plan'        => $plan,
            'vehicle'     => $vehicle,
            'price'       => $price_formatted,
            'price_raw'   => $price_value,
            'payment'     => $payment_label,
            'payment_slug'=> $payment_slug,
            'payment_deadline' => $deadline,
            'state'       => [
                'value' => $state_value,
                'label' => $state_label,
            ],
            'dates'       => $dates,
            'customer'    => [
                'name'  => $customer_name,
                'email' => $customer_email,
            ],
            'vendor'      => [
                'id'            => $vendor_id,
                'name'          => $vendor_name,
                'company_name'  => $vendor_company_name,
                'personal_name' => $vendor_personal_name,
                'contact_name'  => $vendor_contact_name,
                'greeting_name' => $vendor_greeting_name,
                'first_name'    => $vendor_first_name,
                'last_name'     => $vendor_last_name,
                'email'         => $vendor_email,
                'email_source'  => $vendor_email_source,
                'registration_email' => $vendor_registration_email,
                'channel_label' => $vendor_channel_label,
                'channel_summary' => $vendor_channel_summary,
                'type_label'   => $vendor_type_label,
                'company'       => $vendor_company,
            ],
            'transfer'    => [
                'iban'     => $this->sanitize_transfer_iban($detail['transfer_iban'] ?? ''),
                'concept'  => $this->build_transfer_concept($plate, $guarantee_id),
                'amount'   => $price_formatted,
                'deadline' => $deadline['formatted'],
            ],
            'permalink'   => esc_url_raw($permalink),
        ];
    }

    private function parse_number($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,\.]/', '', $value);
        if ($normalized === '') {
            return null;
        }

        $last_comma = strrpos($normalized, ',');
        $last_dot   = strrpos($normalized, '.');
        $decimal_separator = $last_comma > $last_dot ? ',' : '.';

        if ($decimal_separator === ',') {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function format_currency(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' €';
    }

    private function resolve_payment_label(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $map = [
            'domiciliacion'          => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'domiciliacion_bancaria' => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'domiciliacion-bancaria' => __('Domiciliación bancaria', 'garantias-online-360vo'),
            'transferencia'          => __('Transferencia bancaria', 'garantias-online-360vo'),
            'transferencia_bancaria' => __('Transferencia bancaria', 'garantias-online-360vo'),
        ];

        if (isset($map[$slug])) {
            return $map[$slug];
        }

        return ucfirst(str_replace('_', ' ', $slug));
    }

    private function format_date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $date = date_create($value);
        if (! $date) {
            return '';
        }

        return $date->format('d/m/Y');
    }

    private function compute_payment_deadline(string $from_date): array
    {
        $raw = '';
        $formatted = '';

        if ($from_date !== '') {
            $date = date_create($from_date);
            if ($date) {
                $date->modify('+7 days');
                $raw = $date->format('Y-m-d');
                $formatted = $date->format('d/m/Y');
            }
        }

        return [
            'raw'       => $raw,
            'formatted' => $formatted,
        ];
    }

    private function sanitize_transfer_iban($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function build_vehicle_summary(array $detail): string
    {
        $parts = [];

        $type = sanitize_text_field($detail['tipo'] ?? '');
        if ($type !== '') {
            $parts[] = trim($type);
        }

        $brand = sanitize_text_field($detail['marca'] ?? '');
        if ($brand !== '') {
            $parts[] = trim($brand);
        }

        $model = sanitize_text_field($detail['modelo'] ?? '');
        if ($model !== '') {
            $parts[] = trim($model);
        }

        if (! empty($parts)) {
            return preg_replace('/\s+/', ' ', trim(implode(' ', $parts)));
        }

        $fallback = sanitize_text_field($detail['marca_modelo'] ?? '');

        return $fallback !== '' ? $fallback : '';
    }

    private function build_vehicle_sentence(array $detail, string $summary): string
    {
        if ($summary === '') {
            return '';
        }

        $type = sanitize_text_field($detail['tipo'] ?? '');
        if ($type !== '') {
            $lower = function_exists('mb_strtolower')
                ? mb_strtolower($type, 'UTF-8')
                : strtolower($type);

            $brand = sanitize_text_field($detail['marca'] ?? '');
            $model = sanitize_text_field($detail['modelo'] ?? '');

            $pieces = array_filter([
                $lower,
                $brand,
                $model,
            ], static function ($value) {
                return $value !== '';
            });

            if (! empty($pieces)) {
                $summary = preg_replace('/\s+/', ' ', implode(' ', $pieces));
            }
        }

        return sprintf(
            /* translators: %s: vehicle description */
            __('para un %s', 'garantias-online-360vo'),
            $summary
        );
    }

    private function build_transfer_concept(string $plate, int $guarantee_id): string
    {
        $reference = $plate !== '' ? $plate : ('#' . $guarantee_id);

        return sprintf(
            /* translators: %s: vehicle plate */
            __('Garantía %s', 'garantias-online-360vo'),
            $reference
        );
    }
}
