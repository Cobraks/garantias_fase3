<?php
if (! defined('ABSPATH')) {
    exit;
}

$initiator = $context['initiator'] ?? [];
$initiator_name = $initiator['name'] ?? '';
$plate_label = $guarantee['plate'] ?? '#' . ($guarantee['id'] ?? '');
$payment_slug = $guarantee['payment_slug'] ?? '';
$vendor_data = $guarantee['vendor'] ?? [];
$vendor_company = $vendor_data['company_name'] ?? ($vendor_data['name'] ?? '');
$vendor_personal = $vendor_data['personal_name'] ?? '';
$vendor = $vendor_company !== '' ? $vendor_company : ($vendor_personal !== '' ? $vendor_personal : '');
$deadline = $guarantee['payment_deadline']['formatted'] ?? '';
$copy = $context['email_copy'] ?? [];
$admin_intro = $copy['admin_intro'] ?? '';
$signature = $copy['signature'] ?? '';
$is_domiciliation = in_array($payment_slug, ['domiciliacion', 'domiciliacion_bancaria', 'domiciliacion-bancaria'], true);
$is_transfer = in_array($payment_slug, ['transferencia', 'transferencia_bancaria'], true);
$vendor_display = $vendor !== '' ? $vendor : __('El profesional', 'garantias-online-360vo');
$heading = $is_domiciliation
    ? __('Garantía activada para %s', 'garantias-online-360vo')
    : __('Garantía contratada para %s', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Garantía activada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#e2001b; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Nueva garantía', 'garantias-online-360vo'); ?>
        </div>
        <?php if ($admin_intro !== '') : ?>
            <div style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php echo wpautop($admin_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
        <h1 style="font-size:22px; margin:0 0 12px; color:#111;">
            <?php
            printf(
                esc_html($heading),
                esc_html($plate_label)
            );
            ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
            <?php if ($is_domiciliation) : ?>
                <?php
                printf(
                    /* translators: %s: professional name */
                    esc_html__('%s ha contratado la garantía mediante Domiciliación bancaria. Recuerda realizar el cobro y marcar la garantía como pagada desde el enlace de la garantía.', 'garantias-online-360vo'),
                    '<strong style="color:#111;">' . esc_html($vendor_display) . '</strong>'
                );
                ?>
            <?php elseif ($is_transfer) : ?>
                <?php
                $deadline_text = $deadline !== ''
                    ? sprintf(
                        /* translators: 1: professional name, 2: deadline */
                        esc_html__('%1$s tiene hasta el %2$s para realizar la transferencia.', 'garantias-online-360vo'),
                        '<strong style="color:#111;">' . esc_html($vendor_display) . '</strong>',
                        '<strong style="color:#111;">' . esc_html($deadline) . '</strong>'
                    )
                    : '';
                $prefix = esc_html__('La contratación se ha completado y se encuentra pendiente de pago.', 'garantias-online-360vo');
                echo $deadline_text !== ''
                    ? $prefix . ' ' . $deadline_text
                    : $prefix;
                ?>
            <?php else : ?>
                <?php if ($initiator_name) : ?>
                    <?php
                    printf(
                        /* translators: %s: user name */
                        esc_html__('El usuario %s ha completado la contratación.', 'garantias-online-360vo'),
                        '<strong style="color:#111;">' . esc_html($initiator_name) . '</strong>'
                    );
                    ?>
                <?php else : ?>
                    <?php esc_html_e('La contratación se ha completado correctamente.', 'garantias-online-360vo'); ?>
                <?php endif; ?>
            <?php endif; ?>
        </p>
        <?php include __DIR__ . '/partials/summary.php'; ?>
        <p style="margin-top:24px; font-size:13px; color:#777; line-height:1.4;">
            <?php esc_html_e('Puedes acceder al expediente completo desde Mis Garantías.', 'garantias-online-360vo'); ?>
        </p>
        <?php if ($signature !== '') : ?>
            <div style="margin-top:24px; font-size:13px; color:#444; line-height:1.5;">
                <?php echo wpautop($signature); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#999;">
        <?php esc_html_e('Este mensaje ha sido generado automáticamente por 360VO Garantías Online.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
