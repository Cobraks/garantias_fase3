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
$signature  = isset($signature) ? (string) $signature : '';
$channel_key = isset($channel_key) ? (string) $channel_key : '';
$channel_label = isset($channel_label) ? (string) $channel_label : '';
$is_individual = $channel_key === 'individual';

$deadline_sentence = '';
if ($expires_in > 0) {
    $hours = max(1, floor($expires_in / HOUR_IN_SECONDS));
    $deadline_sentence = sprintf(
        /* translators: %d: number of hours */
        __('El código caduca en %d horas.', 'garantias-online-360vo'),
        $hours
    );
}
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Verificación de cuenta', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Verificación requerida', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Confirma tu correo en Garantías Online', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php
                                printf(
                                    /* translators: %s: recipient name */
                                    esc_html__('Hola %s,', 'garantias-online-360vo'),
                                    esc_html($name)
                                );
                                ?>
                            </p>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Para activar tu cuenta introduce el siguiente código en el paso final del registro.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($is_individual) : ?>
                                <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                    <?php esc_html_e('Como particular, podrás gestionar los pagos de tus garantías mediante transferencia bancaria. Tras activar tu cuenta te enviaremos las instrucciones para completar el ingreso.', 'garantias-online-360vo'); ?>
                                </p>
                            <?php endif; ?>
                            <div style="display:inline-block;padding:20px 36px;border:2px dashed #bc0000;border-radius:16px;margin:0 0 20px;font-size:32px;letter-spacing:0.3em;font-weight:700;color:#bc0000;">
                                <?php echo esc_html($code); ?>
                            </div>
                            <?php if ($deadline_sentence !== '') : ?>
                                <p style="font-size:14px;margin:0 0 18px;color:#4b5563;line-height:1.6;">
                                    <?php echo esc_html($deadline_sentence); ?>
                                </p>
                            <?php else : ?>
                                <p style="font-size:14px;margin:0 0 18px;color:#4b5563;line-height:1.6;">
                                    <?php esc_html_e('El código caduca en 24 horas.', 'garantias-online-360vo'); ?>
                                </p>
                            <?php endif; ?>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#bc0000;">
                                        <a href="<?php echo esc_url($url); ?>" style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Ir al paso de verificación', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:13px;margin:0 0 16px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Si no has solicitado esta verificación puedes ignorar este mensaje.', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="font-size:13px;margin:0;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Gracias por unirte a Garantías Online.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php
                            $signature_html = $signature;
                            include __DIR__ . '/partials/signature.php';
                            ?>
                        </td>
                    </tr>
                </table>
                <p style="text-align:center;margin:16px 0 0;font-size:12px;color:#6b7280;">
                    <?php esc_html_e('Este mensaje ha sido enviado automáticamente por Garantías Online 360VO.', 'garantias-online-360vo'); ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
