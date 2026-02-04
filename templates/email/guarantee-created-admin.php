<?php
if (! defined('ABSPATH')) {
    exit;
}

$initiator = $context['initiator'] ?? [];
$initiator_name = $initiator['name'] ?? '';
$copy = $context['email_copy'] ?? [];
$admin_intro = $copy['admin_intro'] ?? '';
$signature = $copy['signature'] ?? '';
$headline = sprintf(
    /* translators: %s: vehicle plate */
    __('Se ha creado una garantía para %s', 'garantias-online-360vo'),
    esc_html($guarantee['plate'] ?? '#' . ($guarantee['id'] ?? ''))
);
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Nueva garantía registrada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php include __DIR__ . '/partials/header.php'; ?>
                            <?php if ($admin_intro !== '') : ?>
                                <div style="font-size:15px;margin:0 0 20px;color:#374151;line-height:1.6;">
                                    <?php echo wpautop($admin_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php echo esc_html($headline); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php if ($initiator_name) : ?>
                                    <?php
                                    printf(
                                        /* translators: %s: user name */
                                        esc_html__('El usuario %s ha iniciado una nueva garantía desde el asistente online.', 'garantias-online-360vo'),
                                        esc_html($initiator_name)
                                    );
                                    ?>
                                <?php else : ?>
                                    <?php esc_html_e('Se ha iniciado una nueva garantía desde el asistente online.', 'garantias-online-360vo'); ?>
                                <?php endif; ?>
                            </p>
                            <?php include __DIR__ . '/partials/summary.php'; ?>
                            <p style="margin:24px 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Recuerda completar la contratación para que podamos activar la cobertura.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php
                            $signature_html = $signature;
                            $signature_margin_top = '12px';
                            include __DIR__ . '/partials/signature.php';
                            ?>
                        </td>
                    </tr>
                </table>
                <p style="text-align:center;margin:16px 0 0;font-size:11px;color:#6b7280;padding-bottom:16px;">
                    <?php esc_html_e('Este mensaje ha sido generado automáticamente por 360VO Garantías Online.', 'garantias-online-360vo'); ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
