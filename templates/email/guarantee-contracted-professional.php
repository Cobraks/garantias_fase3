<?php
if (! defined('ABSPATH')) {
    exit;
}

$plate_label = $guarantee['plate'] ?? '#' . ($guarantee['id'] ?? '');
$payment_slug = $guarantee['payment_slug'] ?? '';
$deadline = $guarantee['payment_deadline']['formatted'] ?? '';
$transfer = $guarantee['transfer'] ?? [];
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
$is_domiciliation = in_array($payment_slug, ['domiciliacion', 'domiciliacion_bancaria', 'domiciliacion-bancaria'], true);
$is_transfer = in_array($payment_slug, ['transferencia', 'transferencia_bancaria'], true);
$heading = $is_domiciliation
    ? __('Garantía activada para %s', 'garantias-online-360vo')
    : __('Garantía contratada para %s', 'garantias-online-360vo');
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Confirmación de garantía', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Tu nueva cobertura', 'garantias-online-360vo');
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
                                    esc_html($heading),
                                    esc_html($plate_label)
                                );
                                ?>
                            </h1>
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
                                    /* translators: 1: professional name, 2: plan description, 3: optional vehicle sentence */
                                    wp_kses(
                                        __('Hola <strong>%1$s</strong>. Acabas de contratar %2$s%3$s. Aquí tienes un resumen rápido para que puedas revisar los detalles en cualquier momento.', 'garantias-online-360vo'),
                                        ['strong' => ['style' => []]]
                                    ),
                                    esc_html($vendor_greeting),
                                    wp_kses_post($plan_fragment),
                                    $vehicle_fragment
                                );
                                ?>
                            </p>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Desde tu área de Mis Garantías podrás descargar la documentación, revisar los datos y consultar el estado de cada cobertura contratada.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($is_domiciliation) : ?>
                                <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                    <?php esc_html_e('La contratación se ha completado y la garantía ya está activa. No necesitas hacer nada más para que el vehículo esté cubierto.', 'garantias-online-360vo'); ?>
                                </p>
                            <?php elseif ($is_transfer) : ?>
                                <div style="margin:0 0 20px;padding:18px 22px;background:#fdecec;border:1px solid #f5b3b5;border-radius:12px;color:#7a0f12;line-height:1.6;font-size:14px;">
                                    <strong style="display:block;margin-bottom:6px;font-size:15px;color:#bc0000;">¡Importante!</strong>
                                    <?php
                                    $transfer_notice = __('La contratación se ha completado pero todavía no está activada.', 'garantias-online-360vo');
                                    echo '<span style="display:block;margin-top:6px;color:#7a0f12;">' . esc_html($transfer_notice) . '</span>';

                                    if ($deadline !== '') {
                                        printf(
                                            '<span style="display:block;margin-top:4px;color:#7a0f12;">%s</span>',
                                            esc_html(
                                                sprintf(
                                                    /* translators: %s: payment deadline */
                                                    __('Recuerda realizar la transferencia antes del %s.', 'garantias-online-360vo'),
                                                    $deadline
                                                )
                                            )
                                        );
                                    } else {
                                        echo '<span style="display:block;margin-top:4px;color:#7a0f12;">' . esc_html__(
                                            'Recuerda realizar la transferencia lo antes posible para evitar incidencias.',
                                            'garantias-online-360vo'
                                        ) . '</span>';
                                    }
                                    ?>
                                </div>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 24px;">
                                    <tbody>
                                        <tr style="background:#f9fafb;">
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                                <?php esc_html_e('Concepto', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                                <?php echo esc_html($transfer['concept'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Cantidad', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($transfer['amount'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('IBAN', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($transfer['iban'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                    <?php esc_html_e('Hemos registrado la contratación correctamente.', 'garantias-online-360vo'); ?>
                                </p>
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
                                <?php esc_html_e('Si tienes alguna duda, puedes responder directamente a este correo o contactar con tu asesor de 360VO.', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">
                                <?php esc_html_e('¡Gracias por confiar en 360VO!', 'garantias-online-360vo'); ?>
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
