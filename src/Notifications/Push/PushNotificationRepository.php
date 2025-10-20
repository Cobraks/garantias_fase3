<?php

namespace GarantiasOnline360VO\Notifications\Push;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

class PushNotificationRepository
{
    public function __construct()
    {
        PushTables::ensure_tables();
    }

    public function create(int $user_id, array $payload): ?int
    {
        if ($user_id <= 0 || empty($payload['title'])) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $record = [
            'user_id'    => $user_id,
            'title'      => sanitize_text_field((string) $payload['title']),
            'body'       => isset($payload['body']) ? wp_kses_post((string) $payload['body']) : null,
            'icon'       => isset($payload['icon']) ? esc_url_raw((string) $payload['icon']) : null,
            'badge'      => isset($payload['badge']) ? esc_url_raw((string) $payload['badge']) : null,
            'actions'    => isset($payload['actions']) ? wp_json_encode($payload['actions']) : null,
            'link'       => isset($payload['link']) ? esc_url_raw((string) $payload['link']) : null,
            'is_read'    => empty($payload['is_read']) ? 0 : 1,
            'created_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert(
            $table,
            $record,
            ['%d','%s','%s','%s','%s','%s','%s','%d','%s']
        );

        if ($inserted === false) {
            return null;
        }

        return (int) $wpdb->insert_id;
    }

    public function mark_read(int $notification_id, int $user_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $updated = $wpdb->update(
            $table,
            ['is_read' => 1],
            [
                'id'      => $notification_id,
                'user_id' => $user_id,
            ],
            ['%d'],
            ['%d','%d']
        );

        return $updated !== false;
    }

    public function mark_all_read(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;
        $wpdb->update($table, ['is_read' => 1], ['user_id' => $user_id], ['%d'], ['%d']);
    }

    public function delete(int $notification_id, int $user_id): void
    {
        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;
        $wpdb->delete($table, ['id' => $notification_id, 'user_id' => $user_id], ['%d','%d']);
    }

    public function count_unread(int $user_id): int
    {
        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );

        return (int) $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $user_id, int $page = 1, int $per_page = 10): array
    {
        if ($user_id <= 0) {
            return [];
        }

        $page = max(1, $page);
        $per_page = max(1, min(50, $per_page));
        $offset = ($page - 1) * $per_page;

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['actions'] = $row['actions'] ? json_decode((string) $row['actions'], true) : [];
                return $row;
            },
            $results
        );
    }
}
