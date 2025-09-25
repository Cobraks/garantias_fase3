<?php
if (! defined('ABSPATH')) {
    exit;
}

$plate = $guarantee['plate'] ?? '';
$plan = $guarantee['plan'] ?? '';
$price = $guarantee['price'] ?? '';
$payment = $guarantee['payment'] ?? '';
$dates = $guarantee['dates'] ?? [];
$customer = $guarantee['customer'] ?? [];
$vendor = $guarantee['vendor'] ?? [];
$vendor_company = $vendor['company_name'] ?? ($vendor['name'] ?? '');
$vendor_personal = $vendor['contact_name'] ?? ($vendor['personal_name'] ?? '');
$vendor_channel = $vendor['channel_summary'] ?? ($vendor['channel_label'] ?? '');
$vendor_contact = $vendor_personal !== '' ? $vendor_personal : ($vendor['greeting_name'] ?? '');
$vendor_email_source = $vendor['email_source'] ?? '';
$vendor_email_pref = $vendor['email'] ?? '';
$vendor_email_registration = $vendor['registration_email'] ?? '';
if ($vendor_email_source === 'registration') {
    $vendor_contact_email = $vendor_email_registration !== '' ? $vendor_email_registration : $vendor_email_pref;
} else {
    $vendor_contact_email = $vendor_email_pref !== '' ? $vendor_email_pref : $vendor_email_registration;
}
$vendor_contact_email = sanitize_email($vendor_contact_email);
$vendor_contact = $vendor_contact !== '' ? $vendor_contact : $vendor_company;
$permalink = $guarantee['permalink'] ?? '';
$coverage = '';
if (! empty($dates['from']) && ! empty($dates['to'])) {
    $coverage = $dates['from'] . ' → ' . $dates['to'];
} elseif (! empty($dates['from'])) {
    $coverage = $dates['from'];
} elseif (! empty($dates['to'])) {
    $coverage = $dates['to'];
}
?>
<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse; margin-top:16px;">
    <tbody>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111; font-weight:600;">
                <?php echo esc_html($plate ?: '#' . ($guarantee['id'] ?? '')); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Modalidad', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($plan ?: '-'); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Importe', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($price ?: '-'); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Método de pago', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($payment ?: '-'); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Cobertura', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($coverage ?: '-'); ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html(($customer['name'] ?? '') ?: '-'); ?>
                <?php if (! empty($customer['email'])) : ?>
                    <span style="color:#888;">(<?php echo esc_html($customer['email']); ?>)</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($vendor_channel !== '' ? $vendor_channel : '-'); ?>
                <?php if ($vendor_company !== '') : ?>
                    <div style="color:#888; font-size:13px; margin-top:2px;">
                        <?php echo esc_html($vendor_company); ?>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Responsable', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html($vendor_contact !== '' ? $vendor_contact : '-'); ?>
                <?php if ($vendor_contact_email !== '') : ?>
                    <span style="color:#888;">(<?php echo esc_html($vendor_contact_email); ?>)</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php if (! empty($permalink)) : ?>
        <tr>
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Enlace', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <a href="<?php echo esc_url($permalink); ?>" style="color:#e2001b; text-decoration:none;">
                    <?php esc_html_e('Abrir en Mis Garantías', 'garantias-online-360vo'); ?>
                </a>
            </td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
