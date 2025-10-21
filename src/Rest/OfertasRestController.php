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

        $group = get_field('ofertas_y_descuentos', 'user_' . $user_id);

        $especiales = self::extract_special_price_offers(is_array($group) ? $group : []);

        $lista_ofertas = [];
        if (is_array($group) && isset($group['ofertas']) && is_array($group['ofertas'])) {
            $lista_ofertas = $group['ofertas'];
        }

        $now = time();
        $ofertas_clean = [];

        foreach ($lista_ofertas as $oferta) {
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
            'ofertas'                          => $ofertas_clean,
            'tiene_oferta_especial_precio_fijo' => $especiales['enabled'],
            'ofertas_precio_fijo'               => $especiales['offers'],
        ], 200);
    }

    private static function extract_special_price_offers(array $group): array
    {
        $result = [
            'enabled' => false,
            'offers'  => [],
        ];

        if (empty($group)) {
            return $result;
        }

        $enabled_flag = !empty($group['tiene_oferta_especial_precio_fijo']);
        $rows         = isset($group['oferta_especial_precio_fijo']) && is_array($group['oferta_especial_precio_fijo'])
            ? $group['oferta_especial_precio_fijo']
            : [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = self::normalize_special_price_row($row);
            if ($normalized === null) {
                continue;
            }
            $result['offers'][] = $normalized;
        }

        if (!empty($result['offers'])) {
            $result['enabled'] = true;
        } elseif ($enabled_flag) {
            $result['enabled'] = true;
        }

        return $result;
    }

    private static function normalize_special_price_row(array $row): ?array
    {
        $tipo_id  = isset($row['tipo_de_garantia']) ? (int) $row['tipo_de_garantia'] : 0;
        $nivel_id = isset($row['nivel_garantia']) ? (int) $row['nivel_garantia'] : 0;

        if ($tipo_id <= 0 && $nivel_id <= 0) {
            return null;
        }

        $precio_raw = $row['precio_fijo'] ?? null;
        $precio     = null;
        if ($precio_raw !== null && $precio_raw !== '') {
            if (is_numeric($precio_raw)) {
                $precio = (float) $precio_raw;
            } elseif (is_string($precio_raw)) {
                $valor = str_replace(' ', '', $precio_raw);
                if (strpos($valor, ',') !== false) {
                    $valor = str_replace('.', '', $valor);
                    $valor = str_replace(',', '.', $valor);
                }
                if (is_numeric($valor)) {
                    $precio = (float) $valor;
                }
            }
        }

        if ($precio === null) {
            return null;
        }

        $tipo_info  = self::resolve_term_info($tipo_id, 'tipo_garantia');
        $nivel_info = self::resolve_term_info($nivel_id, 'nivel_garantia');

        if ($tipo_info['id'] === null && $tipo_info['slug'] === '') {
            return null;
        }
        if ($nivel_info['id'] === null && $nivel_info['slug'] === '') {
            return null;
        }

        $duracion_value = null;
        $duracion_label = '';
        if (isset($row['duracion_maxima'])) {
            $duracion_raw = $row['duracion_maxima'];
            if (is_array($duracion_raw)) {
                if (!empty($duracion_raw['value'])) {
                    $duracion_value = (int) $duracion_raw['value'];
                }
                if (!empty($duracion_raw['label'])) {
                    $duracion_label = (string) $duracion_raw['label'];
                }
            } elseif ($duracion_raw !== null && $duracion_raw !== '') {
                $duracion_value = (int) $duracion_raw;
            }
        }
        if ($duracion_label === '' && $duracion_value) {
            $duracion_label = sprintf('%d meses', $duracion_value);
        }

        return [
            'tipo_garantia_id'     => $tipo_info['id'],
            'tipo_garantia_slug'   => $tipo_info['slug'],
            'nivel_garantia_id'    => $nivel_info['id'],
            'nivel_garantia_slug'  => $nivel_info['slug'],
            'precio_fijo'          => $precio,
            'excluir_resto_niveles'=> !empty($row['excluir_resto_de_niveles']),
            'duracion_meses'       => $duracion_value ? (int) $duracion_value : null,
            'duracion_label'       => $duracion_label,
        ];
    }

    private static function resolve_term_info(int $term_id, string $taxonomy): array
    {
        $info = [
            'id'   => null,
            'slug' => '',
        ];

        if ($term_id <= 0) {
            return $info;
        }

        $term = get_term($term_id, $taxonomy);
        if ($term instanceof \WP_Term && ! is_wp_error($term)) {
            $info['id']   = (int) $term->term_id;
            $info['slug'] = (string) $term->slug;
            return $info;
        }

        $info['id'] = $term_id;
        return $info;
    }
}
