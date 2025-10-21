<?php

namespace GarantiasOnline360VO\Rest\Notifications;

use GarantiasOnline360VO\Notifications\Push\Base64KeyNormalizer;
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

        register_rest_route(
            self::NAMESPACE,
            self::REST_BASE . '/status',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'get_subscription_status'],
                'permission_callback' => [$this, 'check_permissions'],
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

        $raw_params = $request->get_json_params();
        $payload = $this->sanitize_payload($raw_params);
        if (empty($payload['endpoint']) || empty($payload['publicKey']) || empty($payload['authToken'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $raw_keys = is_array($raw_params['keys'] ?? null) ? $raw_params['keys'] : [];
                error_log('[GO360 Push] invalid subscription payload for user ' . $user_id . ': ' . wp_json_encode([
                    'endpointEmpty'   => empty($payload['endpoint']),
                    'publicKeyEmpty'  => empty($payload['publicKey']),
                    'authTokenEmpty'  => empty($payload['authToken']),
                    'rawEndpointLen'  => isset($raw_params['endpoint']) ? strlen((string) $raw_params['endpoint']) : null,
                    'rawPublicKeyLen' => isset($raw_keys['p256dh']) ? strlen((string) $raw_keys['p256dh']) : (isset($raw_params['publicKey']) ? strlen((string) $raw_params['publicKey']) : null),
                    'rawAuthLen'      => isset($raw_keys['auth']) ? strlen((string) $raw_keys['auth']) : (isset($raw_params['authToken']) ? strlen((string) $raw_params['authToken']) : null),
                ]));
            }
            return new WP_Error('invalid_payload', __('Suscripción no válida.', 'garantias-online-360vo'), ['status' => 400]);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push] creating subscription for user ' . $user_id . ' endpoint ' . substr($payload['endpoint'], 0, 80));
        }

        do_action('go360/push/log', 'subscription_upsert', [
            'user'     => $user_id,
            'endpoint' => substr($payload['endpoint'], 0, 80),
        ]);

        $stored = $this->repository->upsert($user_id, $payload);

        if (! $stored) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GO360 Push] subscription upsert failed for user ' . $user_id);
            }
            return new WP_Error('subscription_error', __('No se pudo registrar la suscripción.', 'garantias-online-360vo'), ['status' => 500]);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push] subscription stored for user ' . $user_id);
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

        if ($endpoint === 'all') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GO360 Push] removing all subscriptions for user ' . $user_id);
            }

            $this->repository->remove_all_for_user($user_id);
        } else {
            do_action('go360/push/log', 'subscription_delete', [
                'user'     => $user_id,
                'endpoint' => substr((string) $endpoint, 0, 80),
            ]);

            $this->repository->remove_by_endpoint($endpoint);
        }

        return new WP_REST_Response(['success' => true]);
    }

    public function get_subscription_status(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error('not_logged_in', __('Debes iniciar sesión para consultar las notificaciones.', 'garantias-online-360vo'), ['status' => 401]);
        }

        $subscriptions = $this->repository->get_user_subscriptions($user_id);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GO360 Push] subscription status for user ' . $user_id . ': ' . wp_json_encode([
                'count' => count($subscriptions),
            ]));
        }

        return new WP_REST_Response([
            'hasSubscriptions' => ! empty($subscriptions),
            'count'            => count($subscriptions),
            'subscriptions'    => $subscriptions,
        ]);
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
        $raw_public_key = $keys['p256dh'] ?? ($params['publicKey'] ?? '');
        $raw_auth_token = $keys['auth'] ?? ($params['authToken'] ?? '');
        $content_encoding = isset($params['contentEncoding'])
            ? sanitize_key((string) $params['contentEncoding'])
            : (isset($params['content_encoding']) ? sanitize_key((string) $params['content_encoding']) : 'aes128gcm');

        return [
            'endpoint'         => $endpoint,
            'publicKey'        => Base64KeyNormalizer::normalize($raw_public_key),
            'authToken'        => Base64KeyNormalizer::normalize($raw_auth_token),
            'contentEncoding'  => $content_encoding !== '' ? $content_encoding : 'aes128gcm',
            'userAgent'        => isset($params['userAgent']) ? sanitize_text_field((string) $params['userAgent']) : '',
        ];
    }
}
