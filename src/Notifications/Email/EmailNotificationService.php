<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\GuaranteeLogger;
use GarantiasOnline360VO\SettingsPage;

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

    /** @var array|null */
    private $notification_settings;

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
        add_action('go360/guarantee/contracted', [$this, 'handle_contracted'], 10, 2);
    }

    public function handle_contracted(int $guarantee_id, array $context = []): void
    {
        $data = $this->data_factory->build($guarantee_id);
        $initiator_id = $this->resolve_initiator_id($context);

        if (empty($data)) {
            $this->log_skip($guarantee_id, 'contracted_admin', 'empty_data', $initiator_id);
            $this->log_skip($guarantee_id, 'contracted_professional', 'empty_data', $initiator_id);
            return;
        }

        if (! $this->has_been_notified($guarantee_id, 'contracted_admin') && $this->should_notify('contracted_admin', $data, $context)) {
            $delivery = $this->get_admin_delivery($guarantee_id, $context);
            if (empty($delivery['to'])) {
                $this->log_skip($guarantee_id, 'contracted_admin', 'no_recipients', $initiator_id);
            } else {
                $options = [];
                if (! empty($delivery['bcc'])) {
                    $options['bcc'] = $delivery['bcc'];
                }

                $admin_message = $this->builder->composeContractedAdmin(
                    $data,
                    $delivery['to'],
                    $this->build_template_context('contracted_admin', $context, $initiator_id),
                    $options
                );
                $this->dispatch($admin_message, $guarantee_id, 'contracted_admin', $initiator_id);
            }
        }

        if (! $this->has_been_notified($guarantee_id, 'contracted_professional') && $this->should_notify('contracted_professional', $data, $context)) {
            $vendor_recipients = $this->get_professional_recipients($data, $context);
            if (empty($vendor_recipients)) {
                $this->log_skip($guarantee_id, 'contracted_professional', 'no_recipients', $initiator_id);
            } else {
                $reply_to = $this->get_reply_to_address();
                $options = [];
                if ($reply_to !== '') {
                    $options['reply_to'] = $reply_to;
                }

                $vendor_message = $this->builder->composeContractedProfessional(
                    $data,
                    $vendor_recipients,
                    $this->build_template_context('contracted_professional', $context, $initiator_id),
                    $options
                );
                $this->dispatch($vendor_message, $guarantee_id, 'contracted_professional', $initiator_id);
            }
        }
    }

    private function dispatch(?EmailMessage $message, int $guarantee_id, string $event_slug, int $initiator_id): void
    {
        if (! $message instanceof EmailMessage) {
            $this->log_skip($guarantee_id, $event_slug, 'invalid_message', $initiator_id);
            return;
        }

        $sent = $this->mailer->send($message);
        $details = sprintf(
            '%s|to:%s',
            $event_slug,
            implode(',', $message->get_recipients())
        );

        $bcc = $message->get_bcc();
        if (! empty($bcc)) {
            $details .= '|bcc:' . implode(',', $bcc);
        }

        $reply_to = $message->get_reply_to();
        if ($reply_to !== '') {
            $details .= '|reply-to:' . $reply_to;
        }

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

    private function get_admin_delivery(int $guarantee_id, array $context = []): array
    {
        $settings = $this->get_notification_settings();
        $rows = [];

        if (isset($settings['direcciones_correo']) && is_array($settings['direcciones_correo'])) {
            $rows = $settings['direcciones_correo'];
        } elseif (isset($settings['notificaciones_email']['direcciones_correo']) && is_array($settings['notificaciones_email']['direcciones_correo'])) {
            $rows = $settings['notificaciones_email']['direcciones_correo'];
        }

        $to = [];
        $bcc = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $email = sanitize_email($row['admin_recipients'] ?? '');
                if ($email === '') {
                    continue;
                }

                if (! empty($row['copia_oculta'])) {
                    $bcc[$email] = $email;
                } else {
                    $to[$email] = $email;
                }
            }
        }

        if (empty($to) && empty($bcc)) {
            $fallback = sanitize_email(get_option('admin_email'));
            if ($fallback !== '') {
                $to[$fallback] = $fallback;
                error_log('[EMAIL] Fallback admin recipient applied for guarantee ' . $guarantee_id);
            }
        }

        $filtered_to = apply_filters('go360/email/admin_recipients', array_values($to), $guarantee_id, $context);
        $filtered_bcc = apply_filters('go360/email/admin_bcc_recipients', array_values($bcc), $guarantee_id, $context);

        return [
            'to'  => $this->normalize_recipients($filtered_to),
            'bcc' => $this->normalize_recipients($filtered_bcc),
        ];
    }

    private function get_professional_recipients(array $data, array $context = []): array
    {
        $recipients = [];
        $email = $data['vendor']['email'] ?? '';
        if ($email) {
            $recipients[] = $email;
        } elseif (! empty($data['vendor']['id'])) {
            $vendor = get_user_by('id', (int) $data['vendor']['id']);
            if ($vendor && $vendor->user_email) {
                $recipients[] = $vendor->user_email;
            }
        }

        if (empty($recipients)) {
            $initiator_id = $this->resolve_initiator_id($context);
            if ($initiator_id) {
                $initiator = get_user_by('id', $initiator_id);
                if ($initiator && in_array('go_profesional', (array) $initiator->roles, true) && $initiator->user_email) {
                    $recipients[] = $initiator->user_email;
                }
            }
        }

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

    private function get_reply_to_address(): string
    {
        $settings = $this->get_notification_settings();
        $reply_to = '';

        if (isset($settings['direccion_respuesta'])) {
            $reply_to = (string) $settings['direccion_respuesta'];
        }

        if ($reply_to === '' && isset($settings['notificaciones_email']['direccion_respuesta'])) {
            $reply_to = (string) $settings['notificaciones_email']['direccion_respuesta'];
        }

        $reply_to = apply_filters('go360/email/reply_to', $reply_to, $settings);

        return sanitize_email($reply_to);
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
                'email_copy' => $this->get_email_copy(),
            ]
        );
    }

    private function log_skip(int $guarantee_id, string $slug, string $reason, int $initiator_id): void
    {
        GuaranteeLogger::log(
            $initiator_id,
            $guarantee_id,
            'email_skipped',
            sprintf('%s|%s', $slug, $reason)
        );
    }

    private function get_notification_settings(): array
    {
        if ($this->notification_settings !== null) {
            return $this->notification_settings;
        }

        if (! function_exists('get_field')) {
            $this->notification_settings = [];
            return $this->notification_settings;
        }

        $settings = get_field('notificaciones', SettingsPage::SUBMENU_SLUG);
        if (is_array($settings)) {
            $this->notification_settings = $settings;
        } else {
            $this->notification_settings = [];
        }

        return $this->notification_settings;
    }

    private function get_email_copy(): array
    {
        $settings = $this->get_notification_settings();
        $content = $settings['contenido_correos_electronicos'] ?? [];
        if (! is_array($content)) {
            $content = [];
        }

        $admin_group = $content['administracion'] ?? [];
        $client_group = $content['cliente'] ?? [];

        if (! is_array($admin_group)) {
            $admin_group = [];
        }

        if (! is_array($client_group)) {
            $client_group = [];
        }

        return [
            'admin_intro'  => $this->sanitize_copy($admin_group['mensaje_inicial'] ?? ''),
            'client_intro' => $this->sanitize_copy($client_group['mensaje_inicial'] ?? ''),
            'signature'    => $this->sanitize_copy($content['firma'] ?? ''),
        ];
    }

    private function sanitize_copy($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(wp_kses_post($value));
    }
}
