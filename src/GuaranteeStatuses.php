<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeStatuses
{
    /**
     * Custom post statuses for guarantees
     */
    public const STATUSES = [
        'pendiente_pago' => 'Pendiente de pago',
        'activada'       => 'Activada',
        'expira_pronto'  => 'Expira pronto',
        'expirada'       => 'Expirada',
    ];

    /** Hook registrations */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_statuses']);
        add_filter('display_post_states', [__CLASS__, 'display_post_states']);
        add_action('admin_footer-post.php', [__CLASS__, 'append_statuses_to_dropdown']);
        add_action('admin_footer-post-new.php', [__CLASS__, 'append_statuses_to_dropdown']);
        add_action('pre_get_posts', [__CLASS__, 'include_in_admin_all']);
    }

    /**
     * Registers custom post statuses for guarantees
     */
    public static function register_statuses(): void
    {
        foreach (self::STATUSES as $status => $label) {
            register_post_status($status, [
                'label'                     => _x($label, 'post status', 'garantias-online-360vo'),
                'public'                    => true,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'show_in_metabox_dropdown'  => true,
                'show_in_inline_dropdown'   => true,
                'label_count'               => _n_noop(
                    "$label <span class=\"count\">(%s)</span>",
                    "$label <span class=\"count\">(%s)</span>",
                    'garantias-online-360vo'
                ),
            ]);
        }
    }

    /**
     * Display readable post states in admin list
     */
    public static function display_post_states($states): array
    {
        global $post;
        if ($post->post_type !== GuaranteeCPT::POST_TYPE) {
            return $states;
        }
        $statuses = self::STATUSES;
        if (isset($statuses[$post->post_status])) {
            $states[$post->post_status] = $statuses[$post->post_status];
        }
        return $states;
    }

    /**
     * Include custom statuses in the "All" admin list view
     */
    public static function include_in_admin_all($query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ($post_type !== GuaranteeCPT::POST_TYPE) {
            return;
        }

        $status = $query->get('post_status');
        if (! $status || $status === 'any') {
            $query->set('post_status', self::all_with_default());
        }
    }

    /**
     * Append custom statuses to the status dropdown on the post edit screen
     */
    public static function append_statuses_to_dropdown(): void
    {
        global $post;
        if (! $post || $post->post_type !== GuaranteeCPT::POST_TYPE) {
            return;
        }
        $json_statuses = wp_json_encode(self::STATUSES);
        echo <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('post_status');
    if (!select) return;
    var statuses = {$json_statuses};
    Object.entries(statuses).forEach(function(entry) {
        var value = entry[0];
        var label = entry[1];
        var exists = Array.prototype.some.call(select.options, function(o){
            return o.value === value;
        });
        if (!exists) {
            select.add(new Option(label, value));
        }
    });
    var pending = select.querySelector('option[value="pending"]');
    if (pending) {
        pending.remove();
    }
    var current = document.getElementById('hidden_post_status');
    if (current) {
        select.value = current.value;
    }
});
</script>
JS;
    }

    /**
     * Return all registered custom status slugs
     */
    public static function get_slugs(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * Return list of slugs including draft and publish for queries
     */
    public static function all_with_default(): array
    {
        return array_merge(['draft','publish'], self::get_slugs());
    }
}
