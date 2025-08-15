<?php

namespace GarantiasOnline360VO\Logs;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeLog
{
    const TABLE = 'go_guarantee_logs';

    protected static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function create_table(): void
    {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            guarantee_id bigint(20) unsigned NOT NULL,
            event varchar(100) NOT NULL,
            timestamp datetime NOT NULL,
            details longtext,
            user_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY guarantee_id (guarantee_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function add_log(int $guarantee_id, string $event, $details = '', ?int $user_id = null): void
    {
        global $wpdb;
        $wpdb->insert(
            self::table_name(),
            [
                'guarantee_id' => $guarantee_id,
                'event'       => $event,
                'timestamp'   => current_time('mysql'),
                'details'     => maybe_serialize($details),
                'user_id'     => $user_id,
            ],
            ['%d', '%s', '%s', '%s', '%d']
        );
    }

    public static function get_logs(int $guarantee_id): array
    {
        global $wpdb;
        $table = self::table_name();
        $query = $wpdb->prepare(
            "SELECT guarantee_id, event, timestamp, details, user_id FROM {$table} WHERE guarantee_id = %d ORDER BY timestamp DESC",
            $guarantee_id
        );
        $results = $wpdb->get_results($query, ARRAY_A);
        if (! $results) {
            return [];
        }
        foreach ($results as &$row) {
            $row['details'] = maybe_unserialize($row['details']);
        }
        return $results;
    }
}
