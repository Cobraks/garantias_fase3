<?php

namespace GarantiasOnline360VO\Clients;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use GarantiasOnline360VO\GuaranteeCPT;
use GarantiasOnline360VO\Rest\ClientRestController;
use WP_User;
use WP_User_Query;
use function _n;
use function __;
use function add_action;
use function current_time;
use function delete_transient;
use function get_post_type;
use function get_transient;
use function get_user_by;
use function number_format_i18n;
use function set_transient;
use function wp_date;
use function wp_timezone;

if (! defined('ABSPATH')) {
    exit;
}

class ClientSummary
{
    private const TRANSIENT_KEY = 'go360_clients_summary';
    private const TRANSIENT_TTL = 300;
    private const GUARANTEE_CLIENT_META = 'garantia_contratada_concesionario_empresa_profesional';
    private const GUARANTEE_PRICE_META  = 'garantia_contratada_precio';
    private const GUARANTEE_STATUSES = ['publish', 'pending', 'future', 'draft'];

    private static bool $hooks_registered = false;

    public static function register_hooks(): void
    {
        if (self::$hooks_registered) {
            return;
        }

        self::$hooks_registered = true;

        add_action('user_register', [__CLASS__, 'flush_cache']);
        add_action('profile_update', [__CLASS__, 'flush_cache']);
        add_action('deleted_user', [__CLASS__, 'flush_cache']);
        add_action('save_post_' . GuaranteeCPT::POST_TYPE, [__CLASS__, 'flush_cache']);
        add_action('deleted_post', [__CLASS__, 'maybe_flush_on_post_delete'], 10, 1);
    }

    public static function flush_cache(): void
    {
        delete_transient(self::TRANSIENT_KEY);
    }

    public static function maybe_flush_on_post_delete(int $post_id): void
    {
        if (get_post_type($post_id) !== GuaranteeCPT::POST_TYPE) {
            return;
        }

        self::flush_cache();
    }

    public static function get_summary(): array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $data = self::build_summary();
        set_transient(self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL);

        return $data;
    }

    private static function build_summary(): array
    {
        $contexts = [];
        foreach (['year', 'month'] as $context_key) {
            $contexts[$context_key] = self::build_context_summary($context_key);
        }

        return [
            'default_context' => 'month',
            'generated_at'    => current_time('mysql'),
            'contexts'        => $contexts,
        ];
    }

    private static function build_context_summary(string $context): array
    {
        $period = self::get_period_bounds($context);
        $current = $period['current'];
        $previous = $period['previous'];

        $new_clients_current = self::count_clients_in_period($current['start'], $current['end']);
        $new_clients_previous = self::count_clients_in_period($previous['start'], $previous['end']);

        $active_clients_current = self::count_guarantee_clients_in_period($current['start'], $current['end']);
        $active_clients_previous = self::count_guarantee_clients_in_period($previous['start'], $previous['end']);

        $top_client = self::get_top_client_by_guarantees($current['start'], $current['end']);
        $top_amount_client = self::get_top_client_by_amount($current['start'], $current['end']);

        $sparkline = $context === 'year'
            ? self::build_monthly_sparkline($current['end'])
            : [];

        $trend_suffix = $context === 'year'
            ? __('vs año ant.', 'garantias-online-360vo')
            : __('vs mes ant.', 'garantias-online-360vo');

        return [
            'metrics'   => [
                [
                    'key'       => 'new_clients',
                    'label'     => __('Clientes nuevos', 'garantias-online-360vo'),
                    'value'     => $new_clients_current,
                    'formatted' => number_format_i18n($new_clients_current),
                    'sublabel'  => $context === 'year'
                        ? __('Altas en el año en curso', 'garantias-online-360vo')
                        : __('Altas en el mes actual', 'garantias-online-360vo'),
                    'trend'     => self::format_trend($new_clients_current, $new_clients_previous, $trend_suffix),
                ],
                [
                    'key'       => 'active_guarantees',
                    'label'     => __('Con nuevas garantías', 'garantias-online-360vo'),
                    'value'     => $active_clients_current,
                    'formatted' => number_format_i18n($active_clients_current),
                    'sublabel'  => $context === 'year'
                        ? __('Actividad de garantías confirmada', 'garantias-online-360vo')
                        : __('Garantías registradas este mes', 'garantias-online-360vo'),
                    'trend'     => self::format_trend($active_clients_current, $active_clients_previous, $trend_suffix),
                ],
            ],
            'spotlight' => [
                'title' => __('Clientes destacados', 'garantias-online-360vo'),
                'items' => [
                    [
                        'label' => __('Más garantías', 'garantias-online-360vo'),
                        'value' => $top_client['name'],
                        'count' => $top_client['count'],
                        'meta'  => $top_client['count'] > 0
                            ? sprintf(
                                _n('%s garantía', '%s garantías', $top_client['count'], 'garantias-online-360vo'),
                                number_format_i18n($top_client['count'])
                            )
                            : '',
                    ],
                    [
                        'label' => __('Mayor importe', 'garantias-online-360vo'),
                        'value' => $top_amount_client['name'],
                        'meta'  => $top_amount_client['amount'] > 0
                            ? sprintf('%s €', number_format_i18n($top_amount_client['amount'], 2))
                            : '',
                    ],
                ],
            ],
            'trendline' => $context === 'year'
                ? [
                    'title'  => __('Ritmo mensual', 'garantias-online-360vo'),
                    'points' => $sparkline,
                ]
                : [],
        ];
    }

    private static function get_period_bounds(string $context): array
    {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);

        if ($context === 'year') {
            $current_start = $now
                ->setDate((int) $now->format('Y'), 1, 1)
                ->setTime(0, 0, 0);
            $current_end = $now;
            $previous_start = $current_start->sub(new DateInterval('P1Y'));
            $days_elapsed = (int) $now->format('z');
            $previous_year_days = (int) $previous_start->format('L') === 1 ? 366 : 365;
            $span_days = min($days_elapsed + 1, $previous_year_days);
            $previous_end = $previous_start->add(new DateInterval('P' . max(0, $span_days - 1) . 'D'))
                ->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
        } else {
            $current_start = $now
                ->modify('first day of this month')
                ->setTime(0, 0, 0);
            $current_end = $now;
            $previous_start = $current_start->sub(new DateInterval('P1M'));
            $current_day = (int) $now->format('j');
            $previous_month_days = (int) $previous_start->format('t');
            $span_days = min($current_day, $previous_month_days);
            $previous_end = $previous_start->add(new DateInterval('P' . max(0, $span_days - 1) . 'D'))
                ->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
        }

        return [
            'current'  => ['start' => $current_start, 'end' => $current_end],
            'previous' => ['start' => $previous_start, 'end' => $previous_end],
        ];
    }

    private static function count_clients_in_period(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if ($end < $start) {
            return 0;
        }

        $query = new WP_User_Query([
            'role__in'    => ClientRestController::get_supported_roles(),
            'fields'      => 'ID',
            'number'      => 1,
            'paged'       => 1,
            'count_total' => true,
            'date_query'  => [
                [
                    'column'    => 'user_registered',
                    'after'     => self::format_gmt($start),
                    'before'    => self::format_gmt($end),
                    'inclusive' => true,
                ],
            ],
        ]);

        return (int) $query->get_total();
    }

    private static function count_guarantee_clients_in_period(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if ($end < $start) {
            return 0;
        }

        global $wpdb;
        if (! isset($wpdb->posts, $wpdb->postmeta)) {
            return 0;
        }

        $status_placeholders = implode(',', array_fill(0, count(self::GUARANTEE_STATUSES), '%s'));

        $sql = "
            SELECT COUNT(DISTINCT client_meta.meta_value) AS total
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS client_meta
                ON client_meta.post_id = posts.ID
                AND client_meta.meta_key = %s
            WHERE posts.post_type = %s
                AND posts.post_status IN ($status_placeholders)
                AND posts.post_date_gmt BETWEEN %s AND %s
        ";

        $params = array_merge(
            [self::GUARANTEE_CLIENT_META, GuaranteeCPT::POST_TYPE],
            self::GUARANTEE_STATUSES,
            [self::format_gmt($start), self::format_gmt($end)]
        );

        $total = $wpdb->get_var($wpdb->prepare($sql, $params));

        return $total ? (int) $total : 0;
    }

    private static function get_top_client_by_guarantees(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;
        if (! isset($wpdb->posts, $wpdb->postmeta)) {
            return self::empty_spotlight();
        }

        $status_placeholders = implode(',', array_fill(0, count(self::GUARANTEE_STATUSES), '%s'));
        $sql = "
            SELECT client_meta.meta_value AS client_id, COUNT(*) AS total
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS client_meta
                ON client_meta.post_id = posts.ID
                AND client_meta.meta_key = %s
            WHERE posts.post_type = %s
                AND posts.post_status IN ($status_placeholders)
                AND posts.post_date_gmt BETWEEN %s AND %s
            GROUP BY client_meta.meta_value
            ORDER BY total DESC
            LIMIT 1
        ";

        $params = array_merge(
            [self::GUARANTEE_CLIENT_META, GuaranteeCPT::POST_TYPE],
            self::GUARANTEE_STATUSES,
            [self::format_gmt($start), self::format_gmt($end)]
        );

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        if (! is_array($row) || empty($row['client_id'])) {
            return self::empty_spotlight();
        }

        $client_id = (int) $row['client_id'];

        return [
            'id'    => $client_id,
            'name'  => self::resolve_client_name($client_id),
            'count' => isset($row['total']) ? (int) $row['total'] : 0,
        ];
    }

    private static function get_top_client_by_amount(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        global $wpdb;
        if (! isset($wpdb->posts, $wpdb->postmeta)) {
            return self::empty_amount_spotlight();
        }

        $status_placeholders = implode(',', array_fill(0, count(self::GUARANTEE_STATUSES), '%s'));
        $sql = "
            SELECT client_meta.meta_value AS client_id,
                   SUM(
                        CASE
                            WHEN price.meta_value REGEXP '^-?[0-9]+(\\.[0-9]+)?$'
                                THEN CAST(price.meta_value AS DECIMAL(20,2))
                            ELSE 0
                        END
                   ) AS total_amount
            FROM {$wpdb->posts} AS posts
            INNER JOIN {$wpdb->postmeta} AS client_meta
                ON client_meta.post_id = posts.ID
                AND client_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} AS price
                ON price.post_id = posts.ID
                AND price.meta_key = %s
            WHERE posts.post_type = %s
                AND posts.post_status IN ($status_placeholders)
                AND posts.post_date_gmt BETWEEN %s AND %s
            GROUP BY client_meta.meta_value
            ORDER BY total_amount DESC
            LIMIT 1
        ";

        $params = array_merge(
            [self::GUARANTEE_CLIENT_META, self::GUARANTEE_PRICE_META, GuaranteeCPT::POST_TYPE],
            self::GUARANTEE_STATUSES,
            [self::format_gmt($start), self::format_gmt($end)]
        );

        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        if (! is_array($row) || empty($row['client_id'])) {
            return self::empty_amount_spotlight();
        }

        $client_id = (int) $row['client_id'];
        $amount = isset($row['total_amount']) ? (float) $row['total_amount'] : 0.0;

        return [
            'id'     => $client_id,
            'name'   => self::resolve_client_name($client_id),
            'amount' => $amount,
        ];
    }

    private static function build_monthly_sparkline(DateTimeImmutable $end): array
    {
        $points = [];
        $anchor = $end->modify('first day of this month')->setTime(0, 0, 0);

        for ($i = 5; $i >= 0; $i--) {
            $start = $anchor->sub(new DateInterval('P' . $i . 'M'));
            $start = $start->setTime(0, 0, 0);
            $segment_end = $start->modify('last day of this month')->setTime(23, 59, 59);
            if ($segment_end > $end) {
                $segment_end = $end;
            }
            $value = self::count_clients_in_period($start, $segment_end);
            $points[] = [
                'label'     => wp_date('M', $start->getTimestamp()),
                'value'     => $value,
                'formatted' => number_format_i18n($value),
            ];
        }

        return $points;
    }

    private static function build_daily_sparkline(DateTimeImmutable $end): array
    {
        $points = [];
        $anchor_end = $end->setTime(23, 59, 59);

        for ($i = 6; $i >= 0; $i--) {
            $segment_start = $anchor_end->sub(new DateInterval('P' . $i . 'D'))->setTime(0, 0, 0);
            $segment_end = $segment_start->setTime(23, 59, 59);
            $value = self::count_clients_in_period($segment_start, $segment_end);
            $points[] = [
                'label'     => wp_date('d M', $segment_start->getTimestamp()),
                'value'     => $value,
                'formatted' => number_format_i18n($value),
            ];
        }

        return $points;
    }

    private static function resolve_client_name(int $user_id): string
    {
        if ($user_id <= 0) {
            return __('Sin datos', 'garantias-online-360vo');
        }

        $user = get_user_by('id', $user_id);
        if (! $user instanceof WP_User) {
            return __('Sin datos', 'garantias-online-360vo');
        }

        return self::resolve_client_display_name($user);
    }

    private static function resolve_client_display_name(WP_User $user): string
    {
        $display = trim((string) $user->display_name);
        if ($display !== '') {
            return $display;
        }

        $name = trim($user->first_name . ' ' . $user->last_name);
        if ($name !== '') {
            return $name;
        }

        return $user->user_login;
    }

    private static function format_trend(int $current, int $previous, string $suffix): array
    {
        if ($previous <= 0) {
            if ($current <= 0) {
                return [
                    'direction' => 'neutral',
                    'label'     => '—',
                ];
            }

            return [
                'direction' => 'positive',
                'label'     => sprintf('+%d%% %s', 100, $suffix),
            ];
        }

        $difference = $current - $previous;
        if ($difference === 0) {
            return [
                'direction' => 'neutral',
                'label'     => '—',
            ];
        }

        $percent = round(($difference / $previous) * 100);
        $direction = $percent > 0 ? 'positive' : 'negative';
        $formatted = ($percent > 0 ? '+' : '') . $percent . '% ' . $suffix;

        return [
            'direction' => $direction,
            'label'     => $formatted,
        ];
    }

    private static function empty_spotlight(): array
    {
        return [
            'id'    => 0,
            'name'  => __('Sin datos', 'garantias-online-360vo'),
            'count' => 0,
        ];
    }

    private static function empty_amount_spotlight(): array
    {
        return [
            'id'     => 0,
            'name'   => __('Sin datos', 'garantias-online-360vo'),
            'amount' => 0.0,
        ];
    }

    private static function format_gmt(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
