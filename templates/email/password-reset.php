<?php
if (! defined('ABSPATH')) {
    exit;
}

$site_name      = isset($site_name) ? (string) $site_name : get_bloginfo('name');
$reset_url      = isset($reset_url) ? (string) $reset_url : home_url('/wp-login.php');
$user_login     = isset($user_login) ? (string) $user_login : '';
$personal_name  = isset($personal_name) ? (string) $personal_name : '';
$company_name   = isset($company_name) ? (string) $company_name : '';
$support_url    = isset($support_url) ? (string) $support_url : home_url('/');
$signature      = isset($signature) ? (string) $signature : '';

$greeting_name = $personal_name !== ''
    ? $personal_name
    : ($user_login !== '' ? $user_login : __('usuario', 'garantias-online-360vo'));

$reset_notice = __('Hemos recibido una solicitud para restablecer tu contraseña de acceso a Garantías Online.', 'garantias-online-360vo');
if ($company_name !== '' && $company_name !== $greeting_name) {
    $reset_notice = sprintf(
        /* translators: %s: company name */
        __('Hemos recibido una solicitud para restablecer la contraseña de acceso a Garantías Online de %s.', 'garantias-online-360vo'),
        $company_name
    );
}
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(sprintf(__('Restablece tu contraseña · %s', 'garantias-online-360vo'), $site_name)); ?></title>
</head>
<body style="margin:0;padding:32px 16px;background-color:#f4f4f5;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Seguridad de tu cuenta', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="margin:0 0 18px;font-size:24px;color:#111827;font-weight:700;">
                                <?php esc_html_e('Restablece tu contraseña', 'garantias-online-360vo'); ?>
                            </h1>
                            <p style="margin:0 0 18px;font-size:15px;color:#374151;line-height:1.7;">
                                <?php echo esc_html(sprintf(__('Hola %s,', 'garantias-online-360vo'), $greeting_name)); ?>
                            </p>
                            <p style="margin:0 0 18px;font-size:15px;color:#374151;line-height:1.7;">
                                <?php echo esc_html($reset_notice); ?>
                            </p>
                            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.7;">
                                <?php esc_html_e('Haz clic en el siguiente botón para crear una contraseña nueva. Por seguridad, el enlace dejará de estar disponible en unas horas.', 'garantias-online-360vo'); ?>
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#bc0000;">
                                        <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Restablecer contraseña', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Si el botón no funciona, copia y pega este enlace en tu navegador:', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;color:#bc0000;line-height:1.6;word-break:break-all;">
                                <a href="<?php echo esc_url($reset_url); ?>" style="color:#bc0000;text-decoration:none;">
                                    <?php echo esc_html($reset_url); ?>
                                </a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Si tú no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual seguirá siendo válida.', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="margin:24px 0 0;font-size:13px;color:#4b5563;line-height:1.6;">
                                <?php echo esc_html(sprintf(__('Atentamente, el equipo de %s', 'garantias-online-360vo'), $site_name)); ?>
                            </p>
                            <p style="margin:4px 0 0;font-size:13px;color:#bc0000;line-height:1.6;">
                                <a href="<?php echo esc_url($support_url); ?>" style="color:#bc0000;text-decoration:none;">
                                    <?php echo esc_html($support_url); ?>
                                </a>
                            </p>
                            <?php
                            $signature_html = $signature;
                            include __DIR__ . '/partials/signature.php';
                            ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
