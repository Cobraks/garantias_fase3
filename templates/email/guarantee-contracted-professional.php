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
$vendor_name = $guarantee['vendor']['name'] ?? '';
$vendor_display = $vendor_name !== '' ? $vendor_name : __('Tu equipo', 'garantias-online-360vo');
$is_domiciliation = in_array($payment_slug, ['domiciliacion', 'domiciliacion_bancaria', 'domiciliacion-bancaria'], true);
$is_transfer = in_array($payment_slug, ['transferencia', 'transferencia_bancaria'], true);
$heading = $is_domiciliation
    ? __('Garantía activada para %s', 'garantias-online-360vo')
    : __('Garantía contratada para %s', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Confirmación de garantía', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#e2001b; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Nueva garantía', 'garantias-online-360vo'); ?>
        </div>
        <?php if ($client_intro !== '') : ?>
            <div style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php echo wpautop($client_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
            <?php
            $plan_fragment = $plan_name !== ''
                ? sprintf(
                    /* translators: %s: plan name */
                    __('una garantía %s', 'garantias-online-360vo'),
                    '<strong>' . esc_html($plan_name) . '</strong>'
                )
                : __('una garantía', 'garantias-online-360vo');

            $vehicle_fragment = $vehicle_sentence !== ''
                ? ' ' . esc_html($vehicle_sentence)
                : '';

            printf(
                /* translators: 1: professional name, 2: plan description, 3: optional vehicle sentence */
                wp_kses(
                    __('Hola <strong>%1$s</strong>. Acabas de contratar %2$s%3$s. Aquí tienes los datos de la cobertura. Recuerda que puedes consultar la documentación y toda la información necesaria en el área de Mis Garantías.', 'garantias-online-360vo'),
                    ['strong' => []]
                ),
                esc_html($vendor_display),
                wp_kses_post($plan_fragment),
                $vehicle_fragment
            );
            ?>
        </p>
        <?php if ($is_domiciliation) : ?>
            <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php esc_html_e('La contratación se ha completado y la garantía ya está activa.', 'garantias-online-360vo'); ?>
            </p>
        <?php elseif ($is_transfer) : ?>
            <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php esc_html_e('La contratación se ha completado pero todavía no está activada.', 'garantias-online-360vo'); ?>
            </p>
        <?php else : ?>
            <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php esc_html_e('Hemos registrado la contratación correctamente.', 'garantias-online-360vo'); ?>
            </p>
        <?php endif; ?>
        <?php if ($is_transfer) : ?>
            <div style="padding:14px 18px; margin:0 0 18px; background:#fff4d6; border:1px solid #f7ce68; border-radius:8px; font-size:14px; color:#8b6500; line-height:1.6;">
                <strong style="display:block; font-size:15px; color:#5c3d00; margin-bottom:4px;">¡Importante!</strong>
                <?php
                if ($deadline !== '') {
                    printf(
                        /* translators: %s: deadline date */
                        esc_html__('Recuerda realizar la transferencia antes del %s para activar la garantía.', 'garantias-online-360vo'),
                        esc_html($deadline)
                    );
                } else {
                    esc_html_e('Recuerda realizar la transferencia cuanto antes para activar la garantía.', 'garantias-online-360vo');
                }
                ?>
            </div>
            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #e5e5e5; border-radius:8px; overflow:hidden; margin:0 0 18px;">
                <tbody>
                    <tr style="background:#fafafa;">
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#777; letter-spacing:0.05em;">
                            <?php esc_html_e('Concepto', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; font-weight:600;">
                            <?php echo esc_html($transfer['concept'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#777; letter-spacing:0.05em; border-top:1px solid #e5e5e5;">
                            <?php esc_html_e('Cantidad', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e5e5;">
                            <?php echo esc_html($transfer['amount'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#777; letter-spacing:0.05em; border-top:1px solid #e5e5e5;">
                            <?php esc_html_e('IBAN', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e5e5;">
                            <?php echo esc_html($transfer['iban'] ?? ''); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
        <?php include __DIR__ . '/partials/summary.php'; ?>
        <p style="font-size:13px; color:#777; line-height:1.4; margin:0 0 16px;">
            <?php esc_html_e('Si tienes alguna duda, estamos a tu disposición. Puedes responder a este correo o llamarnos por teléfono.', 'garantias-online-360vo'); ?>
        </p>
        <p style="font-size:13px; color:#777; line-height:1.4; margin:0 0 16px;">
            <?php esc_html_e('¡Gracias por confiar en 360VO!', 'garantias-online-360vo'); ?>
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
