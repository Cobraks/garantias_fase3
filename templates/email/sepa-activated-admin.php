<?php
if (! defined('ABSPATH')) {
    exit;
}

$user = is_array($user ?? null) ? $user : [];
$document = is_array($document ?? null) ? $document : [];
$status = is_array($status ?? null) ? $status : [];
$trigger = is_array($trigger ?? null) ? $trigger : [];
$signature_html = isset($signature) && is_string($signature) ? trim($signature) : '';

$personal_name = trim((string) ($user['name'] ?? ''));
if ($personal_name === '') {
    $personal_name = __('Profesional', 'garantias-online-360vo');
}
$company_name = trim((string) ($user['company'] ?? ''));
$email_address = sanitize_email((string) ($user['email'] ?? ''));
$phone_number = trim((string) ($user['phone'] ?? ''));
$profile_url = isset($user['profile_url']) ? esc_url((string) $user['profile_url']) : '';
$uploaded_by_admin = ! empty($trigger['uploaded_by_admin']);
$actor_payload = is_array($trigger['actor'] ?? null) ? $trigger['actor'] : [];
$actor_name = trim((string) ($actor_payload['name'] ?? ''));
$actor_email = sanitize_email((string) ($actor_payload['email'] ?? ''));

$filename = trim((string) ($document['filename'] ?? 'mandato-sepa-firmado.pdf'));
if ($filename === '') {
    $filename = 'mandato-sepa-firmado.pdf';
}
$reference = trim((string) ($document['reference'] ?? ''));

$status_label = trim((string) ($status['label'] ?? ''));
if ($status_label === '') {
    $status_label = __('SEPA válido y activo', 'garantias-online-360vo');
}

$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
$badge_text = __('Domiciliación activada', 'garantias-online-360vo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Domiciliación bancaria activada', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php include __DIR__ . '/partials/header.php'; ?>
                            <h1 style="font-size:24px;margin:0 0 18px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Se ha activado una domiciliación bancaria', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php if ($uploaded_by_admin) : ?>
                                    <?php
                                    $actor_notice = $actor_name !== ''
                                        ? sprintf(
                                            __('El administrador %s ha validado y activado la domiciliación bancaria para este profesional.', 'garantias-online-360vo'),
                                            $actor_name
                                        )
                                        : __('Un administrador ha validado y activado la domiciliación bancaria para este profesional.', 'garantias-online-360vo');
                                    echo esc_html($actor_notice);
                                    ?>
                                <?php else : ?>
                                    <?php esc_html_e('Un profesional ha completado el proceso de validación del mandato SEPA.', 'garantias-online-360vo'); ?>
                                <?php endif; ?>
                            </p>
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
                                            <?php echo esc_html($personal_name); ?>
                                        </td>
                                    </tr>
                                    <?php if ($company_name !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Empresa', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($company_name); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($email_address !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Correo', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <a href="mailto:<?php echo esc_attr($email_address); ?>" style="color:#bc0000;text-decoration:none;">
                                                    <?php echo esc_html($email_address); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($phone_number !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($phone_number); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($profile_url !== '') : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Ficha del cliente', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <a href="<?php echo esc_url($profile_url); ?>" style="color:#bc0000;text-decoration:none;">
                                                    <?php esc_html_e('Abrir ficha', 'garantias-online-360vo'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($uploaded_by_admin && ($actor_name !== '' || $actor_email !== '')) : ?>
                                        <tr>
                                            <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                                <?php esc_html_e('Gestionado por', 'garantias-online-360vo'); ?>
                                            </th>
                                            <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
                                                <?php echo esc_html($actor_name !== '' ? $actor_name : __('Administrador', 'garantias-online-360vo')); ?>
                                                <?php if ($actor_email !== '') : ?>
                                                    <br>
                                                    <a href="mailto:<?php echo esc_attr($actor_email); ?>" style="color:#bc0000;text-decoration:none;">
                                                        <?php echo esc_html($actor_email); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <h2 style="font-size:18px;margin:0 0 12px;color:#111827;font-weight:600;">
                                <?php esc_html_e('Detalles del mandato', 'garantias-online-360vo'); ?>
                            </h2>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 24px;">
                                <tbody>
                                    <tr style="background:#f9fafb;">
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;">
                                            <?php esc_html_e('Estado', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;font-weight:600;">
                                            <?php echo esc_html($status_label); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th align="left" style="padding:12px 18px;font-size:12px;text-transform:uppercase;color:#6b7280;letter-spacing:0.08em;border-top:1px solid #e5e7eb;">
                                            <?php esc_html_e('Archivo firmado', 'garantias-online-360vo'); ?>
                                        </th>
                                        <td style="padding:12px 18px;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">
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
                                </tbody>
                            </table>
                            <p style="font-size:14px;margin:0 0 24px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Puedes revisar la documentación desde el área de clientes o contactar con el profesional si necesitas información adicional.', 'garantias-online-360vo'); ?>
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
