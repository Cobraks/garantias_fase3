<?php

namespace GarantiasOnline360VO\Notifications\Email;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeEmailBuilder
{
    /** @var TemplateRenderer */
    private $renderer;

    public function __construct(TemplateRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function composeContractedAdmin(array $data, array $recipients, array $context = [], array $options = []): ?EmailMessage
    {
        $subject = $this->build_admin_subject($data);

        return $this->create_message(
            $recipients,
            $subject,
            'guarantee-contracted-admin',
            $data,
            $context,
            $options
        );
    }

    public function composeContractedProfessional(array $data, array $recipients, array $context = [], array $options = []): ?EmailMessage
    {
        $subject = $this->build_professional_subject($data);

        return $this->create_message(
            $recipients,
            $subject,
            'guarantee-contracted-professional',
            $data,
            $context,
            $options
        );
    }

    public function composeTransferActivatedProfessional(array $data, array $recipients, array $context = [], array $options = []): ?EmailMessage
    {
        $subject = $this->build_transfer_activation_subject($data);

        return $this->create_message(
            $recipients,
            $subject,
            'transfer-activated-professional',
            $data,
            $context,
            $options
        );
    }

    public function composeCancelledAdmin(array $data, array $recipients, array $context = [], array $options = []): ?EmailMessage
    {
        $plate = $this->resolve_plate_label($data);
        $subject = sprintf(__('Garantía %s cancelada', 'garantias-online-360vo'), $plate);

        return $this->create_message(
            $recipients,
            $subject,
            'guarantee-cancelled-admin',
            $data,
            $context,
            $options
        );
    }

    private function create_message(array $recipients, string $subject, string $template, array $data, array $context = [], array $options = []): ?EmailMessage
    {
        $headers = $options['headers'] ?? [];
        $attachments = $options['attachments'] ?? [];
        $metadata = [
            'cc'       => $options['cc'] ?? [],
            'bcc'      => $options['bcc'] ?? [],
            'reply_to' => $options['reply_to'] ?? '',
        ];

        $message = new EmailMessage(
            $recipients,
            $subject,
            $this->renderer->render($template, [
                'guarantee' => $data,
                'context'   => $context,
            ]),
            $headers,
            $attachments,
            $metadata
        );

        if (! $message->has_recipients() || $message->get_body() === '') {
            return null;
        }

        return $message;
    }

    private function resolve_plate_label(array $data): string
    {
        $plate = $data['plate'] ?? '';
        if ($plate === '') {
            return sprintf('#%d', $data['id'] ?? 0);
        }

        return $plate;
    }

    private function build_admin_subject(array $data): string
    {
        $plate = $this->resolve_plate_label($data);
        $slug  = isset($data['payment_slug']) ? (string) $data['payment_slug'] : '';

        if (in_array($slug, ['domiciliacion', 'domiciliacion_bancaria', 'domiciliacion-bancaria'], true)) {
            return sprintf(
                /* translators: %s: vehicle plate */
                __('Nueva garantía %s pendiente de domiciliación', 'garantias-online-360vo'),
                $plate
            );
        }

        return sprintf(
            /* translators: %s: vehicle plate */
            __('Nueva garantía %s pendiente de pago', 'garantias-online-360vo'),
            $plate
        );
    }

    private function build_professional_subject(array $data): string
    {
        return sprintf(
            /* translators: %s: vehicle plate */
            __('Garantía %s contratada', 'garantias-online-360vo'),
            $this->resolve_plate_label($data)
        );
    }

    private function build_transfer_activation_subject(array $data): string
    {
        return sprintf(
            /* translators: %s: vehicle plate */
            __('Garantía %s activada', 'garantias-online-360vo'),
            $this->resolve_plate_label($data)
        );
    }
}
