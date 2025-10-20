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

        $payload = [
            'title' => __('Notificación de prueba', 'garantias-online-360vo'),
            'body'  => __('Todo funciona correctamente. Recibirás avisos en cuanto haya novedades importantes.', 'garantias-online-360vo'),
            'icon'  => esc_url(plugins_url('assets/img/notifications/user-verified.svg', GARANTIAS360VO__FILE__)),
        ];

        $notification_id = $this->repository->create($user_id, $payload);
        if (! $notification_id) {
            return new WP_Error('notification_error', __('No se pudo registrar la notificación de prueba.', 'garantias-online-360vo'), ['status' => 500]);
        }

        $this->dispatcher->dispatch($user_id, $payload);

        return new WP_REST_Response([
            'success' => true,
            'meta'    => [
                'unread' => $this->repository->count_unread($user_id),
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
}
