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
$channel_key = isset($channel_key) ? (string) $channel_key : '';
$channel_label = isset($channel_label) ? (string) $channel_label : ($user['channel'] ?? '');
$requires_transfer = ! empty($requires_transfer);

$company_name = $company['trade_name'] ?? '';
if ($company_name === '' && ! empty($company['legal_name'])) {
    $company_name = $company['legal_name'];
}

$full_address = array_filter([
    $address['street'] ?? '',
    trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? '')),
    $address['province'] ?? '',
]);
$has_company = false;
foreach ([
    $company['trade_name'] ?? '',
    $company['legal_name'] ?? '',
    $company['tax_id'] ?? '',
    $address['street'] ?? '',
    $address['postal_code'] ?? '',
    $address['city'] ?? '',
    $address['province'] ?? '',
] as $value) {
    if (trim((string) $value) !== '') {
        $has_company = true;
        break;
    }
}
$has_workshop = false;
if (is_array($workshop)) {
    foreach ($workshop as $value) {
        if (trim((string) $value) !== '') {
            $has_workshop = true;
            break;
        }
    }
}
$has_sepa = false;
if (is_array($sepa)) {
    foreach ($sepa as $value) {
        if (trim((string) $value) !== '') {
            $has_sepa = true;
            break;
        }
    }
}
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Nuevo registro en Garantías Online', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:720px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Nuevo registro', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 18px;color:#111827;font-weight:700;">
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
                            <p style="font-size:15px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Estos son los datos enviados a través del formulario de alta de Garantías Online.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($requires_transfer) : ?>
                                <p style="font-size:14px;margin:0 0 18px;color:#b91c1c;line-height:1.6;font-weight:600;">
                                    <?php esc_html_e('Registro de particular: las garantías se abonarán mediante transferencia bancaria (domiciliación no disponible).', 'garantias-online-360vo'); ?>
                                </p>
                            <?php endif; ?>
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Datos de contacto', 'garantias-online-360vo'); ?>
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 28px;">
                                <tbody>
                                    <tr style="background:#f9fafb;">
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                            <?php esc_html_e('Nombre', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                            <?php echo esc_html($user['name'] ?? ''); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                            <?php esc_html_e('Correo', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                            <?php echo esc_html($user['email'] ?? ''); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                            <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                            <?php echo esc_html($user['phone'] ?? ''); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                            <?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                            <?php echo esc_html($channel_label !== '' ? $channel_label : ($user['channel'] ?? '')); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <?php if ($has_company) : ?>
                                <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                    <?php esc_html_e('Datos de la empresa', 'garantias-online-360vo'); ?>
                                </h2>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 28px;">
                                    <tbody>
                                        <tr style="background:#f9fafb;">
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                                <?php esc_html_e('Nombre comercial', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                                <?php echo esc_html($company['trade_name'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Razón social', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($company['legal_name'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('CIF', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($company['tax_id'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html(implode(', ', $full_address)); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <?php if ($has_workshop) : ?>
                                <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                    <?php esc_html_e('Datos del taller', 'garantias-online-360vo'); ?>
                                </h2>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 28px;">
                                    <tbody>
                                        <tr style="background:#f9fafb;">
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                                <?php esc_html_e('Nombre', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                                <?php echo esc_html($workshop['name'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Denominación fiscal', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['fiscal_name'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('CIF', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['tax_id'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Contacto', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['contact'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['phone'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Email', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['email'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($workshop['address'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <?php if ($has_sepa) : ?>
                                <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                    <?php esc_html_e('Datos SEPA', 'garantias-online-360vo'); ?>
                                </h2>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 28px;">
                                    <tbody>
                                        <tr style="background:#f9fafb;">
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                                <?php esc_html_e('Titular', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                                <?php echo esc_html($sepa['name'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Dirección', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($sepa['address'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('IBAN', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($sepa['iban'] ?? ''); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('BIC', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($sepa['bic'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <?php if ($auto_signature) : ?>
                                <p style="font-size:13px;margin:0 0 12px;color:#6b7280;line-height:1.6;">
                                    <?php esc_html_e('El sistema ha adjuntado automáticamente la documentación enviada por el profesional.', 'garantias-online-360vo'); ?>
                                </p>
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
