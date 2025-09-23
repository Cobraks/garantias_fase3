<?php
if (! defined('ABSPATH')) {
    exit;
}

$site_name   = isset($site_name) ? (string) $site_name : get_bloginfo('name');
$user_login  = isset($user_login) ? (string) $user_login : '';
$user_email  = isset($user_email) ? (string) $user_email : '';
$profile_url = isset($profile_url) ? (string) $profile_url : admin_url('users.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(sprintf(__('Contraseña actualizada · %s', 'garantias-online-360vo'), $site_name)); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Inter',system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 24px 48px -32px rgba(15,23,42,0.45);">
                    <tr>
                        <td style="padding:32px 32px 16px 32px;text-align:center;">
                            <p style="margin:0;font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;color:#64748b;font-weight:600;">
                                <?php esc_html_e('Notificación de seguridad', 'garantias-online-360vo'); ?>
                            </p>
                            <h1 style="margin:12px 0 0;font-size:1.5rem;color:#0f172a;font-weight:700;">
                                <?php esc_html_e('Se ha actualizado una contraseña', 'garantias-online-360vo'); ?>
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;font-size:1rem;line-height:1.5;color:#1f2937;">
                            <p style="margin:0 0 16px;">
                                <?php esc_html_e('Hola,', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:0 0 16px;">
                                <?php esc_html_e('Te avisamos de que la contraseña de un usuario de Garantías Online se ha actualizado correctamente.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 24px;background:#f8fafc;border-radius:12px;">
                                <tr>
                                    <td style="padding:16px 20px;font-size:0.95rem;color:#0f172a;">
                                        <p style="margin:0 0 6px;font-weight:600;">
                                            <?php esc_html_e('Detalles del usuario', 'garantias-online-360vo'); ?>
                                        </p>
                                        <?php if ($user_login !== '') : ?>
                                            <p style="margin:0 0 4px;color:#1f2937;">
                                                <strong><?php esc_html_e('Usuario:', 'garantias-online-360vo'); ?></strong>
                                                <?php echo esc_html($user_login); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($user_email !== '') : ?>
                                            <p style="margin:0;color:#1f2937;">
                                                <strong><?php esc_html_e('Correo electrónico:', 'garantias-online-360vo'); ?></strong>
                                                <a href="mailto:<?php echo esc_attr($user_email); ?>" style="color:#e11d48;text-decoration:none;">
                                                    <?php echo esc_html($user_email); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:0.95rem;color:#475569;">
                                <?php esc_html_e('Si necesitas revisar o actualizar los permisos de este usuario, puedes acceder a su ficha desde el panel de administración.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#0f172a;">
                                        <a href="<?php echo esc_url($profile_url); ?>" style="display:inline-block;padding:12px 26px;font-size:0.95rem;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Ver ficha de usuario', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:0.9rem;color:#64748b;">
                                <?php esc_html_e('Si no esperabas este cambio, te recomendamos ponerte en contacto con el usuario para confirmar la acción.', 'garantias-online-360vo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 32px 32px;font-size:0.85rem;line-height:1.6;color:#64748b;text-align:center;background:#f8fafc;">
                            <p style="margin:0 0 8px;">
                                <?php echo esc_html(sprintf(__('Centro de soporte · %s', 'garantias-online-360vo'), $site_name)); ?>
                            </p>
                            <p style="margin:0;">
                                <a href="<?php echo esc_url(home_url('/garantias-online/')); ?>" style="color:#e11d48;text-decoration:none;">
                                    <?php echo esc_html(home_url('/garantias-online/')); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
