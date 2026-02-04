<?php
if (! defined('ABSPATH')) {
    exit;
}

$site_name   = isset($site_name) ? (string) $site_name : get_bloginfo('name');
$user_login  = isset($user_login) ? (string) $user_login : '';
$user_email  = isset($user_email) ? (string) $user_email : '';
$profile_url = isset($profile_url) ? (string) $profile_url : admin_url('users.php');
$logo_url    = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(sprintf(__('Contraseña actualizada · %s', 'garantias-online-360vo'), $site_name)); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Notificación de seguridad', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="margin:0 0 18px;font-size:24px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Se ha actualizado una contraseña', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="margin:0 0 18px;font-size:15px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Hola,', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Te avisamos de que la contraseña de un usuario de Garantías Online se ha actualizado correctamente.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin:0 0 24px;">
                                <tbody>
                                    <tr style="background:#f9fafb;">
                                        <td style="padding:16px 20px;font-size:14px;color:#111827;font-weight:600;">
                                            <?php esc_html_e('Detalles del usuario', 'garantias-online-360vo'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:16px 20px;font-size:14px;color:#4b5563;border-top:1px solid #e5e7eb;">
                                            <?php if ($user_login !== '') : ?>
                                                <p style="margin:0 0 6px;">
                                                    <strong><?php esc_html_e('Usuario:', 'garantias-online-360vo'); ?></strong>
                                                    <?php echo esc_html($user_login); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($user_email !== '') : ?>
                                                <p style="margin:0;">
                                                    <strong><?php esc_html_e('Correo electrónico:', 'garantias-online-360vo'); ?></strong>
                                                    <a href="mailto:<?php echo esc_attr($user_email); ?>" style="color:#bc0000;text-decoration:none;">
                                                        <?php echo esc_html($user_email); ?>
                                                    </a>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p style="margin:0 0 18px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Si necesitas revisar o actualizar los permisos de este usuario, puedes acceder a su ficha desde el panel de administración.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#bc0000;">
                                        <a href="<?php echo esc_url($profile_url); ?>" style="display:inline-block;padding:13px 26px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Ver ficha de usuario', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:13px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Si no esperabas este cambio, te recomendamos ponerte en contacto con el usuario para confirmar la acción.', 'garantias-online-360vo'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="text-align:center;margin:16px 0 0;font-size:11px;color:#6b7280;padding-bottom:16px;">
                    <?php echo esc_html(sprintf(__('Centro de soporte · %s', 'garantias-online-360vo'), $site_name)); ?> ·
                    <a href="<?php echo esc_url(home_url('/garantias-online/')); ?>" style="color:#bc0000;text-decoration:none;">
                        <?php echo esc_html(home_url('/garantias-online/')); ?>
                    </a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
