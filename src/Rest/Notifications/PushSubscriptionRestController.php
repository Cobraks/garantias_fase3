<?php

namespace GarantiasOnline360VO\Rest\Notifications;

use GarantiasOnline360VO\Notifications\Push\PushSubscriptionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class PushSubscriptionRestController
{
    private const NAMESPACE = 'go/v1';
    private const REST_BASE = '/push-subscriptions';

    /** @var PushSubscriptionRepository */
    private $repository;

    public function __construct()
    {
        $this->repository = new PushSubscriptionRepository();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE,
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'create_subscription'],
                    'permission_callback' => [$this, 'check_permissions'],
                ],
                [
                    'methods'             => 'DELETE',
                    'callback'            => [$this, 'delete_subscription'],
                    'permission_callback' => [$this, 'check_permissions'],
                ],
            ]
        );
    }

    public function check_permissions(): bool
    {
        return current_user_can('manage_options');
    }

    public function create_subscription(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para registrar notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        if (! $this->user_has_opt_in($user_id)) {
            return new WP_Error('notifications_disabled', __('Las notificaciones están desactivadas en tu perfil.', 'garantias-online-360vo'), ['status' => 403]);
        }

        $payload = $this->sanitize_payload($request->get_json_params());
        if (empty($payload['endpoint']) || empty($payload['publicKey']) || empty($payload['authToken'])) {
            return new WP_Error('invalid_payload', __('Suscripción no válida.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $stored = $this->repository->upsert($user_id, $payload);

        if (! $stored) {
            return new WP_Error('subscription_error', __('No se pudo registrar la suscripción.', 'garantias-online-360vo'), ['status' => 500]);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function delete_subscription(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para eliminar notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $endpoint = (string) $request->get_param('endpoint');
        if ($endpoint === '') {
            return new WP_Error('invalid_payload', __('Debes indicar el endpoint a eliminar.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $this->repository->remove_by_endpoint($endpoint);

        return new WP_REST_Response(['success' => true]);
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

        $system_group = $settings['notificaciones_del_sistema'] ?? [];
        if (! is_array($system_group)) {
            return false;
        }

        return ! empty($system_group['activar_notificaciones_del_sistema']);
    }

    /**
     * @param mixed $params
     * @return array<string, mixed>
     */
    private function sanitize_payload($params): array
    {
        if (! is_array($params)) {
            return [];
        }

        $endpoint = isset($params['endpoint']) ? esc_url_raw((string) $params['endpoint']) : '';
        $keys = isset($params['keys']) && is_array($params['keys']) ? $params['keys'] : [];
        $content_encoding = isset($params['contentEncoding'])
            ? sanitize_key((string) $params['contentEncoding'])
            : (isset($params['content_encoding']) ? sanitize_key((string) $params['content_encoding']) : 'aes128gcm');

        return [
            'endpoint'         => $endpoint,
            'publicKey'        => $this->sanitize_base64_key($keys['p256dh'] ?? $params['publicKey'] ?? ''),
            'authToken'        => $this->sanitize_base64_key($keys['auth'] ?? $params['authToken'] ?? ''),
            'contentEncoding'  => $content_encoding !== '' ? $content_encoding : 'aes128gcm',
            'userAgent'        => isset($params['userAgent']) ? sanitize_text_field((string) $params['userAgent']) : '',
        ];
    }

    private function sanitize_base64_key($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $normalized = preg_replace('/\s+/', '', $value);
        if (! is_string($normalized)) {
            return '';
        }

        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        if (! preg_match('/^[A-Za-z0-9\-_=+/]+$/', $normalized)) {
            return '';
        }

        $converted = strtr($normalized, '-_', '+/');
        $padding = strlen($converted) % 4;
        if ($padding) {
            $converted .= str_repeat('=', 4 - $padding);
        }

        if (base64_decode($converted, true) === false) {
            return '';
        }

        return $normalized;
    }
}
