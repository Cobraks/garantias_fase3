<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\GuaranteeLogger;
use GarantiasOnline360VO\Docs\PrivateDocsManager;
use GarantiasOnline360VO\Rest\GuaranteeRestController;
use GarantiasOnline360VO\Support\NotificationEmailResolver;
use GarantiasOnline360VO\Support\UserProfileResolver;

if (! defined('ABSPATH')) {
    exit;
}

class EmailNotificationService
{
    private const EVENT_TRANSFER_ACTIVATED_PROFESSIONAL = 'transfer_activated_professional';

    private const TRANSFER_PAYMENT_SLUGS = [
        'transferencia',
        'transferencia_bancaria',
        'transferencia-bancaria',
    ];

    private const TRANSFER_ACTIVATION_PREVIOUS_STATES = [
        'pendiente_pago',
        'validacion_pendiente',
        'sin_finalizar',
    ];


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
        add_action('go360/guarantee/payment_recorded', [$this, 'handle_payment_recorded'], 10, 5);
        add_action('go360/guarantee/cancelled', [$this, 'handle_cancelled'], 10, 3);
    }

    public static function notify_cancelled(int $guarantee_id, array $context = []): void
    {
        $service = new self(
            new Mailer(),
            new GuaranteeEmailDataFactory(),
            new GuaranteeEmailBuilder(new TemplateRenderer())
        );

        $service->handle_cancelled($guarantee_id, $context, (int) ($context['initiator'] ?? 0));
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

        $is_correction_regeneration = ! empty($context['is_correction_regeneration'])
            || sanitize_key((string) ($context['previous_state'] ?? '')) === 'en_revision';
        $suppress_customer_emails = $is_correction_regeneration && ! empty($context['suppress_customer_emails']);
        if ($is_correction_regeneration) {
            $context['is_correction_regeneration'] = true;
            $context['suppress_customer_emails'] = $suppress_customer_emails;
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
            ! $suppress_customer_emails
            &&
            ! $this->has_been_notified($guarantee_id, 'contracted_professional')
            && $this->should_notify('contracted_professional', $data, $context)
        ) {
            $this->send_professional_notification($guarantee_id, $data, $context, $initiator_id);
        }

        if (! $suppress_customer_emails && $this->should_send_transfer_activation($guarantee_id, $data, $context)) {
            $slug = self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL;

            if (
                ! $this->has_been_notified($guarantee_id, $slug)
                && $this->should_notify($slug, $data, $context)
            ) {
                $this->send_transfer_activation_notification($guarantee_id, $data, $context, $initiator_id);
            }
        }
    }

    public function handle_payment_recorded(
        int $guarantee_id,
        string $method,
        string $state,
        string $actor_type = '',
        int $initiator_id = 0
    ): void {
        $method_slug = sanitize_key($method);
        if ($method_slug === '' || ! in_array($method_slug, self::TRANSFER_PAYMENT_SLUGS, true)) {
            return;
        }

        $state_slug = sanitize_key($state);
        if ($state_slug !== 'activada') {
            return;
        }

        $actor_slug = sanitize_key($actor_type);
        if ($actor_slug === 'actor') {
            return;
        }

        if ($this->has_been_notified($guarantee_id, self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL)) {
            return;
        }

        $data = $this->data_factory->build($guarantee_id);
        if (empty($data)) {
            $this->log_skip($guarantee_id, self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL, 'empty_data', $initiator_id);
            return;
        }

        $context = [
            'initiator'      => $initiator_id,
            'current_state'  => $state_slug,
            'payment_method' => $method_slug,
            'actor_type'     => $actor_slug,
        ];

        if (! $this->should_notify(self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL, $data, $context)) {
            return;
        }

        $this->send_transfer_activation_notification($guarantee_id, $data, $context, $initiator_id);
    }

    public function handle_cancelled(int $guarantee_id, array $context = [], int $initiator_id = 0): void
    {
        $data = $this->data_factory->build($guarantee_id);
        $initiator = $this->resolve_initiator_id($context);
        $initiator_id = $initiator ?: $initiator_id;

        if (empty($data)) {
            $this->log_skip($guarantee_id, 'cancelled_admin', 'empty_data', $initiator_id);
            return;
        }

        $reason_label = isset($context['reason']) && $context['reason'] !== ''
            ? sanitize_text_field((string) $context['reason'])
            : ($data['cancellation']['reason_label'] ?? '');
        $context['reason_label'] = $reason_label;

        if (
            ! $this->has_been_notified($guarantee_id, 'cancelled_admin')
            && $this->should_notify('cancelled_admin', $data, $context)
        ) {
            $this->send_admin_notification(
                'cancelled_admin',
                static function (GuaranteeEmailBuilder $builder, array $data, array $recipients, array $context, array $options) {
                    return $builder->composeCancelledAdmin($data, $recipients, $context, $options);
                },
                $guarantee_id,
                $data,
                $context,
                $initiator_id
            );
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
        $options  = $this->build_professional_options($reply_to, $data);

        $message = $this->builder->composeContractedProfessional(
            $data,
            $recipients,
            $this->build_template_context('contracted_professional', $context, $initiator_id),
            $options
        );

        $this->dispatch($message, $guarantee_id, 'contracted_professional', $initiator_id);
    }

    private function send_transfer_activation_notification(int $guarantee_id, array $data, array $context, int $initiator_id): void
    {
        $recipients = $this->get_professional_recipients($data, $context);
        error_log('[EMAIL] transfer activation recipients ' . $guarantee_id . ' => ' . wp_json_encode($recipients));

        if (empty($recipients)) {
            $this->log_skip($guarantee_id, self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL, 'no_recipients', $initiator_id);
            return;
        }

        $reply_to = $this->get_reply_to_address();
        $options  = $this->build_professional_options($reply_to, $data);

        $message = $this->builder->composeTransferActivatedProfessional(
            $data,
            $recipients,
            $this->build_template_context(self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL, $context, $initiator_id),
            $options
        );

        $this->dispatch($message, $guarantee_id, self::EVENT_TRANSFER_ACTIVATED_PROFESSIONAL, $initiator_id);
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
        $this->get_notification_settings();
        $reply_to = EmailSettings::getReplyTo();

        error_log('[EMAIL] resolved reply-to => ' . $reply_to);

        return $reply_to;
    }

    private function should_send_transfer_activation(int $guarantee_id, array $data, array $context): bool
    {
        $previous = isset($context['previous_state']) ? sanitize_key((string) $context['previous_state']) : '';
        $current  = isset($context['current_state']) ? sanitize_key((string) $context['current_state']) : '';

        if (! in_array($previous, self::TRANSFER_ACTIVATION_PREVIOUS_STATES, true) || $current !== 'activada') {
            return false;
        }

        $slug = isset($data['payment_slug']) ? sanitize_key((string) $data['payment_slug']) : '';

        if ($slug === '' && isset($context['payment_method'])) {
            $slug = sanitize_key((string) $context['payment_method']);
        }

        if ($slug === '' && isset($data['payment']) && is_string($data['payment'])) {
            if (stripos($data['payment'], 'transfer') !== false) {
                $slug = 'transferencia';
            }
        }

        if ($slug === '' && $guarantee_id > 0) {
            $stored = get_post_meta($guarantee_id, 'garantia_contratada_metodo_pago', true);
            if (is_array($stored) && isset($stored['value'])) {
                $stored = $stored['value'];
            }
            if (is_string($stored) && $stored !== '') {
                $slug = sanitize_key($stored);
            }
        }

        return $slug !== '' && in_array($slug, self::TRANSFER_PAYMENT_SLUGS, true);
    }

    private function get_admin_from_header(): string
    {
        $email = EmailSettings::resolveSenderEmail('admin');
        if ($email === '') {
            return '';
        }

        return $this->build_from_header(__('Garantías 360VO', 'garantias-online-360vo'), $email);
    }

    private function get_professional_from_header(string $reply_to = ''): string
    {
        $email = EmailSettings::resolveSenderEmail('professional', $reply_to);
        if ($email === '') {
            return '';
        }

        return $this->build_from_header(__('Garantías 360VO', 'garantias-online-360vo'), $email);
    }

    private function build_from_header(string $name, string $email): string
    {
        $name = wp_strip_all_tags($name);
        $email = sanitize_email($email);

        if ($email === '') {
            return '';
        }

        return sprintf('From: %s <%s>', $name !== '' ? $name : 'Garantías 360VO', $email);
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

    private function build_professional_options(string $reply_to, array $data = []): array
    {
        $options = [];

        if ($reply_to !== '') {
            $options['reply_to'] = $reply_to;
        }

        $from_header = $this->get_professional_from_header($reply_to);
        if ($from_header !== '') {
            $options['headers'][] = $from_header;
        }

        $attachments = $this->get_proforma_attachments($data);
        if (! empty($attachments)) {
            $options['attachments'] = $attachments;
        }

        return $options;
    }

    private function get_proforma_attachments(array $data): array
    {
        $settings = GuaranteeRestController::get_proforma_feature_settings();
        $options  = isset($settings['options']) && is_array($settings['options'])
            ? $settings['options']
            : [];

        if (empty($options['enviar_por_correo'])) {
            return [];
        }

        $guarantee_id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($guarantee_id <= 0) {
            return [];
        }

        $hash = get_post_meta($guarantee_id, GuaranteeRestController::PROFORMA_HASH_META, true);
        if (! $hash) {
            error_log('[EMAIL][Proforma] missing hash for guarantee ' . $guarantee_id);
            return [];
        }

        $binary = PrivateDocsManager::retrieve($hash, 'pdf');
        if (! $binary) {
            error_log('[EMAIL][Proforma] unable to retrieve pdf for ' . $guarantee_id);
            return [];
        }

        $filename = $this->build_proforma_filename($data);
        $directory = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        $directory = trailingslashit($directory);

        $target_name = function_exists('wp_unique_filename')
            ? wp_unique_filename($directory, $filename)
            : $filename;

        $temp_path = $directory . $target_name;

        $written = file_put_contents($temp_path, $binary);
        if ($written === false) {
            error_log('[EMAIL][Proforma] failed writing attachment for ' . $guarantee_id);
            return [];
        }

        $this->schedule_temp_file_cleanup($temp_path);

        return [$temp_path];
    }

    private function build_proforma_filename(array $data): string
    {
        $plate = '';

        if (! empty($data['plate']) && is_string($data['plate'])) {
            $plate = strtoupper(str_replace(' ', '', sanitize_text_field($data['plate'])));
        }

        if ($plate === '') {
            $plate = isset($data['id']) ? (string) ((int) $data['id']) : 'proforma';
        }

        return sprintf('factura_proforma_%s.pdf', $plate);
    }

    private function schedule_temp_file_cleanup(string $path): void
    {
        if ($path === '') {
            return;
        }

        register_shutdown_function(static function () use ($path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        });
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
        if ($this->notification_settings === null) {
            $this->notification_settings = EmailSettings::all();
            error_log('[EMAIL] notification settings loaded ' . wp_json_encode($this->notification_settings));
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

        $signature_source = EmailSettings::getSignature();

        if ($signature_source === '' && isset($content['firma']) && is_string($content['firma'])) {
            $signature_source = trim($content['firma']);
        }

        return [
            'admin_intro'  => $this->sanitize_copy($admin_group['mensaje_inicial'] ?? ''),
            'client_intro' => $this->sanitize_copy($client_group['mensaje_inicial'] ?? ''),
            'signature'    => $this->sanitize_copy($signature_source, true),
        ];
    }

    private function sanitize_copy($value, bool $allow_styles = false): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($allow_styles) {
            /**
             * Signatures are stored as fully prepared HTML in the options page.
             * We therefore return the trimmed markup without additional
             * sanitization to preserve inline styles and advanced layout.
             */
            return $value;
        }

        return trim(wp_kses_post($value));
    }
}
