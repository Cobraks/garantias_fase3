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
        $is_admin = user_can($current_user, 'manage_options');
        $requested_user_id = $request->get_param('id');
        $user_id = $current_user_id;

        if ($requested_user_id && $requested_user_id != $current_user_id) {
            if (!$is_admin) {
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

        $ofertas_group = get_field('ofertas_y_descuentos', 'user_' . $user_id);
        if (!is_array($ofertas_group)) {
            $ofertas_group = [];
        }

        $ofertas_list = isset($ofertas_group['ofertas']) && is_array($ofertas_group['ofertas'])
            ? $ofertas_group['ofertas']
            : [];

        $tiene_precio_fijo = !empty($ofertas_group['tiene_oferta_especial_precio_fijo']);
        $ofertas_fijas_raw = [];
        if ($tiene_precio_fijo && !empty($ofertas_group['oferta_especial_precio_fijo']) && is_array($ofertas_group['oferta_especial_precio_fijo'])) {
            $ofertas_fijas_raw = $ofertas_group['oferta_especial_precio_fijo'];
        }

        $ofertas_fijas = [];
        foreach ($ofertas_fijas_raw as $entrada) {
            $tipo_id = isset($entrada['tipo_de_garantia']) ? intval($entrada['tipo_de_garantia']) : 0;
            $nivel_id = isset($entrada['nivel_garantia']) ? intval($entrada['nivel_garantia']) : 0;
            $precio_fijo_raw = $entrada['precio_fijo'] ?? '';
            $precio_fijo = $precio_fijo_raw === '' ? null : floatval(str_replace(',', '.', (string) $precio_fijo_raw));

            if (!$tipo_id || !$nivel_id || $precio_fijo === null) {
                continue;
            }

            $tipo_term = get_term($tipo_id, 'tipo_garantia');
            $nivel_term = get_term($nivel_id, 'nivel_garantia');

            if (is_wp_error($tipo_term) || is_wp_error($nivel_term)) {
                continue;
            }

            $duracion = $entrada['duracion_maxima'] ?? null;
            $duracion_value = null;
            $duracion_label = '';
            if (is_array($duracion)) {
                if (isset($duracion['value'])) {
                    $duracion_value = is_numeric($duracion['value']) ? intval($duracion['value']) : null;
                    $duracion_label = $duracion['label'] ?? '';
                } elseif (isset($duracion[0])) {
                    $duracion_value = is_numeric($duracion[0]) ? intval($duracion[0]) : null;
                    $duracion_label = $duracion[1] ?? '';
                }
            } elseif ($duracion !== null && $duracion !== '') {
                $duracion_value = is_numeric($duracion) ? intval($duracion) : null;
            }

            if ($duracion_label === '' && $duracion_value !== null) {
                $duracion_label = sprintf('%d meses', $duracion_value);
            }

            $ofertas_fijas[] = [
                'tipo_garantia' => [
                    'id'   => $tipo_id,
                    'slug' => $tipo_term ? $tipo_term->slug : '',
                    'name' => $tipo_term ? $tipo_term->name : '',
                ],
                'nivel_garantia' => [
                    'id'   => $nivel_id,
                    'slug' => $nivel_term ? $nivel_term->slug : '',
                    'name' => $nivel_term ? $nivel_term->name : '',
                ],
                'precio_fijo' => $precio_fijo,
                'excluir_resto_de_niveles' => !empty($entrada['excluir_resto_de_niveles']),
                'duracion_maxima' => [
                    'value' => $duracion_value,
                    'label' => $duracion_label,
                ],
            ];
        }

        $now = time();
        $ofertas_clean = [];

        foreach ($ofertas_list as $oferta) {
            $estado_activo = isset($oferta['estado']) ? (bool) $oferta['estado'] : false;
            if (!$estado_activo) {
                continue;
            }

            $etiqueta = '';
            $tipo_value = '';
            if (is_array($oferta['tipo_oferta'])) {
                $etiqueta = $oferta['tipo_oferta']['label'] ?? $oferta['tipo_oferta']['value'] ?? '';
                $tipo_value = $oferta['tipo_oferta']['value'] ?? '';
            } else {
                $etiqueta = $oferta['tipo_oferta'] ?? '';
                $tipo_value = $oferta['tipo_oferta'] ?? '';
            }

            $es_sin_suplementos = ($tipo_value === 'sin_suplementos');
            $porcentaje_raw = $oferta['porcentaje_descuento'] ?? 0;
            $porcentaje_descuento = $porcentaje_raw === '' ? 0 : floatval($porcentaje_raw);

            if (!$es_sin_suplementos && $porcentaje_descuento === 0.0) {
                continue;
            }

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
                'porcentaje_descuento' => $porcentaje_descuento,
                'aplicacion'           => $oferta['aplicacion'] ?? [],
                'seleccion_modalidad'  => $seleccion_modalidad_ids,
                'estado'               => isset($oferta['estado']) ? (bool)$oferta['estado'] : true,
                'caducidad_oferta'     => $fecha_cad,
                'timestamp_caducidad'  => $timestamp_cad,
            ];
        }

        return new \WP_REST_Response([
            'ofertas' => $ofertas_clean,
            'especial_precio_fijo' => [
                'habilitado' => $tiene_precio_fijo && !empty($ofertas_fijas),
                'items'      => $tiene_precio_fijo ? $ofertas_fijas : [],
            ],
        ], 200);
    }
}
