<?php

namespace GarantiasOnline360VO\Notifications\Push;

use GarantiasOnline360VO\Rest\Notifications\PushNotificationRestController;
use GarantiasOnline360VO\Rest\Notifications\PushSubscriptionRestController;
use WP_Error;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class PushNotificationService
{
    /** @var PushNotificationRepository */
    private $notifications;

    /** @var PushMessageFactory */
    private $message_factory;

    /** @var VapidKeyManager */
    private $vapid;

    /** @var PushDispatcher */
    private $dispatcher;

    public static function init(): void
    {
        $service = new self(
            new PushNotificationRepository(),
            new PushMessageFactory(),
            new VapidKeyManager(),
            new PushDispatcher()
        );
        $service->register_hooks();
    }

    public function __construct(
        PushNotificationRepository $notifications,
        PushMessageFactory $message_factory,
        VapidKeyManager $vapid,
        PushDispatcher $dispatcher
    ) {
        $this->notifications = $notifications;
        $this->message_factory = $message_factory;
        $this->vapid = $vapid;
        $this->dispatcher = $dispatcher;
    }

    private function register_hooks(): void
    {
        add_action('init', [PushTables::class, 'ensure_tables']);
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('go360/activity/logged', [$this, 'handle_activity'], 20, 4);
        add_filter('go360/push/public_key', [$this, 'get_public_key']);
        add_action('go360/push/log', [$this, 'log_push_event'], 10, 2);
    }

    public function get_public_key(): string
    {
        if (! current_user_can('manage_options')) {
            do_action('go360/push/log', 'public_key_denied', ['reason' => 'capability']);
            return '';
        }

        $keys = $this->vapid->get_keys();
        if (empty($keys['public'])) {
            do_action('go360/push/log', 'public_key_missing');
            return '';
        }

        return $keys['public'];
    }

    public function register_rest(): void
    {
        (new PushSubscriptionRestController())->register_routes();
        (new PushNotificationRestController())->register_routes();

        register_rest_route(
            'go/v1',
            '/push-public-key',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_public_key_rest'],
                'permission_callback' => [$this, 'check_public_key_permissions'],
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get_public_key_rest()
    {
        $key = $this->get_public_key();
        if ($key === '') {
            do_action('go360/push/log', 'public_key_response_empty');

            return new WP_Error(
                'push_key_unavailable',
                __('No hay una clave pública disponible para notificaciones push.', 'garantias-online-360vo'),
                ['status' => 404]
            );
        }

        do_action('go360/push/log', 'public_key_response_success');

        return new WP_REST_Response([
            'publicKey' => $key,
        ]);
    }

    public function check_public_key_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param int                   $activity_id
     * @param string                $event_type
     * @param array<string, mixed>  $record
     * @param array<string, mixed>  $raw
     */
    public function handle_activity(int $activity_id, string $event_type, array $record, array $raw): void
    {
        if (! $this->is_relevant_event($event_type)) {
            return;
        }

        $message = $this->message_factory->build_from_activity($event_type, $record);
        if (! is_array($message)) {
            return;
        }

        $admins = $this->get_target_admins();
        if (empty($admins)) {
            return;
        }

        foreach ($admins as $admin_id) {
            $payload = $message;
            $payload['user_id'] = $admin_id;
            $notification_id = $this->notifications->create($admin_id, $payload);

            if ($notification_id) {
                $payload['id'] = $notification_id;
                $this->dispatcher->dispatch($admin_id, $payload);
            }
        }
    }

    /**
     * @return int[]
     */
    private function get_target_admins(): array
    {
        $users = get_users([
            'role__in' => ['administrator'],
            'fields'   => 'ID',
        ]);

        if (empty($users)) {
            return [];
        }

        $allowed = [];
        foreach ($users as $user_id) {
            if ($this->user_allows_notifications((int) $user_id)) {
                $allowed[] = (int) $user_id;
            }
        }

        return $allowed;
    }

    private function user_allows_notifications(int $user_id): bool
    {
        if (! function_exists('get_field')) {
            return true;
        }

        $settings = get_field('ajustes_de_notificaciones', 'user_' . $user_id);
        if (! is_array($settings)) {
            return false;
        }

        $group = $settings['notificaciones_del_sistema'] ?? [];
        if (! is_array($group)) {
            return false;
        }

        return ! empty($group['activar_notificaciones_del_sistema']);
    }

    private function is_relevant_event(string $event_type): bool
    {
        return in_array($event_type, [
            'user.verification_verified',
            'guarantee.created',
            'payment.reported',
            'sepa.signed_uploaded',
        ], true);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log_push_event(string $event, array $context = []): void
    {
        if (! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        $message = sprintf('[GO360 Push] %s %s', $event, wp_json_encode($context));
        error_log($message);
    }
}
