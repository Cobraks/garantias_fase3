<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\GuaranteeLogger;
use GarantiasOnline360VO\SettingsPage;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;

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
        error_log('[EMAIL] handle_contracted start ID ' . $guarantee_id . ' context ' . wp_json_encode($context));
        $data = $this->data_factory->build($guarantee_id);
        $initiator_id = $this->resolve_initiator_id($context);

        if (empty($data)) {
            $this->log_skip($guarantee_id, 'contracted_admin', 'empty_data', $initiator_id);
            $this->log_skip($guarantee_id, 'contracted_professional', 'empty_data', $initiator_id);
            return;
        }

        if (
            ! $this->has_been_notified($guarantee_id, 'contracted_admin')
            && $this->should_notify('contracted_admin', $data, $context)
        ) {
            $this->send_admin_notification(
                'contracted_admin',
                static function (GuaranteeEmailBuilder $builder, array $data, array $recipients, array $context, array $options) {
                    return $builder->composeContractedAdmin($data, $recipients, $context, $options);
                },
                $guarantee_id,
                $data,
                $context,
                $initiator_id
            );
        }

        if (
            ! $this->has_been_notified($guarantee_id, 'contracted_professional')
            && $this->should_notify('contracted_professional', $data, $context)
        ) {
            $this->send_professional_notification($guarantee_id, $data, $context, $initiator_id);
        }
    }

    private function dispatch(?EmailMessage $message, int $guarantee_id, string $event_slug, int $initiator_id): bool
    {
        if (! $message instanceof EmailMessage) {
            $this->log_skip($guarantee_id, $event_slug, 'invalid_message', $initiator_id);
            return false;
        }

        error_log('[EMAIL] dispatch ' . $event_slug . ' for ' . $guarantee_id . ' recipients ' . wp_json_encode($message->get_recipients()));
        $sent = $this->mailer->send($message);
        error_log('[EMAIL] dispatch result ' . $event_slug . ' => ' . ($sent ? 'sent' : 'failed'));
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

        do_action('go360/email/dispatched', $guarantee_id, $event_slug, $sent, $message, $initiator_id);

        return $sent;
    }

    private function send_admin_notification(
        string $slug,
        callable $composer,
        int $guarantee_id,
        array $data,
        array $context,
        int $initiator_id
    ): void {
        $delivery = $this->get_admin_delivery($guarantee_id, $context);
        error_log('[EMAIL] admin delivery ' . $guarantee_id . ' => ' . wp_json_encode($delivery));

        if (! $this->has_delivery_recipients($delivery)) {
            $this->log_skip($guarantee_id, $slug, 'no_recipients', $initiator_id);
            return;
        }

        $options   = $this->build_admin_options($delivery);
        $recipients = $delivery['to'];
        $message   = $composer(
            $this->builder,
            $data,
            $recipients,
            $this->build_template_context($slug, $context, $initiator_id),
            $options
        );

        $this->dispatch($message, $guarantee_id, $slug, $initiator_id);
    }

    private function send_professional_notification(int $guarantee_id, array $data, array $context, int $initiator_id): void
    {
        $recipients = $this->get_professional_recipients($data, $context);
        error_log('[EMAIL] professional recipients ' . $guarantee_id . ' => ' . wp_json_encode($recipients));

        if (empty($recipients)) {
            $this->log_skip($guarantee_id, 'contracted_professional', 'no_recipients', $initiator_id);
            return;
        }

        $reply_to = $this->get_reply_to_address();
        $options  = $this->build_professional_options($reply_to);

        $message = $this->builder->composeContractedProfessional(
            $data,
            $recipients,
            $this->build_template_context('contracted_professional', $context, $initiator_id),
            $options
        );

        $this->dispatch($message, $guarantee_id, 'contracted_professional', $initiator_id);
    }

    private function has_delivery_recipients(array $delivery): bool
    {
        return ! empty($delivery['to']) || ! empty($delivery['bcc']);
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

        $delivery = [
            'to'  => $this->normalize_recipients($filtered_to),
            'bcc' => $this->normalize_recipients($filtered_bcc),
        ];
        error_log('[EMAIL] get_admin_delivery normalized ' . $guarantee_id . ' => ' . wp_json_encode($delivery));

        return $delivery;
    }

    private function get_professional_recipients(array $data, array $context = []): array
    {
        $recipients = [];

        $email = sanitize_email($data['vendor']['email'] ?? '');
        if ($email !== '') {
            $recipients[] = $email;
        }

        $vendor_id = $this->resolve_vendor_user_id($data, $context);
        error_log('[EMAIL] resolved vendor id for professional recipients => ' . $vendor_id);
        if ($vendor_id > 0) {
            $resolved = NotificationEmailResolver::resolve($vendor_id);
            error_log('[EMAIL] resolved vendor notification email => ' . $resolved);
            if ($resolved !== '') {
                $recipients[] = $resolved;
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
        $normalized = $this->normalize_recipients($recipients);
        error_log('[EMAIL] normalized professional recipients => ' . wp_json_encode($normalized));

        return $normalized;
    }

    private function resolve_vendor_user_id(array $data, array $context = []): int
    {
        if (! empty($data['vendor']['id'])) {
            return (int) $data['vendor']['id'];
        }

        if (! empty($context['vendor_id'])) {
            return (int) $context['vendor_id'];
        }

        $guarantee_id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($guarantee_id > 0) {
            $meta = get_post_meta($guarantee_id, 'garantia_contratada_concesionario_empresa_profesional', true);
            if (is_array($meta) && isset($meta['ID'])) {
                $meta = $meta['ID'];
            } elseif (is_array($meta) && isset($meta['id'])) {
                $meta = $meta['id'];
            }

            if (is_numeric($meta)) {
                return (int) $meta;
            }
        }

        return 0;
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

        $normalized = sanitize_email($reply_to);
        error_log('[EMAIL] resolved reply-to => ' . $normalized);

        return $normalized;
    }

    private function get_admin_from_header(): string
    {
        $email = $this->resolve_sender_email('admin');
        if ($email === '') {
            return '';
        }

        return $this->build_from_header(__('Garantías Online', 'garantias-online-360vo'), $email);
    }

    private function get_professional_from_header(string $reply_to = ''): string
    {
        $email = $this->resolve_sender_email('professional', $reply_to);
        if ($email === '') {
            return '';
        }

        return $this->build_from_header(__('Garantías 360VO', 'garantias-online-360vo'), $email);
    }

    private function resolve_sender_email(string $context, string $fallback = ''): string
    {
        $email = $fallback !== '' ? $fallback : sanitize_email(get_option('admin_email'));

        $settings = $this->get_notification_settings();

        return sanitize_email(
            apply_filters('go360/email/sender_email', $email, $context, $settings)
        );
    }

    private function build_from_header(string $name, string $email): string
    {
        $name = wp_strip_all_tags($name);
        $email = sanitize_email($email);

        if ($email === '') {
            return '';
        }

        return sprintf('From: %s <%s>', $name !== '' ? $name : 'Garantías Online', $email);
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
            'name'  => $user ? UserProfileResolver::get_personal_name($user) : '',
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

    private function build_admin_options(array $delivery): array
    {
        $options = [];

        if (! empty($delivery['bcc'])) {
            $options['bcc'] = $delivery['bcc'];
        }

        $from_header = $this->get_admin_from_header();
        if ($from_header !== '') {
            $options['headers'][] = $from_header;
        }

        return $options;
    }

    public static function resolve_admin_delivery(int $guarantee_id, array $context = []): array
    {
        $service = new self(
            new Mailer(),
            new GuaranteeEmailDataFactory(),
            new GuaranteeEmailBuilder(new TemplateRenderer())
        );

        return $service->get_admin_delivery($guarantee_id, $context);
    }

    private function build_professional_options(string $reply_to): array
    {
        $options = [];

        if ($reply_to !== '') {
            $options['reply_to'] = $reply_to;
        }

        $from_header = $this->get_professional_from_header($reply_to);
        if ($from_header !== '') {
            $options['headers'][] = $from_header;
        }

        return $options;
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

        $root_settings = get_field('notificaciones', SettingsPage::SUBMENU_SLUG);
        if (! is_array($root_settings)) {
            $root_settings = [];
        }

        $notifications_group = $root_settings['notificaciones_email'] ?? get_field('notificaciones_email', SettingsPage::SUBMENU_SLUG);
        if (! is_array($notifications_group)) {
            $notifications_group = [];
        }

        $content_group = $root_settings['contenido_correos_electronicos'] ?? get_field('contenido_correos_electronicos', SettingsPage::SUBMENU_SLUG);
        if (! is_array($content_group)) {
            $content_group = [];
        }

        if (empty($root_settings['direcciones_correo']) && isset($notifications_group['direcciones_correo'])) {
            $root_settings['direcciones_correo'] = $notifications_group['direcciones_correo'];
        }
        if (empty($root_settings['direccion_respuesta']) && isset($notifications_group['direccion_respuesta'])) {
            $root_settings['direccion_respuesta'] = $notifications_group['direccion_respuesta'];
        }

        if (empty($root_settings['firma']) && isset($content_group['firma'])) {
            $root_settings['firma'] = $content_group['firma'];
        }

        $this->notification_settings = array_merge(
            $root_settings,
            [
                'notificaciones_email' => $notifications_group,
                'contenido_correos_electronicos' => $content_group,
            ]
        );

        error_log('[EMAIL] notification settings loaded ' . wp_json_encode($this->notification_settings));

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
