<?php
if (! defined('ABSPATH')) {
    exit;
}

$user = is_array($user ?? null) ? $user : [];
$document = is_array($document ?? null) ? $document : [];
$account_url = isset($account_url) ? esc_url((string) $account_url) : esc_url(home_url('/garantias-online/'));
$support_url = isset($support_url) ? esc_url((string) $support_url) : esc_url(home_url('/garantias-online/soporte/'));
$signature_html = isset($signature) && is_string($signature) ? trim($signature) : '';

$personal_name = trim((string) ($user['name'] ?? ''));
if ($personal_name === '') {
    $personal_name = __('Profesional', 'garantias-online-360vo');
}
$company_name = trim((string) ($user['company'] ?? ''));
$email_address = sanitize_email((string) ($user['email'] ?? ''));

$filename = trim((string) ($document['filename'] ?? 'mandato-sepa.pdf'));
if ($filename === '') {
    $filename = 'mandato-sepa.pdf';
}
$reference = trim((string) ($document['reference'] ?? ''));
$generated_raw = trim((string) ($document['generated'] ?? ''));
$generated_label = '';
if ($generated_raw !== '') {
    $timestamp = strtotime($generated_raw);
    if ($timestamp) {
        $date_format = get_option('date_format') ?: 'd/m/Y';
        $time_format = get_option('time_format') ?: 'H:i';
        $generated_label = wp_date($date_format . ' ' . $time_format, $timestamp);
    }
}

$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
$badge_text = __('Mandato SEPA pendiente', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Tu mandato SEPA para firmar', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php include __DIR__ . '/partials/header.php'; ?>
                            <h1 style="font-size:24px;margin:0 0 18px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Tu mandato SEPA para firmar', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php echo esc_html(sprintf(__('Hola %s, adjuntamos el mandato SEPA generado para que lo revises y lo firmes.', 'garantias-online-360vo'), $personal_name)); ?>
                            </p>
                            <p style="font-size:15px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Una vez firmado, súbelo a tu área privada para completar la activación de la domiciliación bancaria.', 'garantias-online-360vo'); ?>
                            </p>
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Detalles del documento', 'garantias-online-360vo'); ?>
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 24px;">
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
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Próximos pasos', 'garantias-online-360vo'); ?>
                            </h2>
                            <ol style="margin:0 0 24px;padding-left:20px;color:#374151;font-size:14px;line-height:1.7;">
                                <li><?php esc_html_e('Descarga el mandato SEPA adjunto y revisa los datos.', 'garantias-online-360vo'); ?></li>
                                <li><?php esc_html_e('Firma el documento y guárdalo en formato PDF.', 'garantias-online-360vo'); ?></li>
                                <li><?php esc_html_e('Accede a tu área privada y sube el mandato firmado para completar la activación.', 'garantias-online-360vo'); ?></li>
                            </ol>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
                                <tr>
                                    <td style="border-radius:999px;background:#bc0000;">
                                        <a href="<?php echo esc_url($account_url); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Abrir mi área privada', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:14px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Si necesitas ayuda, ponte en contacto con nuestro equipo de soporte y estaremos encantados de acompañarte.', 'garantias-online-360vo'); ?>
                                <br>
                                <a href="<?php echo esc_url($support_url); ?>" style="color:#bc0000;text-decoration:none;">
                                    <?php esc_html_e('Ir al centro de soporte', 'garantias-online-360vo'); ?>
                                </a>
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
