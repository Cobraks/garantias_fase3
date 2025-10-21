<?php

namespace GarantiasOnline360VO\Notifications\Push;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

class PushTables
{
    public const SUBSCRIPTIONS_TABLE = 'go_push_subscriptions';
    public const NOTIFICATIONS_TABLE = 'go_push_notifications';

    private static bool $ensured = false;

    public static function ensure_tables(): void
    {
        if (self::$ensured) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $subscriptions_table = $wpdb->prefix . self::SUBSCRIPTIONS_TABLE;
        $notifications_table = $wpdb->prefix . self::NOTIFICATIONS_TABLE;

        $subscriptions_sql = "CREATE TABLE {$subscriptions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            public_key VARCHAR(255) NOT NULL,
            auth_token VARCHAR(255) NOT NULL,
            content_encoding VARCHAR(40) NOT NULL DEFAULT 'aes128gcm',
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_success_at DATETIME NULL,
            failure_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY endpoint_unique (endpoint(191)),
            KEY user_index (user_id)
        ) {$charset};";

        $notifications_sql = "CREATE TABLE {$notifications_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(191) NOT NULL,
            body TEXT NULL,
            icon VARCHAR(191) NULL,
            badge VARCHAR(191) NULL,
            actions TEXT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_read_index (user_id, is_read),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($subscriptions_sql);
        dbDelta($notifications_sql);

        self::$ensured = true;
    }
}
