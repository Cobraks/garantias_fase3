<?php
if (! defined('ABSPATH')) {
    exit;
}

$initiator = $context['initiator'] ?? [];
$initiator_name = $initiator['name'] ?? '';
$reason_label = $context['reason_label'] ?? '';
$plate_label = $guarantee['plate'] ?? '#' . ($guarantee['id'] ?? '');
$copy = $context['email_copy'] ?? [];
$admin_intro = $copy['admin_intro'] ?? '';
$signature = $copy['signature'] ?? '';
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
$cancellation = $guarantee['cancellation'] ?? [];
$cancellation_date = $cancellation['date'] ?? '';
$vendor_data = $guarantee['vendor'] ?? [];
$vendor_name = $vendor_data['company_name'] ?? ($vendor_data['personal_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Garantía cancelada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Cancelación', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <?php if ($admin_intro !== '') : ?>
                                <div style="font-size:15px;margin:0 0 20px;color:#374151;line-height:1.6;">
                                    <?php echo wpautop($admin_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php printf(esc_html__('Garantía %s cancelada', 'garantias-online-360vo'), esc_html($plate_label)); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php
                                $reason_text = $reason_label !== '' ? $reason_label : ($cancellation['reason_label'] ?? '');
                                $line = $reason_text !== ''
                                    ? sprintf(
                                        /* translators: 1: plate, 2: reason */
                                        esc_html__('Se ha cancelado la garantía %1$s por %2$s.', 'garantias-online-360vo'),
                                        esc_html($plate_label),
                                        '<strong style="color:#111827;">' . esc_html($reason_text) . '</strong>'
                                    )
                                    : sprintf(
                                        esc_html__('Se ha cancelado la garantía %s.', 'garantias-online-360vo'),
                                        esc_html($plate_label)
                                    );
                                echo wp_kses_post($line);
                                ?>
                            </p>
                            <ul style="list-style:none;padding:0;margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php if ($vendor_name !== '') : ?>
                                    <li><strong><?php esc_html_e('Vendedor:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($vendor_name); ?></li>
                                <?php endif; ?>
                                <?php if ($cancellation_date !== '') : ?>
                                    <li><strong><?php esc_html_e('Fecha de cancelación:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($cancellation_date); ?></li>
                                <?php endif; ?>
                                <?php if ($initiator_name !== '') : ?>
                                    <li><strong><?php esc_html_e('Cancelada por:', 'garantias-online-360vo'); ?></strong> <?php echo esc_html($initiator_name); ?></li>
                                <?php endif; ?>
                            </ul>
                            <p style="margin:24px 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Puedes revisar el expediente completo desde Mis Garantías.', 'garantias-online-360vo'); ?>
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
