<?php
if (! defined('ABSPATH')) {
    exit;
}

$name       = isset($name) && $name !== '' ? $name : __('Profesional', 'garantias-online-360vo');
$code       = isset($code) ? (string) $code : '';
$expires_at = isset($expires_at) ? (string) $expires_at : '';
$expires_in = isset($expires_in) ? (int) $expires_in : 0;
$url        = isset($verification_url) && $verification_url !== ''
    ? $verification_url
    : home_url('/garantias-online/registro/');

$deadline_sentence = '';
if ($expires_in > 0) {
    $hours = max(1, floor($expires_in / HOUR_IN_SECONDS));
    $deadline_sentence = sprintf(
        /* translators: %d: number of hours */
        __('El código caduca en %d horas.', 'garantias-online-360vo'),
        $hours
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Verificación de cuenta', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#1d4ed8; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Verificación requerida', 'garantias-online-360vo'); ?>
        </div>
        <h1 style="font-size:22px; margin:0 0 12px; color:#111;">
            <?php esc_html_e('Confirma tu correo en Garantías Online', 'garantias-online-360vo'); ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
            <?php
            printf(
                /* translators: %s: recipient name */
                esc_html__('Hola %s,', 'garantias-online-360vo'),
                esc_html($name)
            );
            ?>
        </p>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
            <?php esc_html_e('Para activar tu cuenta introduce el siguiente código en el paso final del registro.', 'garantias-online-360vo'); ?>
        </p>

        <div style="display:inline-block; padding:18px 32px; border:2px dashed #1d4ed8; border-radius:16px; margin:0 0 16px; font-size:32px; letter-spacing:0.3em; font-weight:700; color:#1d4ed8;">
            <?php echo esc_html($code); ?>
        </div>

        <?php if ($deadline_sentence !== '') : ?>
            <p style="font-size:14px; margin:0 0 16px; color:#1f2937; line-height:1.6;">
                <?php echo esc_html($deadline_sentence); ?>
            </p>
        <?php else : ?>
            <p style="font-size:14px; margin:0 0 16px; color:#1f2937; line-height:1.6;">
                <?php esc_html_e('El código caduca en 24 horas.', 'garantias-online-360vo'); ?>
            </p>
        <?php endif; ?>

        <p style="margin:0 0 24px;">
            <a href="<?php echo esc_url($url); ?>" style="display:inline-block; padding:14px 26px; background:#1d4ed8; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:999px;">
                <?php esc_html_e('Ir al paso de verificación', 'garantias-online-360vo'); ?>
            </a>
        </p>

        <p style="font-size:13px; margin:0 0 16px; color:#6b7280; line-height:1.6;">
            <?php esc_html_e('Si no has solicitado esta verificación puedes ignorar este mensaje.', 'garantias-online-360vo'); ?>
        </p>
        <p style="font-size:13px; margin:0; color:#6b7280; line-height:1.6;">
            <?php esc_html_e('Gracias por unirte a Garantías Online.', 'garantias-online-360vo'); ?>
        </p>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#9ca3af;">
        <?php esc_html_e('Este mensaje ha sido enviado automáticamente por Garantías Online 360VO.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
