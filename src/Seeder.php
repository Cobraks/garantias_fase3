<?php
// src/Seeder.php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Seeder
{
    /** Opción usada para seguir el estado del seed */
    public const OPTION_STATUS       = 'go_seed_status';
    public const OPTION_TERMS_DONE   = 'go_terms_done';
    public const OPTION_ALL_DONE     = 'go_all_done';

    /** Hook setup: en init comprobamos estado y lanzamos seed */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_seed'], 20);
    }

    /**
     * Si la opción indica “pending”, primero seed_terms; luego seed_modalidades.
     * Solo en admin y si current_user_can('activate_plugins').
     * También evitamos correrlo bajo WP-CLI para no interferir.
     */
    public static function maybe_seed(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        // solo en back‑office y si puede activar plugins
        if (! is_admin() || ! current_user_can('activate_plugins')) {
            return;
        }

        $status = get_option(self::OPTION_STATUS, '');

        if ($status === 'pending') {
            self::seed_terms();
            update_option(self::OPTION_STATUS, self::OPTION_TERMS_DONE);
        }

        if ($status === self::OPTION_TERMS_DONE) {
            self::seed_modalidades();
            update_option(self::OPTION_STATUS, self::OPTION_ALL_DONE);
        }
    }

    /** Crea los términos de las 3 taxonomías si no existen */
    private static function seed_terms(): void
    {
        $tax_terms = [
            'tipo_vehiculo' => [
                'Turismo',
                'SUV',
                'Todoterreno',
                'Furgoneta',
                'Moto',
                'Quad',
                'Camión',
                'Autocaravana',
            ],
            'tipo_garantia' => [
                'Normal',
                'Eléctrico / Híbrido',
                'GLP / GNC',
                'Moto / Quad',
                'Camper',
                'Trucks',
            ],
            'nivel_garantia' => [
                'Essential',
                'Confort',
                'Exclusive',
            ],
        ];

        foreach ($tax_terms as $taxonomy => $terms) {
            foreach ($terms as $name) {
                $slug = sanitize_title($name);
                if (! term_exists($slug, $taxonomy)) {
                    $inserted = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
                    if (is_wp_error($inserted)) {
                        error_log("[360VO][Seeder] Error creando término “{$name}” en “{$taxonomy}”: " . $inserted->get_error_message());
                    }
                }
            }
        }
    }

    /** Crea/actualiza cada CPT “modalidad_garantia” asignando los términos */
    private static function seed_modalidades(): void
    {
        // 1) Recoger todos los IDs de cada taxonomy y mapear slug→ID
        $veh_map = self::get_tax_map('tipo_vehiculo');
        $tg_map  = self::get_tax_map('tipo_garantia');
        $nv_map  = self::get_tax_map('nivel_garantia');

        // 2) Definiciones de modalidades
        $definitions = [
            ['Essential Normal',      ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'essential'],
            ['Confort Normal',        ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'confort'],
            ['Exclusive Normal',      ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'exclusive'],
            ['Confort Eléctricos / Híbridos',  ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'electrico-hibrido', 'confort'],
            ['Exclusive Eléctricos / Híbridos', ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'electrico-hibrido', 'exclusive'],
            ['Confort GLP / GNC',     ['turismo', 'suv', 'todoterreno', 'furgoneta'],                               'glp-gnc',           'confort'],
            ['Exclusive GLP / GNC',   ['turismo', 'suv', 'todoterreno', 'furgoneta'],                               'glp-gnc',           'exclusive'],
            ['Camper Confort',        ['autocaravana'],                          'camper',            'confort'],
            ['Camper Exclusive',      ['autocaravana'],                          'camper',            'exclusive'],
            ['Essential Truck',       ['camion'],                                'trucks',            'essential'],
            ['Confort Truck',         ['camion'],                                'trucks',            'confort'],
            ['Exclusive Truck',       ['camion'],                                'trucks',            'exclusive'],
            ['Moto Ocasión Confort',  ['moto'],                                  'moto-quad',         'confort'],
            ['Quad Ocasión Confort',  ['quad'],                                  'moto-quad',         'confort'],
            ['Moto Nueva Exclusive',  ['moto'],                                  'moto-quad',         'exclusive'],
            ['Quad Nuevo Exclusive',  ['quad'],                                  'moto-quad',         'exclusive'],
        ];

        foreach ($definitions as $def) {
            list($title, $veh_slugs, $tg_slug, $nv_slug) = $def;
            $slug    = sanitize_title($title);
            $exists  = get_page_by_path($slug, OBJECT, ModalidadesGarantiasCPT::POST_TYPE);
            $post_id = $exists
                ? $exists->ID
                : wp_insert_post([
                    'post_type'   => ModalidadesGarantiasCPT::POST_TYPE,
                    'post_title'  => $title,
                    'post_name'   => $slug,
                    'post_status' => 'publish',
                ]);

            if (is_wp_error($post_id) || ! $post_id) {
                error_log("[360VO][Seeder] Error creando modalidad “{$title}”: " . (is_wp_error($post_id) ? $post_id->get_error_message() : 'ID inválido'));
                continue;
            }

            // 3) Asignar términos
            $assign_veh = [];
            foreach ($veh_slugs as $vs) {
                if (isset($veh_map[sanitize_title($vs)])) {
                    $assign_veh[] = $veh_map[sanitize_title($vs)];
                }
            }
            if ($assign_veh) {
                wp_set_object_terms($post_id, $assign_veh, 'tipo_vehiculo', false);
            }
            if (isset($tg_map[$tg_slug])) {
                wp_set_object_terms($post_id, (int) $tg_map[$tg_slug], 'tipo_garantia', false);
            }
            if (isset($nv_map[$nv_slug])) {
                wp_set_object_terms($post_id, (int) $nv_map[$nv_slug], 'nivel_garantia', false);
            }
        }
    }

    /** Devuelve un map slug→term_id de toda una taxonomy */
    private static function get_tax_map(string $taxonomy): array
    {
        $map      = [];
        $terms_id = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if (is_wp_error($terms_id)) {
            error_log("[360VO][Seeder] Error obteniendo términos de {$taxonomy}: " . $terms_id->get_error_message());
            return [];
        }
        foreach ($terms_id as $id) {
            $t           = get_term($id, $taxonomy);
            $map[$t->slug] = $id;
        }
        return $map;
    }

    /**
     * Limpia todo lo seedado: CPT + términos de taxonomías.
     * (llamado en register_deactivation_hook)
     */
    public static function clean(): void
    {
        // 1) Borrar modalidades
        $all = get_posts([
            'post_type'   => ModalidadesGarantiasCPT::POST_TYPE,
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        foreach ($all as $pid) {
            wp_delete_post($pid, true);
        }

        // 2) Borrar términos
        foreach (['tipo_vehiculo', 'tipo_garantia', 'nivel_garantia'] as $taxonomy) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'fields'     => 'ids',
            ]);
            if (! is_wp_error($terms)) {
                foreach ($terms as $tid) {
                    wp_delete_term((int) $tid, $taxonomy);
                }
            }
        }
    }
}
