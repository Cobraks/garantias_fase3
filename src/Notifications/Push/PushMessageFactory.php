<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class PushMessageFactory
{
    /**
     * @param array<string, mixed> $activity_record
     * @return array<string, mixed>|null
     */
    public function build_from_activity(string $event_type, array $activity_record): ?array
    {
        switch ($event_type) {
            case 'user.verification_verified':
                return $this->build_user_verified($activity_record);
            case 'guarantee.created':
                return $this->build_guarantee_created($activity_record);
            case 'payment.reported':
                return $this->build_transfer_reported($activity_record);
            case 'sepa.signed_uploaded':
                return $this->build_sepa_uploaded($activity_record);
            default:
                return null;
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_user_verified(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $email = isset($context['user_email']) ? sanitize_text_field((string) $context['user_email']) : '';
        $user_id = isset($record['actor_id']) ? (int) $record['actor_id'] : 0;

        return [
            'title' => __('Nuevo usuario verificado', 'garantias-online-360vo'),
            'body'  => $email !== ''
                ? sprintf(__('El usuario %s ha verificado su cuenta.', 'garantias-online-360vo'), $email)
                : __('Se ha verificado una nueva cuenta de usuario.', 'garantias-online-360vo'),
            'link'  => $user_id > 0 ? admin_url('user-edit.php?user_id=' . $user_id) : admin_url('users.php'),
            'icon'  => plugins_url('assets/img/notifications/user-verified.svg', GARANTIAS360VO__FILE__),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_guarantee_created(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $initiator = isset($context['initiator_label']) ? (string) $context['initiator_label'] : '';
        $company = isset($context['vendor_name']) ? (string) $context['vendor_name'] : '';

        $body_parts = [];
        if ($initiator !== '') {
            $body_parts[] = sprintf(__('Iniciada por %s', 'garantias-online-360vo'), $initiator);
        }
        if ($title !== '') {
            $body_parts[] = $title;
        }
        if ($company !== '') {
            $body_parts[] = sprintf(__('Empresa: %s', 'garantias-online-360vo'), $company);
        }

        $link = $guarantee_id > 0
            ? get_permalink($guarantee_id)
            : admin_url('edit.php?post_type=garantia');

        return [
            'title' => __('Nueva garantía creada', 'garantias-online-360vo'),
            'body'  => ! empty($body_parts) ? implode(' · ', $body_parts) : __('Se ha creado una nueva garantía.', 'garantias-online-360vo'),
            'link'  => $link,
            'icon'  => plugins_url('assets/img/notifications/guarantee-created.svg', GARANTIAS360VO__FILE__),
            'actions' => [
                [
                    'action' => 'view',
                    'title'  => __('Ver garantía', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_transfer_reported(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $amount = isset($context['payment_amount']) ? (string) $context['payment_amount'] : '';
        $link = $guarantee_id > 0
            ? get_permalink($guarantee_id)
            : admin_url('edit.php?post_type=garantia');

        $body = $title !== ''
            ? sprintf(__('Han confirmado la transferencia de la garantía %s.', 'garantias-online-360vo'), $title)
            : __('Han confirmado la transferencia de una garantía.', 'garantias-online-360vo');

        if ($amount !== '') {
            $body .= ' ' . sprintf(__('Importe: %s.', 'garantias-online-360vo'), $amount);
        }

        return [
            'title' => __('Transferencia confirmada', 'garantias-online-360vo'),
            'body'  => $body,
            'link'  => $link,
            'icon'  => plugins_url('assets/img/notifications/transfer-confirmed.svg', GARANTIAS360VO__FILE__),
            'actions' => [
                [
                    'action' => 'open-guarantee',
                    'title'  => __('Revisar garantía', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function build_sepa_uploaded(array $record): array
    {
        $context = $this->decode_context($record['context'] ?? '');
        $guarantee_id = isset($record['guarantee_id']) ? (int) $record['guarantee_id'] : 0;
        $title = isset($context['guarantee_label']) ? (string) $context['guarantee_label'] : '';
        $link = $guarantee_id > 0
            ? get_permalink($guarantee_id)
            : admin_url('edit.php?post_type=garantia');

        return [
            'title' => __('SEPA pendiente de verificación', 'garantias-online-360vo'),
            'body'  => $title !== ''
                ? sprintf(__('Se ha subido el SEPA firmado para %s.', 'garantias-online-360vo'), $title)
                : __('Se ha subido un nuevo SEPA firmado.', 'garantias-online-360vo'),
            'link'  => $link,
            'icon'  => plugins_url('assets/img/notifications/sepa-uploaded.svg', GARANTIAS360VO__FILE__),
            'actions' => [
                [
                    'action' => 'review-sepa',
                    'title'  => __('Revisar documentación', 'garantias-online-360vo'),
                    'url'    => $link,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode_context($context): array
    {
        if (is_array($context)) {
            return $context;
        }

        if (is_string($context) && $context !== '') {
            $decoded = json_decode($context, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
