<?php

namespace GarantiasOnline360VO\Notifications\Push;

use GarantiasOnline360VO\Rest\Notifications\PushNotificationRestController;
use GarantiasOnline360VO\Rest\Notifications\PushSubscriptionRestController;

if (! defined('ABSPATH')) {
    exit;
}

class PushNotificationService
{
    /** @var PushSubscriptionRepository */
    private $subscriptions;

    /** @var PushNotificationRepository */
    private $notifications;

    /** @var PushMessageFactory */
    private $message_factory;

    /** @var VapidKeyManager */
    private $vapid;

    public static function init(): void
    {
        $service = new self(
            new PushSubscriptionRepository(),
            new PushNotificationRepository(),
            new PushMessageFactory(),
            new VapidKeyManager()
        );
        $service->register_hooks();
    }

    public function __construct(
        PushSubscriptionRepository $subscriptions,
        PushNotificationRepository $notifications,
        PushMessageFactory $message_factory,
        VapidKeyManager $vapid
    ) {
        $this->subscriptions = $subscriptions;
        $this->notifications = $notifications;
        $this->message_factory = $message_factory;
        $this->vapid = $vapid;
    }

    private function register_hooks(): void
    {
        add_action('init', [PushTables::class, 'ensure_tables']);
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('go360/activity/logged', [$this, 'handle_activity'], 20, 4);
        add_filter('go360/push/public_key', [$this, 'get_public_key']);
    }

    public function get_public_key(): string
    {
        $keys = $this->vapid->get_keys();
        return $keys['public'];
    }

    public function register_rest(): void
    {
        (new PushSubscriptionRestController())->register_routes();
        (new PushNotificationRestController())->register_routes();
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
                $this->dispatch_push($admin_id, $payload);
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
     * @param int                  $user_id
     * @param array<string, mixed> $payload
     */
    private function dispatch_push(int $user_id, array $payload): void
    {
        $subscriptions = $this->subscriptions->get_user_subscriptions($user_id);
        if (empty($subscriptions)) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $success = $this->send_web_push($subscription, $payload);
            if ($success) {
                $this->subscriptions->mark_success($subscription['endpoint']);
            } else {
                $this->subscriptions->mark_failure($subscription['endpoint']);
            }
        }
    }

    /**
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $payload
     */
    private function send_web_push(array $subscription, array $payload): bool
    {
        $client = new WebPushClient();
        return $client->send($subscription, $payload);
    }
}
