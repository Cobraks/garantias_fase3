<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Register\RegistrationService;
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
    private const ROUTE_EMAIL   = '/register/check-email';
    private const ROUTE_CREATE  = '/register';
    private const ROUTE_VERIFY  = '/register/verify';
    private const ROUTE_RESEND  = '/register/resend';
    private const ROUTE_STATUS  = '/register/verification';

    private const RATE_LIMITS = [
        'check_email' => ['window' => 60, 'max' => 20],
        'register'    => ['window' => 300, 'max' => 5],
        'verify'      => ['window' => 300, 'max' => 25],
        'resend'      => ['window' => 300, 'max' => 10],
        'status'      => ['window' => 120, 'max' => 20],
    ];

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

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE_CREATE,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'create_account'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE_VERIFY,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'verify_account'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE_RESEND,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'resend_code'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            self::ROUTE_STATUS,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_verification'],
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
        $rate_limited = self::check_rate_limit('check_email', $request);
        if ($rate_limited instanceof WP_Error) {
            return self::error_response($rate_limited);
        }

        $email = $request->get_param('email');
        if (! is_string($email) || ! is_email($email)) {
            return new WP_Error('invalid_email', __('Correo electrónico inválido.', 'garantias-online-360vo'), ['status' => 400]);
        }

        $exists = (bool) email_exists($email);

        return new WP_REST_Response(['exists' => $exists], 200);
    }

    public static function create_account(WP_REST_Request $request)
    {
        $rate_limited = self::check_rate_limit('register', $request);
        if ($rate_limited instanceof WP_Error) {
            return self::error_response($rate_limited);
        }

        $service = new RegistrationService();
        $data    = $request->get_params();
        $files   = $request->get_file_params();

        $result = $service->register($data, $files);
        if ($result instanceof WP_Error) {
            return self::error_response($result);
        }

        return new WP_REST_Response($result, 201);
    }

    public static function verify_account(WP_REST_Request $request)
    {
        $rate_limited = self::check_rate_limit('verify', $request);
        if ($rate_limited instanceof WP_Error) {
            return self::error_response($rate_limited);
        }

        $service = new RegistrationService();
        $params  = self::get_body_params($request);
        $token   = isset($params['token']) ? (string) $params['token'] : '';
        $code    = isset($params['code']) ? (string) $params['code'] : '';

        $result = $service->verify($token, $code);
        if ($result instanceof WP_Error) {
            return self::error_response($result);
        }

        return new WP_REST_Response($result, 200);
    }

    public static function resend_code(WP_REST_Request $request)
    {
        $rate_limited = self::check_rate_limit('resend', $request);
        if ($rate_limited instanceof WP_Error) {
            return self::error_response($rate_limited);
        }

        $service = new RegistrationService();
        $params  = self::get_body_params($request);
        $token   = isset($params['token']) ? (string) $params['token'] : '';

        $result = $service->resend($token);
        if ($result instanceof WP_Error) {
            return self::error_response($result);
        }

        return new WP_REST_Response($result, 200);
    }

    public static function get_verification(WP_REST_Request $request)
    {
        $rate_limited = self::check_rate_limit('status', $request);
        if ($rate_limited instanceof WP_Error) {
            return self::error_response($rate_limited);
        }

        $service = new RegistrationService();
        $email   = (string) $request->get_param('email');

        $result = $service->get_verification_context($email);
        if ($result instanceof WP_Error) {
            return self::error_response($result);
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * @return array<string, mixed>
     */
    private static function get_body_params(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        if (! is_array($params) || empty($params)) {
            $params = $request->get_body_params();
        }

        return is_array($params) ? $params : [];
    }


    /**
     * @return true|WP_Error
     */
    private static function check_rate_limit(string $action, WP_REST_Request $request)
    {
        if (! isset(self::RATE_LIMITS[$action])) {
            return true;
        }

        $limit = self::RATE_LIMITS[$action];
        $window = (int) ($limit['window'] ?? 60);
        $max = (int) ($limit['max'] ?? 10);

        if ($window <= 0 || $max <= 0) {
            return true;
        }

        $ip = self::resolve_client_ip($request);
        $key = 'go360_rl_' . md5($action . '|' . $ip);
        $state = get_transient($key);

        if (! is_array($state) || empty($state['start']) || ! isset($state['count'])) {
            $state = [
                'start' => time(),
                'count' => 0,
            ];
        }

        $elapsed = time() - (int) $state['start'];
        if ($elapsed >= $window) {
            $state = [
                'start' => time(),
                'count' => 0,
            ];
            $elapsed = 0;
        }

        $state['count'] = (int) $state['count'] + 1;
        set_transient($key, $state, $window);

        if ($state['count'] > $max) {
            return new WP_Error(
                'go_register_rate_limit',
                __('Demasiadas solicitudes. Espera unos minutos y vuelve a intentarlo.', 'garantias-online-360vo'),
                [
                    'status' => 429,
                    'retry_in' => max(1, $window - $elapsed),
                ]
            );
        }

        return true;
    }

    private static function resolve_client_ip(WP_REST_Request $request): string
    {
        $headers = [
            'x-forwarded-for',
            'x-real-ip',
            'client-ip',
        ];

        foreach ($headers as $header) {
            $value = $request->get_header($header);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $ip = trim($part);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $server_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($server_ip) && filter_var($server_ip, FILTER_VALIDATE_IP)) {
            return $server_ip;
        }

        return 'unknown';
    }

    private static function error_response(WP_Error $error)
    {
        $data = $error->get_error_data();
        $status = 400;
        if (is_array($data) && isset($data['status'])) {
            $status = (int) $data['status'];
        }

        $response = [
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
            'data'    => is_array($data) ? $data : [],
        ];

        return new WP_REST_Response($response, $status);
    }
}
