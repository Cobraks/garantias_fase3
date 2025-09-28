<?php

namespace GarantiasOnline360VO\Rest;

use GarantiasOnline360VO\Support\UserProfileResolver;
use WP_REST_Server;
use WP_User_Query;
use WP_REST_Response;

class UserRestController
{
    const NAMESPACE = 'go/v1';
    const BASE      = 'usuarios';

    public static function register_routes()
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::BASE,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_items'],
                    'permission_callback' => [__CLASS__, 'can_list'],
                    'args'                => [
                        'role' => [
                            'required' => false,
                            'validate_callback' => function ($param) {
                                return is_string($param) && !empty($param);
                            },
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        // Puedes añadir más filtros en el futuro
                    ],
                ],
            ]
        );

        // Nueva ruta para obtener estado SEPA
        register_rest_route(
            self::NAMESPACE,
            '/estado-sepa',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [__CLASS__, 'get_estado_sepa'],
                    'permission_callback' => [__CLASS__, 'can_list'],
                    'args' => [
                        'id' => [
                            'required' => false,
                            'type' => 'integer',
                        ],
                    ],
                ],
            ]
        );
    }

    public static function can_list($request)
    {
        // Prueba si WordPress realmente te ve logueado
        if (!is_user_logged_in()) {
            error_log('NO logueado para REST, sesión rota');
            return false;
        }
        $user = wp_get_current_user();
        error_log('Usuario para REST: ' . print_r($user, true));
        return true;
    }

    /**
     * GET /go/v1/usuarios?role=go_profesional
     */
    public static function get_items($request)
    {
        $role = $request->get_param('role');
        $args = [
            'role'   => $role ? $role : '', // Si no hay role, devuelve todos
            'fields' => 'all_with_meta',
            'number' => 100, // puedes paginar si quieres
        ];
        $users_query = new WP_User_Query($args);
        $users = [];

        $current_user = wp_get_current_user();
        $current_roles = $current_user instanceof \WP_User ? (array) $current_user->roles : [];
        $current_user_id = $current_user instanceof \WP_User ? (int) $current_user->ID : 0;
        $is_admin_like = user_can($current_user, 'manage_options')
            || in_array('go_garantias', $current_roles, true)
            || in_array('go_director_comercial', $current_roles, true);
        $is_comercial = in_array('go_comercial', $current_roles, true);
        $restrict_to_assigned = ($role === 'go_profesional') && $is_comercial && ! $is_admin_like && $current_user_id > 0;

        foreach ($users_query->get_results() as $user) {
            if (! $user instanceof \WP_User) {
                continue;
            }
            $user_id = $user->ID;
            $profile = UserProfileResolver::build_from_user($user);
            $item = [
                'id'             => $user_id,
                'display_name'   => $profile['personal_name'],
                'personal_name'  => $profile['personal_name'],
                'company_name'   => $profile['company']['name'] ?? '',
                'company'        => $profile['company'],
                'username'       => $profile['username'],
                'email'          => $profile['email'],
            ];

            // Si es profesional, añadimos comerciales asignados (ACF group)
            if ($role === 'go_profesional') {
                $comerciales = get_field('ajustes_usuarios_comercial_asignado', 'user_' . $user_id);
                $item['comerciales_asignados'] = [];
                $assigned_ids = [];
                if (is_array($comerciales) && count($comerciales)) {
                    foreach ($comerciales as $com_id) {
                        $com_user = get_user_by('id', $com_id);
                        if ($com_user instanceof \WP_User) {
                            $com_profile = UserProfileResolver::build_from_user($com_user);
                            $assigned_ids[] = (int) $com_user->ID;
                            $item['comerciales_asignados'][] = [
                                'id'             => $com_user->ID,
                                'display_name'   => $com_profile['personal_name'],
                                'personal_name'  => $com_profile['personal_name'],
                                'company_name'   => $com_profile['company']['name'] ?? '',
                                'email'          => $com_profile['email'],
                            ];
                        }
                    }
                }
                if ($restrict_to_assigned && ! in_array($current_user_id, $assigned_ids, true)) {
                    continue;
                }
            } elseif ($restrict_to_assigned) {
                // Si restringimos a asignados pero el usuario no es profesional, simplemente omitirlo
                continue;
            }

            $users[] = $item;
        }

        return rest_ensure_response($users);
    }

    /**
     * GET /go/v1/estado-sepa? id opcional (solo para admin que consulta otro profesional)
     */
    public static function get_estado_sepa($request)
    {
        $current_user = wp_get_current_user();
        $requested_id = $request->get_param('id');
        $is_admin = user_can($current_user, 'manage_options');

        if ($requested_id && $requested_id != $current_user->ID) {
            if (!$is_admin) {
                return new \WP_REST_Response(['estado_sepa' => false], 200);
            }
            $user_id = intval($requested_id);
        } else {
            $user_id = $current_user->ID;
        }

        if (!$user_id) {
            return new \WP_REST_Response(['estado_sepa' => false], 200);
        }

        // Navegar ACF group: gestion_pagos -> gestion_sepa -> estado_documentos -> estado_sepa
        $gestion_pagos = get_field('gestion_pagos', 'user_' . $user_id);
        $estado_sepa = false;
        if (
            is_array($gestion_pagos) &&
            isset($gestion_pagos['gestion_sepa']['estado_documentos']['estado_sepa'])
        ) {
            $estado_sepa = (bool) $gestion_pagos['gestion_sepa']['estado_documentos']['estado_sepa'];
        }

        return new \WP_REST_Response(['estado_sepa' => $estado_sepa], 200);
    }
}
