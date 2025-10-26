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
$permalink = $guarantee['permalink'] ?? '';
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
                <?php echo esc_html(($dates['from'] ?? '') . ($dates['to'] ? ' → ' . $dates['to'] : '')); ?>
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
            <th align="left" style="text-transform:uppercase; font-size:12px; color:#666; padding:4px 0;"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></th>
            <td style="padding:4px 0; font-size:14px; color:#111;">
                <?php echo esc_html(($vendor['name'] ?? '') ?: '-'); ?>
                <?php if (! empty($vendor['email'])) : ?>
                    <span style="color:#888;">(<?php echo esc_html($vendor['email']); ?>)</span>
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
