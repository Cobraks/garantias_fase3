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
        $price = sanitize_text_field($detail['precio'] ?? '');
        $payment = sanitize_text_field($detail['metodo_pago'] ?? '');
        $customer_name = sanitize_text_field($detail['nombre_comprador'] ?? '');
        $customer_email = sanitize_email($detail['email_comprador'] ?? '');
        $vendor_name = sanitize_text_field($detail['concesionario'] ?? '');
        $vendor_email = sanitize_email($detail['email_vendedor'] ?? '');
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
            'price'       => $price,
            'payment'     => $payment,
            'state'       => [
                'value' => $state_value,
                'label' => $state_label,
            ],
            'dates'       => [
                'from' => sanitize_text_field($detail['desde'] ?? ''),
                'to'   => sanitize_text_field($detail['hasta'] ?? ''),
            ],
            'customer'    => [
                'name'  => $customer_name,
                'email' => $customer_email,
            ],
            'vendor'      => [
                'name'  => $vendor_name,
                'email' => $vendor_email,
            ],
            'documents'   => [
                'certificate'  => esc_url_raw($detail['certificate_url'] ?? ''),
                'cobertura'    => esc_url_raw($detail['cobertura_url'] ?? ''),
                'condicionado' => esc_url_raw($detail['condicionado_url'] ?? ''),
            ],
            'permalink'   => esc_url_raw($permalink),
        ];
    }
}
