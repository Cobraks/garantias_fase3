<?php
if (! defined('ABSPATH')) {
    exit;
}

$plate_label = $guarantee['plate'] ?? '#' . ($guarantee['id'] ?? '');
$copy = $context['email_copy'] ?? [];
$client_intro = $copy['client_intro'] ?? '';
$signature = $copy['signature'] ?? '';
$plan_name = $guarantee['plan'] ?? '';
$vehicle_sentence = $guarantee['vehicle']['sentence'] ?? '';
$vehicle_summary = $guarantee['vehicle']['summary'] ?? '';
$vendor_data = $guarantee['vendor'] ?? [];
$vendor_contact = $vendor_data['contact_name'] ?? ($vendor_data['personal_name'] ?? ($vendor_data['name'] ?? ''));
$vendor_company = $vendor_data['company_name'] ?? '';
$vendor_display = $vendor_company !== ''
    ? $vendor_company
    : ($vendor_contact !== '' ? $vendor_contact : __('Tu equipo', 'garantias-online-360vo'));
$vendor_greeting = $vendor_data['greeting_name'] ?? '';
if ($vendor_greeting === '') {
    $vendor_greeting = $vendor_contact !== '' ? $vendor_contact : $vendor_display;
}
$permalink = $guarantee['permalink'] ?? '';
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
                            $badge_text = __('Pago confirmado', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <?php if ($client_intro !== '') : ?>
                                <div style="font-size:15px;margin:0 0 20px;color:#374151;line-height:1.6;">
                                    <?php echo wpautop($client_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            <?php endif; ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php
                                printf(
                                    esc_html__('Garantía activada para %s', 'garantias-online-360vo'),
                                    esc_html($plate_label)
                                );
                                ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php
                                printf(
                                    /* translators: 1: professional name, 2: plate label */
                                    wp_kses(
                                        __('Hola <strong>%1$s</strong>. Hemos confirmado la transferencia y la garantía <strong>%2$s</strong> ya está activa.', 'garantias-online-360vo'),
                                        ['strong' => []]
                                    ),
                                    esc_html($vendor_greeting),
                                    esc_html($plate_label)
                                );
                                ?>
                            </p>
                            <?php
                            $plan_fragment = $plan_name !== ''
                                ? sprintf(
                                    /* translators: %s: plan name */
                                    __('una garantía %s', 'garantias-online-360vo'),
                                    '<strong style="color:#111827;">' . esc_html($plan_name) . '</strong>'
                                )
                                : __('una garantía', 'garantias-online-360vo');

                            $vehicle_fragment = '';
                            if ($vehicle_sentence !== '') {
                                $vehicle_fragment = ' ' . esc_html($vehicle_sentence);
                            } elseif ($vehicle_summary !== '') {
                                $vehicle_fragment = ' ' . sprintf(
                                    /* translators: %s: vehicle description */
                                    esc_html__('para %s', 'garantias-online-360vo'),
                                    esc_html($vehicle_summary)
                                );
                            }
                            ?>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php
                                printf(
                                    /* translators: 1: plan description, 2: optional vehicle fragment */
                                    wp_kses(
                                        __('Has activado %1$s%2$s. El vehículo queda cubierto desde este momento.', 'garantias-online-360vo'),
                                        ['strong' => ['style' => []]]
                                    ),
                                    wp_kses_post($plan_fragment),
                                    $vehicle_fragment
                                );
                                ?>
                            </p>
                            <p style="font-size:15px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Puedes acceder a tu área de Mis Garantías para descargar la documentación o revisar el estado de la cobertura cuando lo necesites.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($permalink !== '') : ?>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#bc0000;">
                                            <a href="<?php echo esc_url($permalink); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                                <?php esc_html_e('Abrir Mis Garantías', 'garantias-online-360vo'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>
                            <?php
                            $summary_context = [
                                'show_vendor_channel'  => false,
                                'show_vendor_contact'  => false,
                                'show_vendor_company'  => false,
                            ];
                            include __DIR__ . '/partials/summary.php';
                            ?>
                            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:24px 0 16px;">
                                <?php esc_html_e('Si necesitas realizar alguna gestión adicional, puedes responder directamente a este correo o contactar con tu asesor de 360VO.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php
                            $signature_html = $signature;
                            $signature_margin_top = '12px';
                            include __DIR__ . '/partials/signature.php';
                            ?>
                        </td>
                    </tr>
                </table>
                <p style="text-align:center;margin:16px 0 0;font-size:12px;color:#6b7280;">
                    <?php esc_html_e('Este mensaje ha sido generado automáticamente por 360VO Garantías Online.', 'garantias-online-360vo'); ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
