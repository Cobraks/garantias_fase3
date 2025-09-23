<?php
if (! defined('ABSPATH')) {
    exit;
}

$site_name  = isset($site_name) ? (string) $site_name : get_bloginfo('name');
$reset_url  = isset($reset_url) ? (string) $reset_url : home_url('/wp-login.php');
$user_login = isset($user_login) ? (string) $user_login : '';
$support_url = isset($support_url) ? (string) $support_url : home_url('/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(sprintf(__('Restablece tu contraseña · %s', 'garantias-online-360vo'), $site_name)); ?></title>
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
                                <?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?>
                            </p>
                            <h1 style="margin:12px 0 0;font-size:1.5rem;color:#0f172a;font-weight:700;">
                                <?php esc_html_e('Restablece tu contraseña', 'garantias-online-360vo'); ?>
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px 32px;font-size:1rem;line-height:1.5;color:#1f2937;">
                            <p style="margin:0 0 16px;">
                                <?php echo esc_html(sprintf(__('Hola %s,', 'garantias-online-360vo'), $user_login !== '' ? $user_login : __('usuario', 'garantias-online-360vo'))); ?>
                            </p>
                            <p style="margin:0 0 16px;">
                                <?php esc_html_e('Hemos recibido una solicitud para restablecer tu contraseña de acceso a Garantías Online.', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:0 0 24px;">
                                <?php esc_html_e('Haz clic en el siguiente botón para crear una contraseña nueva. Por seguridad, el enlace dejará de estar disponible en unas horas.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#e11d48;">
                                        <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:14px 28px;font-size:1rem;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Restablecer contraseña', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:0.95rem;color:#475569;">
                                <?php esc_html_e('Si el botón no funciona, copia y pega este enlace en tu navegador:', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:0 0 24px;font-size:0.95rem;word-break:break-all;color:#0f172a;">
                                <a href="<?php echo esc_url($reset_url); ?>" style="color:#e11d48;text-decoration:none;">
                                    <?php echo esc_html($reset_url); ?>
                                </a>
                            </p>
                            <p style="margin:0;font-size:0.9rem;color:#64748b;">
                                <?php esc_html_e('Si tú no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual seguirá siendo válida.', 'garantias-online-360vo'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 32px 32px;font-size:0.85rem;line-height:1.6;color:#64748b;text-align:center;background:#f8fafc;">
                            <p style="margin:0 0 8px;">
                                <?php echo esc_html(sprintf(__('Atentamente, el equipo de %s', 'garantias-online-360vo'), $site_name)); ?>
                            </p>
                            <p style="margin:0;">
                                <a href="<?php echo esc_url($support_url); ?>" style="color:#e11d48;text-decoration:none;">
                                    <?php echo esc_html($support_url); ?>
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
