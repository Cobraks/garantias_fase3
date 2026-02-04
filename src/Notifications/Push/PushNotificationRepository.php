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
            'icon'       => $this->prepare_icon_value($payload['icon'] ?? null, $payload['icon_slug'] ?? null),
            'icon_slug'  => isset($payload['icon_slug']) ? sanitize_key((string) $payload['icon_slug']) : null,
            'badge'      => isset($payload['badge']) ? sanitize_text_field((string) $payload['badge']) : null,
            'tone'       => isset($payload['tone']) ? sanitize_key((string) $payload['tone']) : null,
            'actions'    => isset($payload['actions']) ? wp_json_encode($payload['actions']) : null,
            'meta'       => isset($payload['meta']) ? wp_json_encode($payload['meta']) : null,
            'link'       => isset($payload['link']) ? esc_url_raw((string) $payload['link']) : null,
            'is_read'    => empty($payload['is_read']) ? 0 : 1,
            'created_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert(
            $table,
            $record,
            ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s']
        );

        if ($inserted === false) {
            return null;
        }

        $notification_id = (int) $wpdb->insert_id;

        /**
         * Action fired after a push notification has been stored for later delivery.
         *
         * @param int                  $notification_id
         * @param int                  $user_id
         * @param array<string, mixed> $payload
         */
        do_action('go360/notifications/created', $notification_id, $user_id, $payload);

        return $notification_id;
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
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return $this->hydrate_rows($results);
    }

    /**
     * @param array<int, string> $icon_slugs
     * @return array<int, array<string, mixed>>
     */
    public function list_by_icons(int $user_id, array $icon_slugs, int $page = 1, int $per_page = 10): array
    {
        if ($user_id <= 0 || empty($icon_slugs)) {
            return [];
        }

        $normalized_icons = array_values(array_filter(array_map('sanitize_key', $icon_slugs)));
        if (empty($normalized_icons)) {
            return [];
        }

        $page = max(1, $page);
        $per_page = max(1, min(50, $per_page));
        $offset = ($page - 1) * $per_page;

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $placeholders = implode(',', array_fill(0, count($normalized_icons), '%s'));
        $sql = "SELECT * FROM {$table} WHERE user_id = %d AND icon_slug IN ($placeholders) ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
        $params = array_merge([$user_id], $normalized_icons, [$per_page, $offset]);

        $prepared = $wpdb->prepare($sql, $params);
        if ($prepared === false) {
            return [];
        }

        $results = $wpdb->get_results($prepared, ARRAY_A);

        return $this->hydrate_rows($results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_after(int $user_id, int $after_id, int $limit = 10): array
    {
        if ($user_id <= 0 || $after_id <= 0) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND id > %d ORDER BY id DESC LIMIT %d",
                $user_id,
                $after_id,
                $limit
            ),
            ARRAY_A
        );

        return $this->hydrate_rows($results);
    }

    public function latest_id(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::NOTIFICATIONS_TABLE;

        $latest = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(id) FROM {$table} WHERE user_id = %d",
                $user_id
            )
        );

        return (int) $latest;
    }

    /**
     * @param mixed $icon
     * @param mixed $icon_slug
     */
    private function prepare_icon_value($icon, $icon_slug): ?string
    {
        if (is_string($icon)) {
            $icon = trim($icon);
            if ($icon !== '') {
                if (strpos($icon, 'data:image/svg+xml') !== 0 && strlen($icon) <= 180) {
                    return sanitize_text_field($icon);
                }

                if (preg_match('/^[a-z0-9_-]+$/', $icon) === 1) {
                    $slug = sanitize_key($icon);
                    return $slug !== '' ? $slug : null;
                }
            }
        }

        if (is_string($icon_slug) && $icon_slug !== '') {
            $slug = sanitize_key($icon_slug);
            return $slug !== '' ? $slug : null;
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>>|null $results
     * @return array<int, array<string, mixed>>
     */
    private function hydrate_rows($results): array
    {
        if (empty($results) || ! is_array($results)) {
            return [];
        }

        return array_map(
            static function (array $row): array {
                $row['actions'] = $row['actions'] ? json_decode((string) $row['actions'], true) : [];
                if (! is_array($row['actions'])) {
                    $row['actions'] = [];
                }
                $row['meta'] = $row['meta'] ? json_decode((string) $row['meta'], true) : [];
                if (! is_array($row['meta'])) {
                    $row['meta'] = [];
                }

                return $row;
            },
            $results
        );
    }
}
