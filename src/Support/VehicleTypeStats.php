<?php

namespace GarantiasOnline360VO\Support;

use GarantiasOnline360VO\GuaranteeCPT;

if (! defined('ABSPATH')) {
    exit;
}

class VehicleTypeStats
{
    private const CACHE_GROUP = 'go360_vehicle_type_stats';
    private const CACHE_KEY_COUNTS = 'counts_v1';

    /**
     * @return array<int,int> Mapa term_id => total garantías
     */
    public static function get_counts(): array
    {
        $cached = wp_cache_get(self::CACHE_KEY_COUNTS, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;
        $meta_key = 'datos_vehiculo_tipo_vehiculo';

        // Contamos garantías por tipo_vehiculo guardado en meta (term_id).
        // Excluimos trash/auto-draft para no sesgar el ranking visual.
        $sql = $wpdb->prepare(
            "SELECT pm.meta_value AS term_id, COUNT(DISTINCT p.ID) AS total
             FROM {$posts_table} p
             INNER JOIN {$postmeta_table} pm
                ON p.ID = pm.post_id
               AND pm.meta_key = %s
             WHERE p.post_type = %s
               AND p.post_status NOT IN ('trash', 'auto-draft')
               AND pm.meta_value REGEXP '^[0-9]+$'
             GROUP BY pm.meta_value",
            $meta_key,
            GuaranteeCPT::POST_TYPE
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $counts = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $term_id = isset($row['term_id']) ? (int) $row['term_id'] : 0;
                $total = isset($row['total']) ? (int) $row['total'] : 0;
                if ($term_id > 0 && $total >= 0) {
                    $counts[$term_id] = $total;
                }
            }
        }

        wp_cache_set(self::CACHE_KEY_COUNTS, $counts, self::CACHE_GROUP);

        return $counts;
    }

    /**
     * @return array<int,\WP_Term>
     */
    public static function get_ordered_terms(bool $hide_empty = false): array
    {
        $terms = get_terms([
            'taxonomy'   => 'tipo_vehiculo',
            'hide_empty' => $hide_empty,
        ]);

        if (! is_array($terms) || empty($terms)) {
            return [];
        }

        $counts = self::get_counts();

        usort($terms, static function ($a, $b) use ($counts): int {
            $a_id = ($a instanceof \WP_Term) ? (int) $a->term_id : 0;
            $b_id = ($b instanceof \WP_Term) ? (int) $b->term_id : 0;

            $a_count = $counts[$a_id] ?? 0;
            $b_count = $counts[$b_id] ?? 0;

            if ($a_count !== $b_count) {
                return $b_count <=> $a_count;
            }

            $a_name = ($a instanceof \WP_Term) ? (string) $a->name : '';
            $b_name = ($b instanceof \WP_Term) ? (string) $b->name : '';
            return strcasecmp($a_name, $b_name);
        });

        return $terms;
    }

    public static function invalidate_cache(): void
    {
        wp_cache_delete(self::CACHE_KEY_COUNTS, self::CACHE_GROUP);
    }
}
