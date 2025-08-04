<?php
// src/TermsSeeder.php
/*BORRAR, YA TENEMOS SEEDER.PHP*/

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class TermsSeeder
{
    public const OPTION_DO           = 'go_do_seed';
    public const OPTION_TERMS_SEEDED = 'go_terms_seeded';

    /** Se engancha a init para sembrar solo si la bandera está activa */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'maybe_seed'], 20);
    }

    public static function maybe_seed(): void
    {
        if (! get_option(self::OPTION_DO, false)) {
            return;
        }

        // 1) Sembramos cada taxonomy
        self::seed_taxonomy('tipo_vehiculo', [
            'Turismo',
            'SUV',
            'Todoterreno',
            'Furgoneta',
            'Moto',
            'Quad',
            'Camión',
            'Autocaravana',
            'Híbrido',
            'Eléctrico',
            'GLP / GNC',
        ]);
        self::seed_taxonomy('tipo_garantia', [
            'Normal',
            'Eléctrico / Híbrido',
            'GLP / GNC',
            'Moto / Quad',
            'Camper',
            'Trucks',
        ]);
        self::seed_taxonomy('nivel_garantia', ['Essential', 'Confort', 'Exclusive']);

        // 2) Marcamos terminado y quitamos la bandera principal
        update_option(self::OPTION_TERMS_SEEDED, true);
        delete_option(self::OPTION_DO);
    }

    /** Inserta términos si no existen */
    private static function seed_taxonomy(string $taxonomy, array $terms): void
    {
        foreach ($terms as $name) {
            $slug = sanitize_title($name);
            if (! term_exists($slug, $taxonomy)) {
                wp_insert_term($name, $taxonomy, ['slug' => $slug]);
            }
        }
    }

    /** Limpia todo lo sembrado (tax terms y flag) */
    public static function run_on_deactivation(): void
    {
        foreach (['tipo_vehiculo', 'tipo_garantia', 'nivel_garantia'] as $tax) {
            $terms = get_terms([
                'taxonomy'   => $tax,
                'hide_empty' => false,
                'fields'     => 'ids',
            ]);
            if (! is_wp_error($terms)) {
                foreach ($terms as $tid) {
                    wp_delete_term((int) $tid, $tax);
                }
            }
        }
        delete_option(self::OPTION_TERMS_SEEDED);
    }
}
