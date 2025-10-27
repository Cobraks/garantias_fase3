<?php
if (! defined('ABSPATH')) {
    exit;
}

$user = is_array($user ?? null) ? $user : [];
$document = is_array($document ?? null) ? $document : [];
$signature_html = isset($signature) && is_string($signature) ? trim($signature) : '';

$name = trim((string) ($user['name'] ?? ''));
$email = sanitize_email($user['email'] ?? '');
$phone = trim((string) ($user['phone'] ?? ''));
$company = trim((string) ($user['company'] ?? ''));
$profile_url = isset($user['profile_url']) ? esc_url_raw((string) $user['profile_url']) : '';

$display_name = $name !== '' ? $name : __('Profesional', 'garantias-online-360vo');
if ($company !== '') {
    $display_name .= ' (' . $company . ')';
}

$filename = trim((string) ($document['filename'] ?? 'mandato-sepa.pdf'));
$reference = trim((string) ($document['reference'] ?? ''));
$generated = isset($document['generated']) ? (string) $document['generated'] : '';

$date_format = get_option('date_format') ?: 'd/m/Y';
$time_format = get_option('time_format') ?: 'H:i';
$generated_label = $generated !== '' ? wp_date($date_format . ' ' . $time_format, strtotime($generated)) : '';

$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Solicitud de domiciliación bancaria', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Domiciliación pendiente de firma', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 18px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Nueva solicitud de domiciliación bancaria', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: professional display name */
                                        __('%s ha generado un mandato SEPA para firmar desde su área privada. Revisa los datos y acompaña al cliente en el proceso de firma.', 'garantias-online-360vo'),
                                        $display_name
                                    )
                                );
                                ?>
                            </p>
                            <?php if ($profile_url !== '') : ?>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#1d4ed8;">
                                            <a href="<?php echo esc_url($profile_url); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                                <?php esc_html_e('Abrir ficha del cliente', 'garantias-online-360vo'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            <?php endif; ?>
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Datos del profesional', 'garantias-online-360vo'); ?>
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 24px;">
                                <tbody>
                                    <tr style="background:#f9fafb;">
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                            <?php esc_html_e('Nombre', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                            <?php echo esc_html($name !== '' ? $name : __('Profesional', 'garantias-online-360vo')); ?>
                                        </td>
                                    </tr>
                                    <?php if ($company !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Empresa', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($company); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($email !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Correo', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <a href="mailto:<?php echo esc_attr($email); ?>" style="color:#1d4ed8;"><?php echo esc_html($email); ?></a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($phone !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($phone); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Detalle de la solicitud', 'garantias-online-360vo'); ?>
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 28px;">
                                <tbody>
                                    <tr style="background:#f9fafb;">
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                            <?php esc_html_e('Archivo', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                            <?php echo esc_html($filename); ?>
                                        </td>
                                    </tr>
                                    <?php if ($reference !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Referencia', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($reference); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($generated_label !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Generado el', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($generated_label); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <p style="font-size:14px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Adjuntamos el mandato generado automáticamente. Verifica los datos bancarios antes de remitir la versión para firma al cliente.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php if ($signature_html !== '') : ?>
                                <div style="margin-top:32px;font-size:13px;line-height:1.6;color:#4b5563;">
                                    <?php echo wp_kses_post($signature_html); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
