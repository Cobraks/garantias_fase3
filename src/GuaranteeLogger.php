<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeLogger
{
    const TABLE = 'guarantee_logs';

    public static function ensure_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            self::create_table();
        }
    }

    public static function create_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            guarantee_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY guarantee_id (guarantee_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log(int $user_id, int $guarantee_id, string $event_type, string $details = ''): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        self::ensure_table();

        $wpdb->insert(
            $table,
            [
                'guarantee_id' => $guarantee_id,
                'user_id'      => $user_id,
                'event_type'   => sanitize_text_field($event_type),
                'details'      => sanitize_textarea_field($details),
                'created_at'   => current_time('mysql', true),
            ],
            ['%d','%d','%s','%s','%s']
        );
    }

    public static function get_logs(array $args = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        self::ensure_table();
        $where = [];
        $params = [];

        if (! empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (! empty($args['guarantee_id'])) {
            $where[] = 'guarantee_id = %d';
            $params[] = (int) $args['guarantee_id'];
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $per_page = ! empty($args['per_page']) ? absint($args['per_page']) : 20;
        $page = ! empty($args['page']) ? max(1, absint($args['page'])) : 1;
        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared, ARRAY_A);
        if ($wpdb->last_error) {
            return [];
        }
        return $results ?: [];
    }
}
