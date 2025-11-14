<?php
if (! defined('ABSPATH')) {
    exit;
}

$logo_url = isset($logo_url) && $logo_url !== ''
    ? $logo_url
    : plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);

$logo_url = set_url_scheme(apply_filters('go360/email/header_logo_url', $logo_url), 'https');

$badge_text = isset($badge_text) && $badge_text !== ''
    ? $badge_text
    : __('Tu nueva cobertura', 'garantias-online-360vo');

$badge_text = wp_strip_all_tags($badge_text);
$alt_text   = isset($logo_alt) && $logo_alt !== ''
    ? $logo_alt
    : __('360VO Garantías Online', 'garantias-online-360vo');
?>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 56px;border-bottom:2px solid rgba(0,0,0,0.12);">
    <tr>
        <td style="padding:0 0 16px; vertical-align:middle;">
            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($alt_text); ?>" style="display:block; width:164px; height:auto;">
        </td>
        <td style="padding:0 0 16px; text-align:right; vertical-align:middle;">
            <span style="display:inline-block; padding:8px 12px; background:black; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:4px;">
                <?php echo esc_html($badge_text); ?>
            </span>
        </td>
    </tr>
</table>
