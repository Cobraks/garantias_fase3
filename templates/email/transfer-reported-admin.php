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
        '<strong style="color:#111827;">' . esc_html($vendor_name) . '</strong>'
    )
    : esc_html__('El cliente ha indicado que ha realizado la transferencia.', 'garantias-online-360vo');
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Transferencia pendiente de validación', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Transferencia reportada', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;line-height:1.35;">
                                <?php echo $headline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 20px;color:#374151;line-height:1.7;">
                                <?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </p>
                            <?php if ($transfer_amount !== '' || $transfer_account !== '' || $transfer_concept !== '') : ?>
                                <h2 style="font-size:16px;margin:0 0 12px;color:#111827;font-weight:600;">
                                    <?php esc_html_e('Detalles facilitados', 'garantias-online-360vo'); ?>
                                </h2>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;font-size:14px;color:#374151;margin:0 0 24px;">
                                    <tbody>
                                        <?php if ($transfer_amount !== '') : ?>
                                            <tr style="background:#f9fafb;">
                                                <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                                    <?php esc_html_e('Importe', 'garantias-online-360vo'); ?>
                                                </th>
                                                <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                                    <?php echo esc_html($transfer_amount); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($transfer_account !== '') : ?>
                                            <tr>
                                                <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                    <?php esc_html_e('Cuenta', 'garantias-online-360vo'); ?>
                                                </th>
                                                <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                    <?php echo esc_html($transfer_account); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($transfer_concept !== '') : ?>
                                            <tr>
                                                <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                    <?php esc_html_e('Concepto', 'garantias-online-360vo'); ?>
                                                </th>
                                                <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                    <?php echo esc_html($transfer_concept); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <p style="font-size:14px;margin:0 0 24px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Revisa la operación y accede a la garantía para activarla cuando proceda.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($permalink !== '') : ?>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 16px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#bc0000;">
                                            <a href="<?php echo esc_url($permalink); ?>" style="display:inline-block;padding:13px 28px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                                <?php esc_html_e('Abrir garantía', 'garantias-online-360vo'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>
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
