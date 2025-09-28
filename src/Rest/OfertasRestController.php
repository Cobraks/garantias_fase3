<?php

namespace GarantiasOnline360VO\Rest;

if (!defined('ABSPATH')) {
    exit;
}

class OfertasRestController
{
    const NAMESPACE = 'go/v1';
    const REST_BASE = 'ofertas-usuario';

    public static function register_routes()
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [__CLASS__, 'get_items'],
                    'permission_callback' => [__CLASS__, 'permissions_check'],
                    'args'                => [
                        'id' => [
                            'description' => 'User ID (opcional, solo admins). Si se omite, devuelve las ofertas del usuario actual.',
                            'required'    => false,
                            'type'        => 'integer',
                        ],
                    ],
                ],
            ]
        );
    }

    private static function has_global_scope($user = null): bool
    {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        if (!$user instanceof \WP_User) {
            return false;
        }

        if (user_can($user, 'manage_options')) {
            return true;
        }

        $roles = (array) $user->roles;
        $global_roles = ['go_garantias', 'go_director_comercial'];

        return (bool) array_intersect($roles, $global_roles);
    }

    public static function permissions_check($request)
    {
        // Solo usuarios logueados
        if (!is_user_logged_in()) {
            return false;
        }
        return true;
    }

    public static function get_items($request)
    {
        $current_user = wp_get_current_user();
        $current_user_id = $current_user->ID;
        $can_query_others = self::has_global_scope($current_user);
        $requested_user_id = $request->get_param('id');
        $user_id = $current_user_id;

        if ($requested_user_id && $requested_user_id != $current_user_id) {
            if (!$can_query_others) {
                // No permitir acceso a otros usuarios
                return new \WP_REST_Response([], 200);
            }
            $user_id = intval($requested_user_id);
        }

        if (!$user_id) {
            return new \WP_REST_Response([], 200);
        }

        // Solo para profesionales
        $user = get_userdata($user_id);
        if (!$user || !in_array('go_profesional', $user->roles, true)) {
            return new \WP_REST_Response([], 200);
        }

        $ofertas = get_field('ofertas_y_descuentos', 'user_' . $user_id);
        if (empty($ofertas) || !isset($ofertas['ofertas']) || !is_array($ofertas['ofertas'])) {
            return new \WP_REST_Response([], 200);
        }

        $now = time();
        $ofertas_clean = [];

        foreach ($ofertas['ofertas'] as $oferta) {
            if (empty($oferta['estado']) || empty($oferta['porcentaje_descuento'])) continue;

            $caducidad_ok = true;
            $fecha_cad = $oferta['caducidad_oferta'] ?? '';
            $timestamp_cad = null;
            if (!empty($fecha_cad)) {
                $fecha_parts = explode('/', $fecha_cad);
                if (count($fecha_parts) === 3) {
                    $timestamp_cad = strtotime("{$fecha_parts[2]}-{$fecha_parts[1]}-{$fecha_parts[0]} 23:59:59");
                    if ($now > $timestamp_cad) $caducidad_ok = false;
                }
            }
            if (!$caducidad_ok) continue;

            $etiqueta = '';
            $tipo_value = '';
            if (is_array($oferta['tipo_oferta'])) {
                $etiqueta = $oferta['tipo_oferta']['label'] ?? $oferta['tipo_oferta']['value'] ?? '';
                $tipo_value = $oferta['tipo_oferta']['value'] ?? '';
            } else {
                $etiqueta = $oferta['tipo_oferta'] ?? '';
                $tipo_value = $oferta['tipo_oferta'] ?? '';
            }
            $nombre_final = ($tipo_value === 'personalizar' && !empty($oferta['nombre_oferta']))
                ? $oferta['nombre_oferta']
                : $etiqueta;

            $seleccion_modalidad_ids = [];
            if (!empty($oferta['seleccion_modalidad']) && is_array($oferta['seleccion_modalidad'])) {
                foreach ($oferta['seleccion_modalidad'] as $sel) {
                    if (is_array($sel) && isset($sel['ID'])) {
                        $seleccion_modalidad_ids[] = intval($sel['ID']);
                    } else {
                        $seleccion_modalidad_ids[] = intval($sel);
                    }
                }
            }

            $ofertas_clean[] = [
                'tipo_oferta'          => $tipo_value,
                'nombre'               => $nombre_final,
                'etiqueta'             => $etiqueta,
                'porcentaje_descuento' => floatval($oferta['porcentaje_descuento'] ?? 0),
                'aplicacion'           => $oferta['aplicacion'] ?? [],
                'seleccion_modalidad'  => $seleccion_modalidad_ids,
                'estado'               => isset($oferta['estado']) ? (bool)$oferta['estado'] : true,
                'caducidad_oferta'     => $fecha_cad,
                'timestamp_caducidad'  => $timestamp_cad,
            ];
        }

        return new \WP_REST_Response($ofertas_clean, 200);
    }
}
