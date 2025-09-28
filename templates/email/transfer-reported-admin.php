<?php
if (! defined('ABSPATH')) {
    exit;
}

$vendor_name = $vendor_name ?? '';
$plate_label = $plate_label ?? '';
$permalink = $permalink ?? '';
$transfer = is_array($transfer ?? null) ? $transfer : [];
$transfer_amount = $transfer['amount'] ?? '';
$transfer_account = $transfer['account'] ?? '';
$transfer_concept = $transfer['concept'] ?? '';
$headline = $plate_label !== ''
    ? sprintf(
        /* translators: %s: vehicle plate */
        esc_html__('Transferencia pendiente de validación · Garantía %s', 'garantias-online-360vo'),
        esc_html($plate_label)
    )
    : esc_html__('Transferencia pendiente de validación', 'garantias-online-360vo');
$intro = $vendor_name !== ''
    ? sprintf(
        /* translators: %s: vendor name */
        esc_html__('El cliente %s ha indicado que ha realizado la transferencia.', 'garantias-online-360vo'),
        '<strong style="color:#111;">' . esc_html($vendor_name) . '</strong>'
    )
    : esc_html__('El cliente ha indicado que ha realizado la transferencia.', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Transferencia pendiente de validación', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <h1 style="font-size:22px; margin:0 0 16px; color:#111; line-height:1.35;">
            <?php echo $headline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#374151; line-height:1.5;">
            <?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </p>
        <?php if ($transfer_amount !== '' || $transfer_account !== '' || $transfer_concept !== '') : ?>
            <div style="margin:0 0 20px;">
                <h2 style="font-size:16px; margin:0 0 8px; color:#111;">
                    <?php esc_html_e('Detalles facilitados', 'garantias-online-360vo'); ?>
                </h2>
                <table role="presentation" style="width:100%; border-collapse:collapse; font-size:14px; color:#374151;">
                    <tbody>
                        <?php if ($transfer_amount !== '') : ?>
                            <tr>
                                <th style="text-align:left; padding:6px 0; color:#111; font-weight:600;">
                                    <?php esc_html_e('Importe', 'garantias-online-360vo'); ?>
                                </th>
                                <td style="padding:6px 0; color:#374151;">
                                    <?php echo esc_html($transfer_amount); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($transfer_account !== '') : ?>
                            <tr>
                                <th style="text-align:left; padding:6px 0; color:#111; font-weight:600;">
                                    <?php esc_html_e('Cuenta', 'garantias-online-360vo'); ?>
                                </th>
                                <td style="padding:6px 0; color:#374151;">
                                    <?php echo esc_html($transfer_account); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($transfer_concept !== '') : ?>
                            <tr>
                                <th style="text-align:left; padding:6px 0; color:#111; font-weight:600;">
                                    <?php esc_html_e('Concepto', 'garantias-online-360vo'); ?>
                                </th>
                                <td style="padding:6px 0; color:#374151;">
                                    <?php echo esc_html($transfer_concept); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p style="font-size:14px; margin:0 0 20px; color:#374151; line-height:1.6;">
            <?php esc_html_e('Revisa la operación y accede a la garantía para activarla cuando proceda.', 'garantias-online-360vo'); ?>
        </p>
        <?php if ($permalink !== '') : ?>
            <p style="margin:0 0 12px;">
                <a href="<?php echo esc_url($permalink); ?>" style="display:inline-block; padding:12px 20px; background-color:#2563eb; color:#ffffff; text-decoration:none; border-radius:6px; font-size:14px; font-weight:600;">
                    <?php esc_html_e('Abrir garantía', 'garantias-online-360vo'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#6b7280;">
        <?php esc_html_e('Este mensaje ha sido generado automáticamente por 360VO Garantías Online.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
