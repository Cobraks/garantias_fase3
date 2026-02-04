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

            // Taxonomías (devolvemos slugs e IDs para mapeos posteriores)
            $tipo_terms  = wp_get_object_terms($post_id, 'tipo_garantia', ['fields' => 'all']);
            if (is_wp_error($tipo_terms) || !is_array($tipo_terms)) {
                $tipo_terms = [];
            }
            $nivel_terms = wp_get_object_terms($post_id, 'nivel_garantia', ['fields' => 'all']);
            if (is_wp_error($nivel_terms) || !is_array($nivel_terms)) {
                $nivel_terms = [];
            }

            $tipo_garantia_slugs = array_map(
                static fn($term) => is_object($term) && isset($term->slug) ? (string) $term->slug : '',
                $tipo_terms
            );
            $tipo_garantia_slugs = array_values(array_filter($tipo_garantia_slugs));
            $tipo_garantia_ids   = array_map(
                static fn($term) => is_object($term) && isset($term->term_id) ? (int) $term->term_id : null,
                $tipo_terms
            );
            $tipo_garantia_ids = array_values(array_filter($tipo_garantia_ids, static fn($id) => $id !== null));

            $nivel_garantia_slugs = array_map(
                static fn($term) => is_object($term) && isset($term->slug) ? (string) $term->slug : '',
                $nivel_terms
            );
            $nivel_garantia_slugs = array_values(array_filter($nivel_garantia_slugs));
            $nivel_garantia_ids   = array_map(
                static fn($term) => is_object($term) && isset($term->term_id) ? (int) $term->term_id : null,
                $nivel_terms
            );
            $nivel_garantia_ids = array_values(array_filter($nivel_garantia_ids, static fn($id) => $id !== null));

            $tipo_vehiculo   = wp_get_object_terms($post_id, 'tipo_vehiculo',   ['fields' => 'slugs']);
            

            $items[] = [
                'ID'              => $post_id,
                'title'           => get_the_title(),
                'acf'             => $acf,
                'tipo_garantia'   => $tipo_garantia_slugs,
                'tipo_garantia_ids' => $tipo_garantia_ids,
                'nivel_garantia'  => $nivel_garantia_slugs,
                'nivel_garantia_ids' => $nivel_garantia_ids,
                'tipo_vehiculo'   => $tipo_vehiculo,
                'slug'            => get_post_field('post_name', $post_id),
            ];
        }
        wp_reset_postdata();

        return new WP_REST_Response($items, 200);
    }
}
