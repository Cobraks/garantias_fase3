<?php
// src/ModalidadesSeeder.php
/*BORRAR, YA TENEMOS SEEDER.PHP*/

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class ModalidadesSeeder
{
    public const OPTION_MODALIDADES_SEEDED = 'go_modalidades_seeded';

    /** Se engancha a init tras TermsSeeder (prioridad 30) */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_seed'], 30);
    }

    public static function maybe_seed(): void
    {
        // solo si ya tenemos los términos y todavía no hemos sembrado modalidades
        if (
            ! get_option(TermsSeeder::OPTION_TERMS_SEEDED, false)
            || get_option(self::OPTION_MODALIDADES_SEEDED, false)
        ) {
            return;
        }

        self::run_on_activation();
        update_option(self::OPTION_MODALIDADES_SEEDED, true);
    }

    /** Crea/actualiza cada CPT de modalidad y le asigna términos */
    public static function run_on_activation(): void
    {
        $defs = [
            ['Essential Normal',      ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'essential'],
            ['Confort Normal',        ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'confort'],
            ['Exclusive Normal',      ['turismo', 'suv', 'todoterreno', 'furgoneta'], 'normal',            'exclusive'],

            ['Confort Eléctricos / Híbridos',  ['eléctrico', 'híbrido'], 'eléctrico-híbrido', 'confort'],
            ['Exclusive Eléctricos / Híbridos', ['eléctrico', 'híbrido'], 'eléctrico-híbrido', 'exclusive'],

            ['Confort GLP / GNC',     ['glp-gnc'], 'glp-gnc',           'confort'],
            ['Exclusive GLP / GNC',   ['glp-gnc'], 'glp-gnc',           'exclusive'],

            ['Camper Confort',        ['autocaravana'], 'camper',    'confort'],
            ['Camper Exclusive',      ['autocaravana'], 'camper',    'exclusive'],

            ['Essential Truck',       ['camion'], 'trucks',             'essential'],
            ['Confort Truck',         ['camion'], 'trucks',             'confort'],
            ['Exclusive Truck',       ['camion'], 'trucks',             'exclusive'],

            ['Moto Ocasión Confort',  ['moto'],   'moto-quad',          'confort'],
            ['Quad Ocasión Confort',  ['quad'],   'moto-quad',          'confort'],
            ['Moto Nueva Exclusive',  ['moto'],   'moto-quad',          'exclusive'],
            ['Quad Nuevo Exclusive',  ['quad'],   'moto-quad',          'exclusive'],
        ];

        foreach ($defs as $def) {
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

            if (! $post_id || is_wp_error($post_id)) {
                continue;
            }

            // IDs de vehículos
            $veh_ids = [];
            foreach ($veh_slugs as $vs) {
                if ($t = get_term_by('slug', sanitize_title($vs), 'tipo_vehiculo')) {
                    $veh_ids[] = (int) $t->term_id;
                }
            }
            $tg = get_term_by('slug', sanitize_title($tg_slug), 'tipo_garantia');
            $nv = get_term_by('slug', sanitize_title($nv_slug), 'nivel_garantia');

            if ($veh_ids) {
                wp_set_object_terms($post_id, $veh_ids,   'tipo_vehiculo',  false);
            }
            if ($tg) {
                wp_set_object_terms($post_id, (int) $tg->term_id, 'tipo_garantia',  false);
            }
            if ($nv) {
                wp_set_object_terms($post_id, (int) $nv->term_id, 'nivel_garantia', false);
            }
        }
    }

    /** Borra todas las modalidades creadas por este seeder */
    public static function run_on_deactivation(): void
    {
        $all = get_posts([
            'post_type'   => ModalidadesGarantiasCPT::POST_TYPE,
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        foreach ($all as $pid) {
            wp_delete_post($pid, true);
        }
        delete_option(self::OPTION_MODALIDADES_SEEDED);
    }
}
