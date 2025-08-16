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
    }

    /**
     * Registers custom post statuses for guarantees
     */
    public static function register_statuses(): void
    {
        foreach (self::STATUSES as $status => $label) {
            register_post_status($status, [
                'label'                     => _x($label, 'post status', 'garantias-online-360vo'),
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'post_type'                 => [GuaranteeCPT::POST_TYPE],
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
     * Append custom statuses to the status dropdown on the post edit screen
     */
    public static function append_statuses_to_dropdown(): void
    {
        global $post;
        if (! $post || $post->post_type !== GuaranteeCPT::POST_TYPE) {
            return;
        }
        $options = '';
        foreach (self::STATUSES as $status => $label) {
            $selected = $post->post_status === $status ? " selected='selected'" : '';
            $label    = esc_js($label);
            $options .= "<option value='{$status}'{$selected}>{$label}</option>";
        }
        echo "<script>jQuery(function($){var s=$('#post_status');if(s.length){s.append('{$options}');}});</script>";
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
