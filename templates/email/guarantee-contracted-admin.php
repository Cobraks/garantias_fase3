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
$is_correction_regeneration = ! empty($context['is_correction_regeneration']);
$vendor_display = $vendor !== '' ? $vendor : __('El profesional', 'garantias-online-360vo');
$heading = $is_correction_regeneration
    ? __('Certificado regenerado para %s', 'garantias-online-360vo')
    : ($is_domiciliation
    ? __('Garantía activada para %s', 'garantias-online-360vo')
    : __('Garantía contratada para %s', 'garantias-online-360vo'));
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Garantía activada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = $is_correction_regeneration
                                ? __('Certificado actualizado', 'garantias-online-360vo')
                                : __('Nueva garantía', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <?php if ($admin_intro !== '') : ?>
                                <div style="font-size:15px;margin:0 0 20px;color:#374151;line-height:1.6;">
                                    <?php echo wpautop($admin_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php
                                printf(
                                    esc_html($heading),
                                    esc_html($plate_label)
                                );
                                ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php if ($is_correction_regeneration) : ?>
                                    <?php
                                    printf(
                                        /* translators: 1: plate, 2: professional name */
                                        wp_kses(
                                            __('Se ha regenerado el certificado <strong style="color:#111827;">%1$s</strong> tras la corrección de datos de %2$s.', 'garantias-online-360vo'),
                                            ['strong' => ['style' => []]]
                                        ),
                                        esc_html($plate_label),
                                        '<strong style="color:#111827;">' . esc_html($vendor_display) . '</strong>'
                                    );
                                    ?>
                                <?php elseif ($is_domiciliation) : ?>
                                    <?php
                                    printf(
                                        /* translators: %s: professional name */
                                        wp_kses(
                                            __('%s ha contratado la garantía mediante Domiciliación bancaria. Recuerda realizar el cobro y marcar la garantía como pagada desde el enlace de la garantía.', 'garantias-online-360vo'),
                                            ['strong' => []]
                                        ),
                                        '<strong style="color:#111827;">' . esc_html($vendor_display) . '</strong>'
                                    );
                                    ?>
                                <?php elseif ($is_transfer) : ?>
                                    <?php
                                    $deadline_text = $deadline !== ''
                                        ? sprintf(
                                            /* translators: 1: professional name, 2: deadline */
                                            esc_html__('%1$s tiene hasta el %2$s para realizar la transferencia.', 'garantias-online-360vo'),
                                            '<strong style="color:#111827;">' . esc_html($vendor_display) . '</strong>',
                                            '<strong style="color:#111827;">' . esc_html($deadline) . '</strong>'
                                        )
                                        : '';
                                    $prefix = esc_html__('La contratación se ha completado y se encuentra pendiente de pago.', 'garantias-online-360vo');
                                    echo $deadline_text !== ''
                                        ? $prefix . ' ' . wp_kses_post($deadline_text)
                                        : $prefix;
                                    ?>
                                <?php else : ?>
                                    <?php if ($initiator_name) : ?>
                                        <?php
                                        printf(
                                            /* translators: %s: user name */
                                            esc_html__('El usuario %s ha completado la contratación.', 'garantias-online-360vo'),
                                            esc_html($initiator_name)
                                        );
                                        ?>
                                    <?php else : ?>
                                        <?php esc_html_e('La contratación se ha completado correctamente.', 'garantias-online-360vo'); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                            <?php
                            $summary_context = [
                                'show_vendor_channel'  => true,
                                'show_vendor_contact'  => true,
                                'show_vendor_company'  => true,
                                'vendor_company_label' => __('Empresa', 'garantias-online-360vo'),
                                'vendor_channel_show_company_inline' => false,
                            ];
                            include __DIR__ . '/partials/summary.php';
                            ?>
                            <p style="margin:24px 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Puedes acceder al expediente completo desde Mis Garantías.', 'garantias-online-360vo'); ?>
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
