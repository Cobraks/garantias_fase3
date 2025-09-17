<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\GuaranteeLogger;

if (! defined('ABSPATH')) {
    exit;
}

class EmailNotificationService
{
    /** @var Mailer */
    private $mailer;

    /** @var GuaranteeEmailDataFactory */
    private $data_factory;

    /** @var GuaranteeEmailBuilder */
    private $builder;

    public static function init(): void
    {
        $service = new self(
            new Mailer(),
            new GuaranteeEmailDataFactory(),
            new GuaranteeEmailBuilder(new TemplateRenderer())
        );
        $service->register_hooks();
    }

    public function __construct(Mailer $mailer, GuaranteeEmailDataFactory $data_factory, GuaranteeEmailBuilder $builder)
    {
        $this->mailer = $mailer;
        $this->data_factory = $data_factory;
        $this->builder = $builder;
    }

    private function register_hooks(): void
    {
        add_action('go360/guarantee/created', [$this, 'handle_created'], 10, 2);
        add_action('go360/guarantee/contracted', [$this, 'handle_contracted'], 10, 2);
    }

    public function handle_created(int $guarantee_id, array $context = []): void
    {
        if ($this->has_been_notified($guarantee_id, 'created_admin')) {
            return;
        }

        $data = $this->data_factory->build($guarantee_id);
        if (empty($data)) {
            return;
        }

        if (! $this->should_notify('created_admin', $data, $context)) {
            return;
        }

        $recipients = $this->get_admin_recipients($guarantee_id, $context);
        $initiator_id = $this->resolve_initiator_id($context);
        $message = $this->builder->composeCreatedAdmin(
            $data,
            $recipients,
            $this->build_template_context('created_admin', $context, $initiator_id)
        );

        $this->dispatch($message, $guarantee_id, 'created_admin', $initiator_id);
    }

    public function handle_contracted(int $guarantee_id, array $context = []): void
    {
        $data = $this->data_factory->build($guarantee_id);
        if (empty($data)) {
            return;
        }

        $initiator_id = $this->resolve_initiator_id($context);

        if (! $this->has_been_notified($guarantee_id, 'contracted_admin') && $this->should_notify('contracted_admin', $data, $context)) {
            $admin_recipients = $this->get_admin_recipients($guarantee_id, $context);
            $admin_message = $this->builder->composeContractedAdmin(
                $data,
                $admin_recipients,
                $this->build_template_context('contracted_admin', $context, $initiator_id)
            );
            $this->dispatch($admin_message, $guarantee_id, 'contracted_admin', $initiator_id);
        }

        if (! $this->has_been_notified($guarantee_id, 'contracted_professional') && $this->should_notify('contracted_professional', $data, $context)) {
            $vendor_recipients = $this->get_professional_recipients($data, $context);
            $vendor_message = $this->builder->composeContractedProfessional(
                $data,
                $vendor_recipients,
                $this->build_template_context('contracted_professional', $context, $initiator_id)
            );
            $this->dispatch($vendor_message, $guarantee_id, 'contracted_professional', $initiator_id);
        }
    }

    private function dispatch(?EmailMessage $message, int $guarantee_id, string $event_slug, int $initiator_id): void
    {
        if (! $message instanceof EmailMessage) {
            return;
        }

        $sent = $this->mailer->send($message);
        $details = sprintf(
            '%s|%s',
            $event_slug,
            implode(',', $message->get_recipients())
        );

        GuaranteeLogger::log(
            $initiator_id,
            $guarantee_id,
            $sent ? 'email_sent' : 'email_failed',
            $details
        );

        if ($sent) {
            $this->mark_notified($guarantee_id, $event_slug);
        }
    }

    private function get_admin_recipients(int $guarantee_id, array $context = []): array
    {
        $recipients = [];
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $recipients[] = $admin_email;
        }

        $recipients = apply_filters('go360/email/admin_recipients', $recipients, $guarantee_id, $context);
        return $this->normalize_recipients($recipients);
    }

    private function get_professional_recipients(array $data, array $context = []): array
    {
        $email = $data['vendor']['email'] ?? '';
        $recipients = $email ? [$email] : [];
        $recipients = apply_filters('go360/email/professional_recipients', $recipients, $data, $context);
        return $this->normalize_recipients($recipients);
    }

    private function normalize_recipients($recipients): array
    {
        if (! is_array($recipients)) {
            $recipients = $recipients ? [$recipients] : [];
        }

        $normalized = [];
        foreach ($recipients as $recipient) {
            $sanitized = sanitize_email($recipient);
            if ($sanitized !== '') {
                $normalized[$sanitized] = $sanitized;
            }
        }

        return array_values($normalized);
    }

    private function has_been_notified(int $guarantee_id, string $slug): bool
    {
        $key = $this->meta_key($slug);
        return (bool) get_post_meta($guarantee_id, $key, true);
    }

    private function mark_notified(int $guarantee_id, string $slug): void
    {
        $key = $this->meta_key($slug);
        update_post_meta($guarantee_id, $key, current_time('mysql'));
    }

    private function meta_key(string $slug): string
    {
        return '_go360_email_notified_' . sanitize_key($slug);
    }

    private function should_notify(string $slug, array $data, array $context): bool
    {
        return (bool) apply_filters('go360/email/should_notify', true, $slug, $data, $context);
    }

    private function resolve_initiator_id(array $context): int
    {
        if (isset($context['initiator'])) {
            return (int) $context['initiator'];
        }

        if (isset($context['initiator_id'])) {
            return (int) $context['initiator_id'];
        }

        return get_current_user_id();
    }

    private function build_template_context(string $event, array $context, int $initiator_id): array
    {
        $user = $initiator_id ? get_user_by('id', $initiator_id) : false;
        $initiator = [
            'id'    => $initiator_id,
            'name'  => $user ? $user->display_name : '',
            'email' => $user ? $user->user_email : '',
        ];

        return array_merge(
            $context,
            [
                'event'     => $event,
                'initiator' => $initiator,
            ]
        );
    }
}
