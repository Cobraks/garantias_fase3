<?php

namespace GarantiasOnline360VO\Rest\Notifications;

use GarantiasOnline360VO\Notifications\Push\PushDispatcher;
use GarantiasOnline360VO\Notifications\Push\PushNotificationRepository;
use GarantiasOnline360VO\Notifications\Push\PushSubscriptionRepository;
use GarantiasOnline360VO\Svg;
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
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'mark_single'],
                    'permission_callback' => [$this, 'check_permissions'],
                ],
                [
                    'methods'             => 'DELETE',
                    'callback'            => [$this, 'delete_single'],
                    'permission_callback' => [$this, 'check_permissions'],
                ],
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

        $query_per_page = min(50, $per_page + 1);
        $items = $this->repository->list($user_id, $page, $query_per_page);
        $has_more = false;
        if (count($items) > $per_page) {
            $has_more = true;
            $items = array_slice($items, 0, $per_page);
        }
        $count = $this->repository->count_unread($user_id);

        return new WP_REST_Response([
            'data' => array_map([$this, 'transform_notification'], $items),
            'meta' => [
                'page'      => $page,
                'per_page'  => $per_page,
                'unread'    => $count,
                'has_more'  => $has_more,
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
            'title'     => __('Notificación de prueba', 'garantias-online-360vo'),
            'body'      => __('Todo funciona correctamente. Recibirás avisos en cuanto haya novedades importantes.', 'garantias-online-360vo'),
            'icon'      => Svg::data_uri('notifications'),
            'icon_slug' => 'notifications',
            'tone'      => 'info',
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

    public function delete_single(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para actualizar las notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $notification_id = (int) $request['id'];
        if ($notification_id <= 0) {
            return new WP_Error('invalid_id', __('Identificador de notificación no válido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $this->repository->delete($notification_id, $user_id);

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
        $icon_slug = isset($item['icon_slug']) ? sanitize_key((string) $item['icon_slug']) : '';

        return [
            'id'         => (int) $item['id'],
            'title'      => (string) ($item['title'] ?? ''),
            'body'       => (string) ($item['body'] ?? ''),
            'icon'       => (string) ($item['icon'] ?? ''),
            'icon_slug'  => $icon_slug,
            'icon_svg'   => $icon_slug !== '' ? Svg::icon($icon_slug, 'notifications-panel__icon-svg') : '',
            'badge'      => (string) ($item['badge'] ?? ''),
            'tone'       => isset($item['tone']) ? sanitize_key((string) $item['tone']) : '',
            'link'       => (string) ($item['link'] ?? ''),
            'meta'       => $this->normalize_meta($item['meta'] ?? []),
            'is_read'    => (int) $item['is_read'] === 1,
            'created_at' => (string) ($item['created_at'] ?? ''),
            'actions'    => is_array($item['actions']) ? $item['actions'] : [],
        ];
    }

    /**
     * @param mixed $meta
     * @return array<int, array{label:string,text:string}>
     */
    private function normalize_meta($meta): array
    {
        if (! is_array($meta)) {
            return [];
        }

        $normalized = [];
        foreach ($meta as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $text = isset($entry['text']) ? trim((string) $entry['text']) : '';
            if ($text === '') {
                continue;
            }
            $label = isset($entry['label']) ? trim((string) $entry['label']) : '';

            $text = wp_strip_all_tags($text);
            $label = $label !== '' ? wp_strip_all_tags($label) : '';

            $normalized[] = [
                'label' => $label,
                'text'  => $text,
            ];
        }

        return $normalized;
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
