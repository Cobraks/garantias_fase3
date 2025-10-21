<?php

namespace GarantiasOnline360VO\Rest\Notifications;

use GarantiasOnline360VO\Notifications\Push\PushDispatcher;
use GarantiasOnline360VO\Notifications\Push\PushNotificationRepository;
use GarantiasOnline360VO\Notifications\Push\PushSubscriptionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class PushNotificationRestController
{
    private const NAMESPACE = 'go/v1';
    private const REST_BASE = '/push-notifications';

    /** @var PushNotificationRepository */
    private $repository;

    /** @var PushDispatcher */
    private $dispatcher;

    public function __construct()
    {
        $this->repository = new PushNotificationRepository();
        $this->dispatcher = new PushDispatcher(new PushSubscriptionRepository());
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE,
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'list_notifications'],
                    'permission_callback' => [$this, 'check_permissions'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'mark_all'],
                    'permission_callback' => [$this, 'check_permissions'],
                    'args'                => [
                        'action' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE . '/test',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'send_test'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE . '/test-all',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'send_test_all'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE . '/(?P<id>\d+)',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'mark_single'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        );
    }

    public function check_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    public function list_notifications(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para consultar las notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $page = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(20, (int) $request->get_param('per_page')));

        $items = $this->repository->list($user_id, $page, $per_page);
        $count = $this->repository->count_unread($user_id);

        return new WP_REST_Response([
            'data' => array_map([$this, 'transform_notification'], $items),
            'meta' => [
                'page'      => $page,
                'per_page'  => $per_page,
                'unread'    => $count,
            ],
        ]);
    }

    public function mark_all(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para actualizar las notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $action = sanitize_key((string) $request->get_param('action'));
        if ($action === 'mark_read') {
            $this->repository->mark_all_read($user_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'unread' => $this->repository->count_unread($user_id),
            ],
        ]);
    }

    public function send_test(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para enviar la notificación de prueba.', 'garantias-online-360vo'), ['status' => 401]);
        }

        if (! $this->user_has_opt_in($user_id)) {
            return new WP_Error('notifications_disabled', __('Activa las notificaciones en tu perfil antes de enviar una prueba.', 'garantias-online-360vo'), ['status' => 403]);
        }

        do_action('go360/push/log', 'test_notification_requested', ['user' => $user_id]);

        $payload = $this->build_test_payload();

        $notification_id = $this->repository->create($user_id, $payload);
        if (! $notification_id) {
            return new WP_Error('notification_error', __('No se pudo registrar la notificación de prueba.', 'garantias-online-360vo'), ['status' => 500]);
        }

        $payload['id'] = $notification_id;

        $result = $this->dispatcher->dispatch($user_id, $payload);
        $attempts = $result['sent'] + $result['failed'];

        if ($attempts === 0) {
            do_action('go360/push/log', 'test_notification_failed', [
                'user'     => $user_id,
                'failures' => ['no_subscriptions'],
            ]);

            return new WP_Error(
                'no_subscriptions',
                __('No hay dispositivos inscritos para recibir la notificación de prueba.', 'garantias-online-360vo'),
                ['status' => 404]
            );
        }

        if ($result['sent'] === 0) {
            do_action('go360/push/log', 'test_notification_failed', [
                'user'     => $user_id,
                'failures' => $result['failures'],
            ]);

            return new WP_Error(
                'push_delivery_failed',
                __('No se pudo entregar la notificación de prueba a ningún dispositivo.', 'garantias-online-360vo'),
                ['status' => 502]
            );
        }

        if ($result['failed'] > 0) {
            do_action('go360/push/log', 'test_notification_partial', [
                'user'     => $user_id,
                'sent'     => $result['sent'],
                'failed'   => $result['failed'],
                'failures' => $result['failures'],
            ]);
        } else {
            do_action('go360/push/log', 'test_notification_sent', [
                'user' => $user_id,
                'sent' => $result['sent'],
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'sent'     => $result['sent'],
                'failed'   => $result['failed'],
                'failures' => $result['failures'],
                'unread'   => $this->repository->count_unread($user_id),
            ],
        ]);
    }

    public function send_test_all(WP_REST_Request $request)
    {
        $current_user_id = get_current_user_id();
        if ($current_user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para enviar la notificación de prueba.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $targets = $this->dispatcher->get_admin_user_ids_with_subscriptions();
        if (empty($targets)) {
            return new WP_Error('no_recipients', __('No hay administradores con notificaciones activadas.', 'garantias-online-360vo'), ['status' => 404]);
        }

        do_action('go360/push/log', 'broadcast_test_requested', ['targets' => $targets]);

        $payload = $this->build_test_payload();
        $summary = [
            'attempted' => 0,
            'sent'      => 0,
            'failed'    => 0,
            'failures'  => [],
        ];

        foreach ($targets as $user_id) {
            if (! $this->user_has_opt_in($user_id)) {
                continue;
            }

            $notification_id = $this->repository->create($user_id, $payload);
            if (! $notification_id) {
                $summary['failures'][] = [
                    'user'   => (int) $user_id,
                    'reason' => 'storage',
                ];
                continue;
            }

            $summary['attempted']++;

            $payload_with_id = $payload;
            $payload_with_id['id'] = $notification_id;
            $result = $this->dispatcher->dispatch($user_id, $payload_with_id);
            $attempts = $result['sent'] + $result['failed'];

            if ($attempts === 0) {
                $summary['failures'][] = [
                    'user'   => (int) $user_id,
                    'reason' => 'no_subscriptions',
                ];
                continue;
            }

            $summary['sent'] += $result['sent'];
            $summary['failed'] += $result['failed'];

            if (! empty($result['failures'])) {
                $summary['failures'][] = [
                    'user'      => (int) $user_id,
                    'endpoints' => $result['failures'],
                ];
            }
        }

        if ($summary['sent'] === 0) {
            do_action('go360/push/log', 'broadcast_test_failed', $summary);

            return new WP_Error('push_delivery_failed', __('No se pudo entregar la notificación global de prueba.', 'garantias-online-360vo'), ['status' => 502]);
        }

        do_action('go360/push/log', 'broadcast_test_sent', $summary);

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'attempted' => $summary['attempted'],
                'sent'      => $summary['sent'],
                'failed'    => $summary['failed'],
                'failures'  => $summary['failures'],
                'unread'    => $this->repository->count_unread($current_user_id),
            ],
        ]);
    }

    public function mark_single(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para actualizar las notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $notification_id = (int) $request['id'];
        if ($notification_id <= 0) {
            return new WP_Error('invalid_id', __('Identificador de notificación no válido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $this->repository->mark_read($notification_id, $user_id);

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'unread' => $this->repository->count_unread($user_id),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function transform_notification(array $item): array
    {
        return [
            'id'         => (int) $item['id'],
            'title'      => (string) $item['title'],
            'body'       => (string) ($item['body'] ?? ''),
            'icon'       => (string) ($item['icon'] ?? ''),
            'badge'      => (string) ($item['badge'] ?? ''),
            'link'       => (string) ($item['link'] ?? ''),
            'is_read'    => (int) $item['is_read'] === 1,
            'created_at' => (string) $item['created_at'],
            'actions'    => is_array($item['actions']) ? $item['actions'] : [],
        ];
    }

    private function user_has_opt_in(int $user_id): bool
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

    /**
     * @return array<string, string>
     */
    private function build_test_payload(): array
    {
        $icon = esc_url(plugins_url('assets/img/notifications/user-verified.svg', GARANTIAS360VO__FILE__));
        $badge_path = plugin_dir_path(GARANTIAS360VO__FILE__) . 'assets/img/notifications/badge.png';
        $badge = file_exists($badge_path)
            ? esc_url(plugins_url('assets/img/notifications/badge.png', GARANTIAS360VO__FILE__))
            : $icon;

        return [
            'title'   => __('Notificación de prueba', 'garantias-online-360vo'),
            'body'    => __('Todo funciona correctamente. Recibirás avisos en cuanto haya novedades importantes.', 'garantias-online-360vo'),
            'icon'    => $icon,
            'badge'   => $badge,
            'link'    => admin_url(),
            'actions' => [
                [
                    'action' => 'open-dashboard',
                    'title'  => __('Abrir panel', 'garantias-online-360vo'),
                    'url'    => admin_url(),
                ],
            ],
        ];
    }
}
