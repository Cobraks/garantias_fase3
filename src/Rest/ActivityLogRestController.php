<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

class ActivityLogRestController
{
    public const NAMESPACE = 'go/v1';
    public const BASE = 'activity';

    public static function register_routes(): void
    {
        $routes = [self::BASE, 'logs'];
        foreach ($routes as $route) {
            register_rest_route(
                self::NAMESPACE,
                '/' . $route,
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [__CLASS__, 'get_items'],
                        'permission_callback' => [__CLASS__, 'permissions'],
                        'args'                => self::request_args(),
                    ],
                ]
            );
        }
    }

    public static function permissions(): bool
    {
        return is_user_logged_in() && current_user_can('read');
    }

    public static function get_items(WP_REST_Request $request)
    {
        $current_id = get_current_user_id();
        $filters = [
            'page'        => (int) $request->get_param('page'),
            'per_page'    => (int) $request->get_param('per_page'),
            'search'      => (string) $request->get_param('search'),
            'event_type'  => (string) $request->get_param('event_type'),
            'category'    => (string) $request->get_param('category'),
            'level'       => (string) $request->get_param('level'),
            'actor_id'    => (int) $request->get_param('actor_id'),
            'target_type' => (string) $request->get_param('target_type'),
            'target_id'   => (int) $request->get_param('target_id'),
            'guarantee_id'=> (int) $request->get_param('guarantee_id'),
            'channel'     => (string) $request->get_param('channel'),
            'vendor_id'   => (int) $request->get_param('vendor_id'),
            'date_from'   => (string) $request->get_param('date_from'),
            'date_to'     => (string) $request->get_param('date_to'),
            'order'       => (string) $request->get_param('order'),
            'order_by'    => (string) $request->get_param('order_by'),
        ];

        if ($filters['page'] < 1) {
            $filters['page'] = 1;
        }
        if ($filters['per_page'] < 1) {
            $filters['per_page'] = 25;
        }

        if (! current_user_can('manage_options')) {
            $filters['actor_id'] = $current_id;
            if (! empty($filters['vendor_id']) && (int) $filters['vendor_id'] !== $current_id) {
                $filters['vendor_id'] = 0;
            }
        }

        $result = ActivityLogger::query($filters);
        if ($result instanceof WP_Error) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    private static function request_args(): array
    {
        return [
            'page' => [
                'validate_callback' => 'absint',
                'default'           => 1,
            ],
            'per_page' => [
                'validate_callback' => 'absint',
                'default'           => 25,
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'event_type' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_key',
            ],
            'level' => [
                'sanitize_callback' => 'sanitize_key',
            ],
            'actor_id' => [
                'validate_callback' => 'absint',
            ],
            'target_type' => [
                'sanitize_callback' => 'sanitize_key',
            ],
            'target_id' => [
                'validate_callback' => 'absint',
            ],
            'guarantee_id' => [
                'validate_callback' => 'absint',
            ],
            'channel' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'vendor_id' => [
                'validate_callback' => 'absint',
            ],
            'date_from' => [
                'sanitize_callback' => [__CLASS__, 'sanitize_date'],
            ],
            'date_to' => [
                'sanitize_callback' => [__CLASS__, 'sanitize_date'],
            ],
            'order' => [
                'sanitize_callback' => [__CLASS__, 'sanitize_order'],
                'default'           => 'DESC',
            ],
            'order_by' => [
                'sanitize_callback' => [__CLASS__, 'sanitize_order_by'],
                'default'           => 'created_at',
            ],
        ];
    }

    public static function sanitize_date($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if (! $timestamp) {
            return '';
        }
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    public static function sanitize_order($value): string
    {
        $value = strtoupper(is_string($value) ? $value : 'DESC');
        return in_array($value, ['ASC', 'DESC'], true) ? $value : 'DESC';
    }

    public static function sanitize_order_by($value): string
    {
        $allowed = ['created_at', 'event_type', 'level'];
        $value = is_string($value) ? $value : 'created_at';
        return in_array($value, $allowed, true) ? $value : 'created_at';
    }
}
