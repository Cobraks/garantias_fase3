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

    public function composeCreatedAdmin(array $data, array $recipients, array $context = []): ?EmailMessage
    {
        return $this->create_message(
            $recipients,
            sprintf(
                /* translators: %s: vehicle plate */
                __('Nueva garantía creada: %s', 'garantias-online-360vo'),
                $this->resolve_plate_label($data)
            ),
            'guarantee-created-admin',
            $data,
            $context
        );
    }

    public function composeContractedAdmin(array $data, array $recipients, array $context = []): ?EmailMessage
    {
        return $this->create_message(
            $recipients,
            sprintf(
                /* translators: %s: vehicle plate */
                __('Garantía activada: %s', 'garantias-online-360vo'),
                $this->resolve_plate_label($data)
            ),
            'guarantee-contracted-admin',
            $data,
            $context
        );
    }

    public function composeContractedProfessional(array $data, array $recipients, array $context = []): ?EmailMessage
    {
        return $this->create_message(
            $recipients,
            sprintf(
                /* translators: %s: vehicle plate */
                __('Detalles de la garantía activada: %s', 'garantias-online-360vo'),
                $this->resolve_plate_label($data)
            ),
            'guarantee-contracted-professional',
            $data,
            $context
        );
    }

    private function create_message(array $recipients, string $subject, string $template, array $data, array $context = []): ?EmailMessage
    {
        $message = new EmailMessage(
            $recipients,
            $subject,
            $this->renderer->render($template, [
                'guarantee' => $data,
                'context'   => $context,
            ])
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
}
