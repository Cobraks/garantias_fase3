<?php

namespace GarantiasOnline360VO\Notifications\Push;

use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

class PushSubscriptionRepository
{
    public function __construct()
    {
        PushTables::ensure_tables();
    }

    public function upsert(int $user_id, array $subscription): bool
    {
        if ($user_id <= 0 || empty($subscription['endpoint'])) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;
        $endpoint = esc_url_raw((string) $subscription['endpoint']);
        $public_key = $this->normalize_key((string) ($subscription['publicKey'] ?? $subscription['p256dh'] ?? ''));
        $auth_token = $this->normalize_key((string) ($subscription['authToken'] ?? $subscription['auth'] ?? ''));
        $content_encoding = sanitize_key((string) ($subscription['contentEncoding'] ?? 'aes128gcm'));
        $user_agent = sanitize_text_field((string) ($subscription['userAgent'] ?? ''));

        if ($public_key === '' || $auth_token === '') {
            return false;
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE endpoint = %s",
                $endpoint
            ),
            ARRAY_A
        );

        $data = [
            'user_id'          => $user_id,
            'endpoint'         => $endpoint,
            'public_key'       => $public_key,
            'auth_token'       => $auth_token,
            'content_encoding' => $content_encoding !== '' ? $content_encoding : 'aes128gcm',
            'user_agent'       => $user_agent !== '' ? $user_agent : null,
            'updated_at'       => current_time('mysql'),
        ];

        if ($existing) {
            $updated = $wpdb->update(
                $table,
                $data,
                ['id' => (int) $existing['id']],
                ['%d','%s','%s','%s','%s','%s','%s'],
                ['%d']
            );

            return $updated !== false;
        }

        $data['created_at'] = current_time('mysql');

        $inserted = $wpdb->insert(
            $table,
            $data,
            ['%d','%s','%s','%s','%s','%s','%s','%s']
        );

        return $inserted !== false;
    }

    public function remove_by_endpoint(string $endpoint): bool
    {
        if ($endpoint === '') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;
        $deleted = $wpdb->delete($table, ['endpoint' => esc_url_raw($endpoint)], ['%s']);

        return $deleted !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_user_subscriptions(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;

        $records = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id),
            ARRAY_A
        ) ?: [];

        return $this->filter_valid_subscriptions($records);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_admin_subscriptions(): array
    {
        $admins = get_users([
            'role__in' => ['administrator'],
            'fields'   => 'ID',
        ]);

        if (empty($admins)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;
        $placeholders = implode(',', array_fill(0, count($admins), '%d'));

        $records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id IN ({$placeholders})",
                ...array_map('intval', $admins)
            ),
            ARRAY_A
        ) ?: [];

        return $this->filter_valid_subscriptions($records);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function filter_valid_subscriptions(array $records): array
    {
        if (empty($records)) {
            return [];
        }

        $valid = [];

        foreach ($records as $record) {
            $endpoint = isset($record['endpoint']) ? (string) $record['endpoint'] : '';
            $public = $this->normalize_key((string) ($record['public_key'] ?? ''));
            $auth = $this->normalize_key((string) ($record['auth_token'] ?? ''));

            if ($endpoint === '' || $public === '' || $auth === '') {
                if ($endpoint !== '') {
                    $this->remove_by_endpoint($endpoint);
                }
                continue;
            }

            $record['endpoint'] = $endpoint;
            $record['public_key'] = $public;
            $record['auth_token'] = $auth;

            $valid[] = $record;
        }

        return $valid;
    }

    public function mark_failure(string $endpoint): void
    {
        if ($endpoint === '') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET failure_count = failure_count + 1 WHERE endpoint = %s",
                esc_url_raw($endpoint)
            )
        );
    }

    public function mark_success(string $endpoint): void
    {
        if ($endpoint === '') {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . PushTables::SUBSCRIPTIONS_TABLE;
        $wpdb->update(
            $table,
            [
                'failure_count'  => 0,
                'last_success_at'=> current_time('mysql'),
            ],
            ['endpoint' => esc_url_raw($endpoint)],
            ['%d','%s'],
            ['%s']
        );
}

    private function normalize_key(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', $value);
        if (! is_string($normalized)) {
            return '';
        }

        $normalized = trim($normalized);
        if ($normalized === '') {
            return '';
        }

        if (! preg_match('/^[A-Za-z0-9\-_=+/]+$/', $normalized)) {
            return '';
        }

        $converted = strtr($normalized, '-_', '+/');
        $padding = strlen($converted) % 4;
        if ($padding) {
            $converted .= str_repeat('=', 4 - $padding);
        }

        if (base64_decode($converted, true) === false) {
            return '';
        }

        return $normalized;
    }
}
