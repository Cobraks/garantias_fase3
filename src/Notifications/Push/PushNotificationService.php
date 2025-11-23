<?php

namespace GarantiasOnline360VO\Notifications\Push;

use GarantiasOnline360VO\Rest\Notifications\PushNotificationRestController;
use GarantiasOnline360VO\Rest\Notifications\PushSubscriptionRestController;

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

    /**
     * @var array<int, array{event:string,record:array}>
     */
    private $pending = [];

    /** @var bool */
    private $shutdown_registered = false;

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
    }

    public function get_public_key(): string
    {
        if (! current_user_can('manage_options')) {
            return '';
        }

        $keys = $this->vapid->get_keys();
        if (empty($keys['public'])) {
            return '';
        }

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
        $raw_event      = isset($raw['event_type']) ? (string) $raw['event_type'] : $event_type;
        $normalized_key = $this->normalize_event_type($raw_event);

        if (! $this->is_relevant_event($normalized_key)) {
            return;
        }

        $canonical_event = $this->canonical_event_from_normalized($normalized_key) ?: $raw_event;

        $this->pending[] = [
            'event'      => $canonical_event,
            'normalized' => $normalized_key,
            'record'     => $record,
        ];

        if (! $this->shutdown_registered) {
            $this->shutdown_registered = true;
            add_action('shutdown', [$this, 'flush_pending']);
        }
    }

    public function flush_pending(): void
    {
        if (empty($this->pending)) {
            return;
        }

        foreach ($this->pending as $entry) {
            $recipients = $this->get_recipients_for_event($entry['normalized'] ?? $entry['event']);
            if (empty($recipients)) {
                continue;
            }

            $message = $this->message_factory->build_from_activity($entry['event'], $entry['record']);
            if (! is_array($message)) {
                continue;
            }

            foreach ($recipients as $user_id) {
                $payload = $message;
                $payload['user_id'] = $user_id;
                $notification_id = $this->notifications->create($user_id, $payload);

                if ($notification_id && $this->user_allows_push_notifications($user_id)) {
                    $this->dispatcher->dispatch($user_id, $payload);
                }
            }
        }

        $this->pending = [];
        $this->shutdown_registered = false;
    }

    /**
     * @return int[]
     */
    private function get_recipients_for_event(string $event_type): array
    {
        $normalized = $this->normalize_event_type($event_type);
        $administrators = $this->get_users_by_roles(['administrator']);

        if (in_array($normalized, ['auth_login_success', 'auth_logout'], true)) {
            return $administrators;
        }

        $managers = $this->get_users_by_roles(['go_director_comercial', 'go_garantias']);
        $all = array_merge($administrators, $managers);

        if (empty($all)) {
            return [];
        }

        $all = array_map('intval', $all);

        return array_values(array_unique($all));
    }

    /**
     * @param string[] $roles
     * @return int[]
     */
    private function get_users_by_roles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }

        $users = get_users([
            'role__in' => $roles,
            'fields'   => 'ID',
        ]);

        if (empty($users)) {
            return [];
        }

        return array_map('intval', $users);
    }

    private function user_allows_push_notifications(int $user_id): bool
    {
        if (! function_exists('get_field')) {
            return true;
        }

        $settings = get_field('ajustes_de_notificaciones', 'user_' . $user_id);
        if (! is_array($settings) || empty($settings)) {
            return true;
        }

        $group = $settings['notificaciones_del_sistema'] ?? null;
        if (! is_array($group) || ! array_key_exists('activar_notificaciones_del_sistema', $group)) {
            return true;
        }

        return ! empty($group['activar_notificaciones_del_sistema']);
    }

    private function is_relevant_event(string $event_type): bool
    {
        $normalized = $this->normalize_event_type($event_type);

        return in_array($normalized, [
            'auth_login_success',
            'auth_logout',
            'user_verification_verified',
            'guarantee_created',
            'guarantee_contracted',
            'guarantee_cancelled',
            'guarantee_note_added',
            'payment_recorded',
            'payment_reported',
            'sepa_pending_requested',
            'sepa_signed_uploaded',
            'sepa_activated',
            'client_commercials_updated',
        ], true);
    }

    private function normalize_event_type(string $event_type): string
    {
        $normalized = strtolower($event_type);
        $normalized = str_replace(['.', '-', ' '], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized);

        return trim((string) $normalized, '_');
    }

    private function canonical_event_from_normalized(string $normalized): string
    {
        $map = [
            'auth_login_success'       => 'auth.login_success',
            'auth_logout'              => 'auth.logout',
            'user_verification_verified' => 'user.verification_verified',
            'guarantee_created'        => 'guarantee.created',
            'guarantee_contracted'     => 'guarantee.contracted',
            'guarantee_cancelled'      => 'guarantee.cancelled',
            'guarantee_note_added'     => 'guarantee.note_added',
            'payment_recorded'         => 'payment.recorded',
            'payment_reported'         => 'payment.reported',
            'sepa_pending_requested'   => 'sepa.pending_requested',
            'sepa_signed_uploaded'     => 'sepa.signed_uploaded',
            'sepa_activated'           => 'sepa.activated',
            'client_commercials_updated' => 'client.commercials_updated',
        ];

        return $map[$normalized] ?? $normalized;
    }

}
