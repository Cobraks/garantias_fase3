<?php
if (! defined('ABSPATH')) {
    exit;
}

$company  = is_array($company ?? null) ? $company : [];
$address  = is_array($company['address'] ?? null) ? $company['address'] : [];
$workshop = is_array($workshop ?? null) ? $workshop : null;
$sepa     = is_array($sepa ?? null) ? $sepa : null;
$user     = is_array($user ?? null) ? $user : [];
$auto_signature = ! empty($auto_signature);

$company_name = $company['trade_name'] ?? '';
if ($company_name === '' && ! empty($company['legal_name'])) {
    $company_name = $company['legal_name'];
}

$full_address = array_filter([
    $address['street'] ?? '',
    trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')),
    $address['province'] ?? '',
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Nuevo registro en Garantías Online', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#111827; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Nuevo registro', 'garantias-online-360vo'); ?>
        </div>
        <h1 style="font-size:22px; margin:0 0 16px; color:#111;">
            <?php
            if ($company_name !== '') {
                printf(
                    /* translators: %s company name */
                    esc_html__('Se ha registrado %s', 'garantias-online-360vo'),
                    esc_html($company_name)
                );
            } else {
                esc_html_e('Se ha registrado un nuevo usuario', 'garantias-online-360vo');
            }
            ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
            <?php esc_html_e('Estos son los datos enviados a través del formulario de alta de Garantías Online.', 'garantias-online-360vo'); ?>
        </p>

        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin:0 0 24px;">
            <tbody>
                <tr style="background:#f9fafb;">
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em;">
                        <?php esc_html_e('Nombre', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; font-weight:600;">
                        <?php echo esc_html($user['name'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('Correo', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html($user['email'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html($user['phone'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html($user['channel'] ?? ''); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 style="font-size:18px; margin:0 0 12px; color:#111;">
            <?php esc_html_e('Datos de la empresa', 'garantias-online-360vo'); ?>
        </h2>
        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin:0 0 24px;">
            <tbody>
                <tr style="background:#f9fafb;">
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em;">
                        <?php esc_html_e('Nombre comercial', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; font-weight:600;">
                        <?php echo esc_html($company['trade_name'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('Razón social', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html($company['legal_name'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('CIF', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html($company['tax_id'] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                        <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                    </th>
                    <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                        <?php echo esc_html(implode(', ', $full_address)); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if ($workshop) : ?>
            <h2 style="font-size:18px; margin:0 0 12px; color:#111;">
                <?php esc_html_e('Datos del taller', 'garantias-online-360vo'); ?>
            </h2>
            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin:0 0 24px;">
                <tbody>
                    <tr style="background:#f9fafb;">
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em;">
                            <?php esc_html_e('Nombre', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; font-weight:600;">
                            <?php echo esc_html($workshop['name'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('Contacto', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($workshop['contact'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($workshop['phone'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('Email', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($workshop['email'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($workshop['address'] ?? ''); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($sepa) : ?>
            <h2 style="font-size:18px; margin:0 0 12px; color:#111;">
                <?php esc_html_e('Datos SEPA', 'garantias-online-360vo'); ?>
            </h2>
            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin:0 0 24px;">
                <tbody>
                    <tr style="background:#f9fafb;">
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em;">
                            <?php esc_html_e('Titular', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; font-weight:600;">
                            <?php echo esc_html($sepa['name'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($sepa['address'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('IBAN', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($sepa['iban'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding:10px 14px; font-size:12px; text-transform:uppercase; color:#6b7280; letter-spacing:0.05em; border-top:1px solid #e5e7eb;">
                            <?php esc_html_e('SWIFT/BIC', 'garantias-online-360vo'); ?>
                        </th>
                        <td style="padding:10px 14px; font-size:14px; color:#111; border-top:1px solid #e5e7eb;">
                            <?php echo esc_html($sepa['swift'] ?? ''); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($auto_signature) : ?>
            <div style="padding:14px 18px; margin:0 0 18px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:8px; font-size:14px; color:#3730a3; line-height:1.6;">
                <?php esc_html_e('Ha solicitado activar la firma y el sello automáticos para los certificados.', 'garantias-online-360vo'); ?>
            </div>
        <?php endif; ?>

        <p style="font-size:13px; color:#6b7280; line-height:1.6; margin:0;">
            <?php esc_html_e('Puedes consultar toda la información desde el panel de administración de Garantías Online.', 'garantias-online-360vo'); ?>
        </p>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#9ca3af;">
        <?php esc_html_e('Mensaje generado automáticamente por Garantías Online 360VO.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
