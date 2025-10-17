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

$filename = trim((string) ($document['filename'] ?? 'mandato-sepa-firmado.pdf'));
$reference = trim((string) ($document['reference'] ?? ''));
$submitted = isset($document['submitted']) ? (int) $document['submitted'] : current_time('timestamp');
if ($submitted <= 0) {
    $submitted = current_time('timestamp');
}

$date_format = get_option('date_format') ?: 'd/m/Y';
$time_format = get_option('time_format') ?: 'H:i';
$submitted_label = wp_date($date_format . ' ' . $time_format, $submitted);

$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
$badge_text = __('Mandato SEPA firmado', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Nuevo mandato SEPA firmado', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 18px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Hemos recibido un mandato SEPA firmado', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php
                                echo esc_html(
                                    __('El profesional ha completado la subida del mandato SEPA. Revisa el documento adjunto y valida la domiciliación desde el panel de clientes.', 'garantias-online-360vo')
                                );
                                ?>
                            </p>
                            <?php if ($profile_url !== '') : ?>
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
                                    <tr>
                                        <td style="border-radius:999px;background:#bc0000;">
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
                                <?php esc_html_e('Detalles del mandato', 'garantias-online-360vo'); ?>
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
                                    <tr>
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                            <?php esc_html_e('Enviado el', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                            <?php echo esc_html($submitted_label); ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="font-size:14px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('El mandato se adjunta a este correo. Tras revisarlo, valida la domiciliación desde el panel del cliente para activar el cobro automatizado.', 'garantias-online-360vo'); ?>
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
