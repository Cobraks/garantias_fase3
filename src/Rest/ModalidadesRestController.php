<?php
// src/Rest/ModalidadesRestController.php

namespace GarantiasOnline360VO\Rest;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class ModalidadesRestController extends WP_REST_Controller
{
    const NAMESPACE = 'go/v1';
    const REST_BASE = 'modalidades';

    public function register_routes()
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'permissions_check'],
                ]
            ]
        );
    }

    public function permissions_check($request)
    {
        return is_user_logged_in(); // Cambia esto según permisos
    }

    public function get_items($request)
    {
        $query = new \WP_Query([
            'post_type'      => 'modalidad_garantia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $items = [];

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            // Campos ACF
            $acf = function_exists('get_fields') ? get_fields($post_id) : [];

            // Taxonomías (puedes devolver nombres o IDs)
            $tipo_garantia   = wp_get_object_terms($post_id, 'tipo_garantia',   ['fields' => 'slugs']);
            $nivel_garantia  = wp_get_object_terms($post_id, 'nivel_garantia',  ['fields' => 'slugs']);
            // ModalidadesRestController.php
            $tipo_vehiculo   = wp_get_object_terms($post_id, 'tipo_vehiculo',   ['fields' => 'slugs']);


            $items[] = [
                'ID'              => $post_id,
                'title'           => get_the_title(),
                'acf'             => $acf,
                'tipo_garantia'   => $tipo_garantia,
                'nivel_garantia'  => $nivel_garantia,
                'tipo_vehiculo'   => $tipo_vehiculo,
                'slug'            => get_post_field('post_name', $post_id),
            ];
        }
        wp_reset_postdata();

        return new WP_REST_Response($items, 200);
    }
}
