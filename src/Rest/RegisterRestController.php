<?php

namespace GarantiasOnline360VO\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

class RegisterRestController
{
    private const NAMESPACE = 'go/public/v1';
    private const ROUTE_EMAIL = '/register/check-email';

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE_EMAIL,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'check_email'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'email' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_email',
                        'validate_callback' => function ($value) {
                            return is_string($value) && is_email($value);
                        },
                    ],
                ],
            ]
        );
    }

    public static function check_email(WP_REST_Request $request)
    {
        $email = $request->get_param('email');
        if (! is_string($email) || ! is_email($email)) {
            return new WP_Error('invalid_email', __('Correo electrónico inválido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $exists = (bool) email_exists($email);

        return new WP_REST_Response(['exists' => $exists], 200);
    }
}
