<?php
use GarantiasOnline360VO\Notifications\Email\EmailSettings;

if (! defined('ABSPATH')) {
    exit;
}

$provided_signature = null;

if (isset($signature_html) && is_string($signature_html)) {
    $provided_signature = $signature_html;
} elseif (isset($signature) && is_string($signature)) {
    $provided_signature = $signature;
}

$resolved_signature = is_string($provided_signature) ? trim($provided_signature) : '';

if ($resolved_signature === '') {
    $fallback_signature = EmailSettings::getSignature();
    if (is_string($fallback_signature)) {
        $resolved_signature = trim($fallback_signature);
    }
}

if ($resolved_signature === '') {
    return;
}

$margin_top = '24px';
if (isset($signature_margin_top)) {
    $margin_top_candidate = is_numeric($signature_margin_top)
        ? (string) $signature_margin_top
        : (is_string($signature_margin_top) ? trim($signature_margin_top) : '');

    if ($margin_top_candidate !== '') {
        $margin_top = is_numeric($margin_top_candidate)
            ? $margin_top_candidate . 'px'
            : $margin_top_candidate;
    }
}
?>
<div style="margin-top:<?php echo esc_attr($margin_top); ?>;">
    <?php echo $resolved_signature; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<?php unset($signature_html, $resolved_signature, $provided_signature, $signature_margin_top, $margin_top); ?>
