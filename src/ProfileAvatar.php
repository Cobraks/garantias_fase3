<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class ProfileAvatar
{
    public static function init(): void
    {
        // Override avatar URL when WordPress generates it
        add_filter('get_avatar_url', [__CLASS__, 'maybe_override_avatar_url'], 10, 3);
    }

    /**
     * If this user has a custom ACF avatar (profile_image),
     * return that URL instead of Gravatar.
     *
     * @param string            $url         Original avatar URL.
     * @param int|string|object $id_or_email User ID, email or comment object.
     * @param array             $args        Arguments passed to get_avatar_url().
     * @return string
     */
    public static function maybe_override_avatar_url($url, $id_or_email, $args): string
    {
        // Determine user ID
        $user_id = false;
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && ! empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_string($id_or_email) && email_exists($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            $user_id = $user ? $user->ID : false;
        }

        // Only override if ACF is active and user has a profile_image set
        if ($user_id && function_exists('get_field')) {
            $avatar_id = get_field('profile_image', 'user_' . $user_id);
            if ($avatar_id) {
                // Determine requested size or fallback to 96px
                $size = ! empty($args['size']) ? (int) $args['size'] : 96;
                $custom_url = wp_get_attachment_image_url($avatar_id, [$size, $size]);
                if ($custom_url) {
                    return $custom_url;
                }
            }
        }

        // Otherwise return the original URL (Gravatar or default)
        return $url;
    }
}
