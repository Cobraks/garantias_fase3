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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Garantía activada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#009688; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Pago confirmado', 'garantias-online-360vo'); ?>
        </div>
        <?php if ($client_intro !== '') : ?>
            <div style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
                <?php echo wpautop($client_intro); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
        <h1 style="font-size:22px; margin:0 0 12px; color:#111;">
            <?php
            printf(
                esc_html__('Garantía activada para %s', 'garantias-online-360vo'),
                esc_html($plate_label)
            );
            ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
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
                '<strong>' . esc_html($plan_name) . '</strong>'
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
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
            <?php
            printf(
                /* translators: 1: plan description, 2: optional vehicle fragment */
                wp_kses(
                    __('Has activado %1$s%2$s. El vehículo queda cubierto desde este momento.', 'garantias-online-360vo'),
                    ['strong' => []]
                ),
                wp_kses_post($plan_fragment),
                $vehicle_fragment
            );
            ?>
        </p>
        <p style="font-size:15px; margin:0 0 20px; color:#444; line-height:1.6;">
            <?php esc_html_e('Puedes acceder a tu área de Mis Garantías para descargar la documentación o revisar el estado de la cobertura cuando lo necesites.', 'garantias-online-360vo'); ?>
        </p>
        <?php if ($permalink !== '') : ?>
            <p style="margin:0 0 24px;">
                <a href="<?php echo esc_url($permalink); ?>" style="display:inline-block; padding:12px 22px; background:#009688; color:#ffffff; text-decoration:none; font-size:14px; border-radius:6px;">
                    <?php esc_html_e('Abrir Mis Garantías', 'garantias-online-360vo'); ?>
                </a>
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
        <p style="font-size:13px; color:#777; line-height:1.5; margin:0 0 16px;">
            <?php esc_html_e('Si necesitas realizar alguna gestión adicional, puedes responder directamente a este correo o contactar con tu asesor de 360VO.', 'garantias-online-360vo'); ?>
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
