<?php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Server;
use WP_REST_Response;
use GarantiasOnline360VO\GuaranteeLogger;

class GuaranteeLogRestController
{
    const NAMESPACE = 'go/v1';
    const BASE = 'logs';

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_items'],
                    'permission_callback' => [__CLASS__, 'permissions'],
                    'args'                => [
                        'user_id'      => ['validate_callback' => 'absint'],
                        'guarantee_id' => ['validate_callback' => 'absint'],
                        'page'         => ['validate_callback' => 'absint', 'default' => 1],
                        'per_page'     => ['validate_callback' => 'absint', 'default' => 20],
                    ],
                ],
            ]
        );
    }

    public static function permissions($request)
    {
        if (! is_user_logged_in()) {
            return false;
        }
        if (current_user_can('manage_options')) {
            return true;
        }
        $current = get_current_user_id();
        $requested = isset($request['user_id']) ? (int) $request['user_id'] : $current;
        return $requested === $current;
    }

    public static function get_items($request)
    {
        $current = get_current_user_id();
        $user_id = isset($request['user_id']) ? absint($request['user_id']) : (current_user_can('manage_options') ? 0 : $current);
        $args = [
            'user_id'      => $user_id,
            'guarantee_id' => isset($request['guarantee_id']) ? absint($request['guarantee_id']) : 0,
            'page'         => isset($request['page']) ? absint($request['page']) : 1,
            'per_page'     => isset($request['per_page']) ? absint($request['per_page']) : 20,
        ];
        $logs = GuaranteeLogger::get_logs($args);
        return new WP_REST_Response($logs);
    }
}
