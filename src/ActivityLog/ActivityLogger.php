<?php

namespace GarantiasOnline360VO\ActivityLog;

use WP_Error;
use wpdb;

if (! defined('ABSPATH')) {
    exit;
}

class ActivityLogger
{
    public const TABLE = 'go_activity_log';
    private const OPTION_MIGRATED = 'go360_activity_migrated_v1';
    private static bool $ensured = false;
    private static bool $migrating = false;

    public static function ensure_table(): void
    {
        if (self::$ensured) {
            return;
        }

        self::$ensured = true;
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            self::create_table();
        }

        self::maybe_migrate_legacy();
    }

    public static function create_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(100) NOT NULL,
            event_category VARCHAR(60) NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            actor_id BIGINT UNSIGNED NULL,
            actor_role VARCHAR(60) NULL,
            actor_name VARCHAR(191) NULL,
            actor_email VARCHAR(191) NULL,
            target_type VARCHAR(60) NULL,
            target_id BIGINT UNSIGNED NULL,
            guarantee_id BIGINT UNSIGNED NULL,
            channel VARCHAR(60) NULL,
            vendor_id BIGINT UNSIGNED NULL,
            vendor_name VARCHAR(191) NULL,
            source VARCHAR(60) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            context LONGTEXT NULL,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            created_gmt DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY event_category (event_category),
            KEY created_at (created_at),
            KEY level (level),
            KEY actor (actor_id),
            KEY vendor (vendor_id),
            KEY guarantee (guarantee_id),
            KEY target (target_type, target_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Registra un evento en la tabla.
     *
     * @param string               $event_type
     * @param array<string, mixed> $data
     */
    public static function log(string $event_type, array $data = []): ?int
    {
        if ($event_type === '') {
            return null;
        }

        self::ensure_table();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $event_type = self::sanitize_event_type($event_type);
        $definition = EventCatalog::get($event_type);
        $event_category = sanitize_key($data['event_category'] ?? ($definition['category'] ?? 'system'));
        $level = sanitize_key($data['level'] ?? ($definition['level'] ?? 'info'));

        $actor = self::resolve_actor($data);
        $context = self::prepare_context($data['context'] ?? []);
        if (isset($data['details']) && $data['details'] !== '') {
            $context['details'] = (string) $data['details'];
        }

        $guarantee_id = isset($data['guarantee_id']) ? absint($data['guarantee_id']) : 0;
        $target_type = $data['target_type'] ?? ($guarantee_id ? 'guarantee' : '');
        $target_id = isset($data['target_id']) ? absint($data['target_id']) : ($target_type === 'guarantee' ? $guarantee_id : 0);
        $vendor_id = isset($data['vendor_id']) ? absint($data['vendor_id']) : 0;
        $vendor_name = $data['vendor_name'] ?? ($vendor_id ? self::resolve_user_name($vendor_id) : '');
        $channel = isset($data['channel']) ? sanitize_text_field((string) $data['channel']) : '';
        if ($channel === '' && isset($context['channel'])) {
            $channel = sanitize_text_field((string) $context['channel']);
        }

        $message = self::build_message($event_type, $definition, $context, $actor, $data);
        if (isset($data['message']) && $data['message'] !== '') {
            $message = wp_strip_all_tags((string) $data['message']);
        }

        $source = sanitize_text_field($data['source'] ?? self::detect_source());
        $ip_address = sanitize_text_field($data['ip_address'] ?? self::detect_ip());
        $user_agent = sanitize_text_field($data['user_agent'] ?? self::detect_user_agent());

        $created_gmt = isset($data['created_gmt']) ? (string) $data['created_gmt'] : current_time('mysql', true);
        if ($created_gmt === '') {
            $created_gmt = current_time('mysql', true);
        }
        $created_at = isset($data['created_at']) ? (string) $data['created_at'] : get_date_from_gmt($created_gmt);
        if ($created_at === '') {
            $created_at = current_time('mysql');
        }

        $record = [
            'event_type'     => $event_type,
            'event_category' => $event_category,
            'level'          => $level,
            'actor_id'       => $actor['id'] ?: null,
            'actor_role'     => $actor['role'] ?: null,
            'actor_name'     => $actor['name'] ?: null,
            'actor_email'    => $actor['email'] ?: null,
            'target_type'    => $target_type ? sanitize_key($target_type) : null,
            'target_id'      => $target_id ?: null,
            'guarantee_id'   => $guarantee_id ?: null,
            'channel'        => $channel !== '' ? $channel : null,
            'vendor_id'      => $vendor_id ?: null,
            'vendor_name'    => $vendor_name !== '' ? $vendor_name : null,
            'source'         => $source !== '' ? $source : null,
            'ip_address'     => $ip_address !== '' ? $ip_address : null,
            'user_agent'     => $user_agent !== '' ? $user_agent : null,
            'context'        => ! empty($context) ? wp_json_encode($context) : null,
            'message'        => $message !== '' ? $message : null,
            'created_at'     => $created_at,
            'created_gmt'    => $created_gmt,
        ];

        $formats = ['%s','%s','%s','%d','%s','%s','%s','%s','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'];
        $inserted = $wpdb->insert($table, $record, $formats);
        if ($inserted === false) {
            error_log('[GO360][ActivityLogger] Error insertando evento ' . $event_type . ': ' . $wpdb->last_error);
            return null;
        }

        $id = (int) $wpdb->insert_id;
        do_action('go360/activity/logged', $id, $event_type, $record, $data);

        return $id;
    }

    /**
     * Obtiene los eventos aplicando filtros y devolviendo metadatos.
     *
     * @param array<string, mixed> $args
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function query(array $args = []): array
    {
        self::ensure_table();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $defaults = [
            'page'        => 1,
            'per_page'    => 25,
            'search'      => '',
            'event_type'  => '',
            'category'    => '',
            'level'       => '',
            'actor_id'    => 0,
            'target_type' => '',
            'target_id'   => 0,
            'guarantee_id'=> 0,
            'channel'     => '',
            'vendor_id'   => 0,
            'date_from'   => '',
            'date_to'     => '',
            'order'       => 'DESC',
            'order_by'    => 'created_at',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if ($args['search'] !== '') {
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $where[] = '(message LIKE %s OR context LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($args['event_type'] !== '') {
            $where[] = 'event_type = %s';
            $params[] = self::sanitize_event_type((string) $args['event_type']);
        }
        if ($args['category'] !== '') {
            $where[] = 'event_category = %s';
            $params[] = sanitize_key((string) $args['category']);
        }
        if ($args['level'] !== '') {
            $where[] = 'level = %s';
            $params[] = sanitize_key((string) $args['level']);
        }
        if ($args['actor_id']) {
            $where[] = '(actor_id = %d OR vendor_id = %d)';
            $params[] = absint($args['actor_id']);
            $params[] = absint($args['actor_id']);
        }
        if ($args['target_type'] !== '') {
            $where[] = 'target_type = %s';
            $params[] = sanitize_key((string) $args['target_type']);
        }
        if ($args['target_id']) {
            $where[] = 'target_id = %d';
            $params[] = absint($args['target_id']);
        }
        if ($args['guarantee_id']) {
            $where[] = 'guarantee_id = %d';
            $params[] = absint($args['guarantee_id']);
        }
        if ($args['channel'] !== '') {
            $where[] = 'channel = %s';
            $params[] = sanitize_text_field((string) $args['channel']);
        }
        if ($args['vendor_id']) {
            $where[] = 'vendor_id = %d';
            $params[] = absint($args['vendor_id']);
        }
        if ($args['date_from'] !== '') {
            $where[] = 'created_at >= %s';
            $params[] = (string) $args['date_from'];
        }
        if ($args['date_to'] !== '') {
            $where[] = 'created_at <= %s';
            $params[] = (string) $args['date_to'];
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $order_by_whitelist = ['created_at', 'event_type', 'level'];
        $order_by = in_array($args['order_by'], $order_by_whitelist, true) ? $args['order_by'] : 'created_at';
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $per_page = max(1, min(100, absint($args['per_page'])));
        $page = max(1, absint($args['page']));
        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM $table $where_sql ORDER BY $order_by $order LIMIT %d OFFSET %d";
        $query_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $query_params), ARRAY_A);
        if (! is_array($rows)) {
            $rows = [];
        }

        $count_sql = "SELECT COUNT(*) FROM $table $where_sql";
        if (! empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = (int) $wpdb->get_var($count_sql);
        $total_pages = $per_page ? (int) ceil($total / $per_page) : 1;

        $data = [];
        foreach ($rows as $row) {
            $data[] = self::normalize_row($row);
        }

        $meta = [
            'total'        => $total,
            'total_pages'  => $total_pages,
            'page'         => $page,
            'per_page'     => $per_page,
            'event_types'  => EventCatalog::event_options(),
            'categories'   => EventCatalog::category_options(),
            'channels'     => self::get_distinct_values('channel'),
            'levels'       => self::get_levels(),
            'vendors'      => self::get_vendor_options(),
        ];

        return [
            'data' => $data,
            'meta' => $meta,
        ];
    }

    private static function normalize_row(array $row): array
    {
        $event = EventCatalog::get($row['event_type']);
        $context = [];
        if (! empty($row['context'])) {
            $decoded = json_decode((string) $row['context'], true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        $created_gmt = $row['created_gmt'] ?? $row['created_at'];
        $created_iso = mysql_to_rfc3339($created_gmt);

        $event_label = $event['label'] ?? $row['event_type'];
        $category_label = $event['category_label'] ?? '';
        if (isset($context['event_label']) && is_string($context['event_label']) && $context['event_label'] !== '') {
            $event_label = $context['event_label'];
            unset($context['event_label']);
        }

        $message = $row['message'];
        if ($message === '' && isset($context['message']) && is_string($context['message'])) {
            $message = $context['message'];
            unset($context['message']);
        }

        $normalized = [
            'id'             => (int) $row['id'],
            'event_type'     => $row['event_type'],
            'event_label'    => $event_label,
            'event_category' => $row['event_category'],
            'event_category_label' => $category_label !== '' ? $category_label : self::resolve_category_label($row['event_category']),
            'level'          => $row['level'],
            'message'        => $message,
            'created_at'     => $row['created_at'],
            'created_gmt'    => $row['created_gmt'],
            'created_iso'    => $created_iso,
            'actor'          => [
                'id'    => (int) $row['actor_id'],
                'name'  => $row['actor_name'],
                'email' => $row['actor_email'],
                'role'  => $row['actor_role'],
            ],
            'target'         => [
                'type' => $row['target_type'],
                'id'   => (int) $row['target_id'],
            ],
            'guarantee_id'   => (int) $row['guarantee_id'],
            'channel'        => $row['channel'],
            'vendor'         => [
                'id'   => (int) $row['vendor_id'],
                'name' => $row['vendor_name'],
            ],
            'source'         => $row['source'],
            'ip_address'     => $row['ip_address'],
            'user_agent'     => $row['user_agent'],
            'context'        => $context,
        ];

        $normalized['presentation'] = ActivityPresenter::format($normalized);

        return $normalized;
    }

    private static function resolve_actor(array $data): array
    {
        $actor_id = isset($data['actor_id']) && $data['actor_id'] ? absint($data['actor_id']) : get_current_user_id();
        $actor_name = $data['actor_name'] ?? '';
        $actor_email = $data['actor_email'] ?? '';
        $actor_role = $data['actor_role'] ?? '';

        if ($actor_id) {
            $user = get_userdata($actor_id);
            if ($user) {
                if ($actor_name === '') {
                    $actor_name = $user->display_name ?: $user->user_login;
                }
                if ($actor_email === '') {
                    $actor_email = $user->user_email;
                }
                if ($actor_role === '' && ! empty($user->roles)) {
                    $actor_role = implode(',', array_map('sanitize_key', $user->roles));
                }
            }
        }

        return [
            'id'    => $actor_id,
            'name'  => $actor_name,
            'email' => $actor_email,
            'role'  => $actor_role,
        ];
    }

    private static function resolve_user_name(int $user_id): string
    {
        if (! $user_id) {
            return '';
        }
        $user = get_userdata($user_id);
        if (! $user) {
            return '';
        }
        return $user->display_name ?: $user->user_login;
    }

    private static function prepare_context($context): array
    {
        if (! is_array($context)) {
            return [];
        }
        $sanitized = [];
        foreach ($context as $key => $value) {
            $key = is_string($key) ? sanitize_key($key) : (string) $key;
            if ($key === '') {
                $key = 'meta_' . substr(md5((string) $value), 0, 6);
            }
            if (is_scalar($value)) {
                $sanitized[$key] = wp_strip_all_tags((string) $value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::prepare_context($value);
            }
        }
        return $sanitized;
    }

    private static function build_message(string $event_type, array $definition, array $context, array $actor, array $data): string
    {
        $template = $definition['message'] ?? '';
        if ($template === '') {
            $template = $definition['label'] ?? $event_type;
        }

        $replacements = array_merge(
            [
                '{{actor_name}}'  => $actor['name'] ?: __('Usuario sin identificar', 'garantias-online-360vo'),
                '{{actor_email}}' => $actor['email'] ?: '',
            ],
            self::flatten_context($context)
        );

        $replacements['{{message}}'] = $data['message'] ?? '';

        return wp_strip_all_tags(strtr($template, $replacements));
    }

    private static function flatten_context(array $context): array
    {
        $flat = [];
        foreach ($context as $key => $value) {
            $placeholder = '{{context.' . $key . '}}';
            if (is_scalar($value)) {
                $flat[$placeholder] = (string) $value;
            } elseif (is_array($value)) {
                foreach (self::flatten_context($value) as $child_key => $child_value) {
                    $flat[$child_key] = $child_value;
                }
            }
        }

        if (isset($context['guarantee_label'])) {
            $flat['{{context.guarantee_label}}'] = (string) $context['guarantee_label'];
        } elseif (isset($context['guarantee_id'])) {
            $flat['{{context.guarantee_label}}'] = '#' . $context['guarantee_id'];
        }

        return $flat;
    }

    private static function resolve_category_label(string $category): string
    {
        $category = sanitize_key($category);
        if ($category === '') {
            return '';
        }

        $catalog = EventCatalog::categories();
        if (isset($catalog[$category])) {
            return $catalog[$category];
        }

        $category = str_replace(['_', '-'], ' ', $category);
        return ucfirst($category);
    }

    private static function detect_source(): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            return 'cli';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'rest';
        }
        if (wp_doing_cron()) {
            return 'cron';
        }
        return 'web';
    }

    private static function detect_ip(): string
    {
        if (! empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return '';
    }

    private static function detect_user_agent(): string
    {
        if (! empty($_SERVER['HTTP_USER_AGENT'])) {
            return substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255);
        }
        return '';
    }

    private static function get_distinct_values(string $column): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $column = sanitize_key($column);
        $values = $wpdb->get_col("SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column ASC");
        if (! is_array($values)) {
            return [];
        }
        return array_map('strval', $values);
    }

    private static function get_levels(): array
    {
        return [
            ['value' => 'info', 'label' => __('Información', 'garantias-online-360vo')],
            ['value' => 'warning', 'label' => __('Aviso', 'garantias-online-360vo')],
            ['value' => 'error', 'label' => __('Error', 'garantias-online-360vo')],
        ];
    }

    private static function get_vendor_options(): array
    {
        $cache_key = 'go360_activity_vendors';
        $cached = wp_cache_get($cache_key, 'go360');
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $roles = ['go_profesional', 'go_gestoria', 'go_comercial'];
        $users = get_users([
            'role__in' => $roles,
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 200,
            'fields'   => ['ID', 'display_name', 'user_email', 'user_login'],
        ]);

        $options = [];
        foreach ($users as $user) {
            $label = $user->display_name ?: ($user->user_email ?: $user->user_login);
            $options[] = [
                'value' => (string) $user->ID,
                'label' => $label,
            ];
        }

        wp_cache_set($cache_key, $options, 'go360', 5 * MINUTE_IN_SECONDS);

        return $options;
    }

    private static function sanitize_event_type(string $value): string
    {
        $value = strtolower(str_replace(':', '.', $value));
        return strtolower(preg_replace('/[^a-z0-9\._]/i', '_', $value));
    }

    private static function maybe_migrate_legacy(): void
    {
        if (self::$migrating || get_option(self::OPTION_MIGRATED)) {
            return;
        }

        global $wpdb;
        $legacy_table = $wpdb->prefix . 'guarantee_logs';
        $new_table = $wpdb->prefix . self::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
        if ($exists !== $legacy_table) {
            update_option(self::OPTION_MIGRATED, 'missing');
            return;
        }

        $has_new_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM $new_table");
        if ($has_new_rows > 0) {
            update_option(self::OPTION_MIGRATED, 'already');
            return;
        }

        self::$migrating = true;
        $rows = $wpdb->get_results("SELECT * FROM $legacy_table ORDER BY id ASC", ARRAY_A);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $mapped = self::map_legacy_event((array) $row);
                if (! $mapped) {
                    continue;
                }
                self::log($mapped['event_type'], $mapped['data']);
            }
        }
        self::$migrating = false;

        update_option(self::OPTION_MIGRATED, gmdate('c'));
    }

    private static function map_legacy_event(array $row): ?array
    {
        if (empty($row['event_type'])) {
            return null;
        }
        $legacy_type = (string) $row['event_type'];
        $map = [
            'created'            => 'guarantee.created',
            'updated'            => 'guarantee.updated',
            'status_changed'     => 'guarantee.status_changed',
            'document_downloaded'=> 'document.downloaded',
            'document_uploaded'  => 'document.uploaded',
            'email_sent'         => 'email.sent',
            'email_failed'       => 'email.failed',
            'email_skipped'      => 'email.skipped',
        ];
        $event_type = $map[$legacy_type] ?? 'guarantee.updated';
        $details = isset($row['details']) ? (string) $row['details'] : '';
        $context = [
            'legacy_event'   => $legacy_type,
            'legacy_details' => $details,
            'guarantee_id'   => isset($row['guarantee_id']) ? absint($row['guarantee_id']) : 0,
        ];

        return [
            'event_type' => $event_type,
            'data'       => [
                'actor_id'     => isset($row['user_id']) ? absint($row['user_id']) : 0,
                'guarantee_id' => isset($row['guarantee_id']) ? absint($row['guarantee_id']) : 0,
                'target_type'  => 'guarantee',
                'target_id'    => isset($row['guarantee_id']) ? absint($row['guarantee_id']) : 0,
                'message'      => $details,
                'context'      => $context,
                'created_at'   => isset($row['created_at']) ? (string) $row['created_at'] : null,
                'created_gmt'  => isset($row['created_at']) ? get_gmt_from_date($row['created_at']) : null,
            ],
        ];
    }
}
