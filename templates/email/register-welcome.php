<?php
if (! defined('ABSPATH')) {
    exit;
}

$name          = isset($name) && $name !== '' ? $name : __('Profesional', 'garantias-online-360vo');
$company_name  = isset($company_name) ? (string) $company_name : '';
$channel_label = isset($channel_label) && $channel_label !== '' ? $channel_label : __('Profesional', 'garantias-online-360vo');
$channel_key   = isset($channel_key) ? (string) $channel_key : '';
$account_url   = isset($account_url) && $account_url !== '' ? $account_url : home_url('/garantias-online/');
$support_url   = isset($support_url) && $support_url !== '' ? $support_url : home_url('/garantias-online/soporte/');
$signature     = isset($signature) ? (string) $signature : '';
$sepa_pending  = ! empty($sepa_pending);

$headline = sprintf(
    /* translators: %s: channel label */
    esc_html__('Tu cuenta de %s ya está activa', 'garantias-online-360vo'),
    $channel_label
);

$intro_company = '';
if ($company_name !== '') {
    $intro_company = sprintf(
        /* translators: %s: company name */
        esc_html__('El perfil de %s ya está listo para utilizar Garantías Online.', 'garantias-online-360vo'),
        esc_html($company_name)
    );
}

$checklist = [];
switch ($channel_key) {
    case 'compraventa':
    case 'concesionario':
        $sepa_item = $sepa_pending
            ? esc_html__('Firma el documento adjunto para activar la domiciliación bancaria en tu cuenta.', 'garantias-online-360vo')
            : esc_html__('Activa la domiciliación SEPA si vas a gestionar los cobros de forma automatizada.', 'garantias-online-360vo');
        $checklist = [
            esc_html__('Accede a "Mis garantías" para comenzar a registrar vehículos y generar certificados.', 'garantias-online-360vo'),
            esc_html__('Revisa el apartado "Perfil" para completar los datos de tu empresa y subir firma o sello si los necesitas.', 'garantias-online-360vo'),
            $sepa_item,
        ];
        break;
    case 'agency':
        $checklist = [
            esc_html__('Desde "Mis garantías" puedes gestionar las coberturas de tus clientes y enviarles la documentación en minutos.', 'garantias-online-360vo'),
            esc_html__('Utiliza el perfil para mantener actualizados los datos fiscales de tu gestoría.', 'garantias-online-360vo'),
            esc_html__('Guarda este correo para tener a mano el acceso directo y el contacto de soporte.', 'garantias-online-360vo'),
        ];
        break;
    case 'individual':
        $checklist = [
            esc_html__('Utiliza "Mis garantías" para llevar un control de tus coberturas activas.', 'garantias-online-360vo'),
            esc_html__('Completa tus datos personales desde el apartado "Perfil" para recibir avisos correctamente.', 'garantias-online-360vo'),
            esc_html__('Si en algún momento necesitas ayuda, escríbenos desde el área de soporte.', 'garantias-online-360vo'),
        ];
        break;
    default:
        $checklist = [
            esc_html__('Accede al área privada para gestionar tus garantías y revisar tu perfil.', 'garantias-online-360vo'),
            esc_html__('Revisa la sección de soporte para conocer las guías rápidas y preguntas frecuentes.', 'garantias-online-360vo'),
        ];
        break;
}
$logo_url = plugins_url('assets/images/logo-horizontal.png', GARANTIAS360VO__FILE__);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Bienvenida a Garantías Online', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0;padding:32px 0px;font-family:'Roboto','Segoe UI','San Francisco',Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;overflow:hidden;">
                    <tr>
                        <td style="padding:32px 28px 12px 28px;">
                            <?php
                            $badge_text = __('Cuenta activada', 'garantias-online-360vo');
                            include __DIR__ . '/partials/header.php';
                            ?>
                            <h1 style="font-size:24px;margin:0 0 16px;color:#111827;font-weight:700;">
                                <?php echo esc_html($headline); ?>
                            </h1>
                            <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                <?php
                                printf(
                                    /* translators: %s: recipient name */
                                    esc_html__('Hola %s,', 'garantias-online-360vo'),
                                    esc_html($name)
                                );
                                ?>
                            </p>
                            <?php if ($intro_company !== '') : ?>
                                <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                    <?php echo $intro_company; ?>
                                </p>
                            <?php else : ?>
                                <p style="font-size:15px;margin:0 0 18px;color:#374151;line-height:1.7;">
                                    <?php esc_html_e('Tu cuenta ya está verificada y lista para comenzar a trabajar con nosotros.', 'garantias-online-360vo'); ?>
                                </p>
                            <?php endif; ?>
                            <p style="font-size:15px;margin:0 0 16px;color:#111827;font-weight:600;line-height:1.6;">
                                <?php esc_html_e('Próximos pasos recomendados', 'garantias-online-360vo'); ?>
                            </p>
                            <ul style="margin:0 0 24px 18px;padding:0;color:#4b5563;">
                                <?php foreach ($checklist as $item) : ?>
                                    <li style="margin:0 0 10px;font-size:14px;line-height:1.6;">
                                        <?php echo esc_html($item); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:999px;background:#bc0000;">
                                        <a href="<?php echo esc_url($account_url); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;">
                                            <?php esc_html_e('Entrar en mi área privada', 'garantias-online-360vo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:13px;margin:0 0 10px;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('¿Necesitas ayuda? Nuestro equipo está disponible para acompañarte en los primeros pasos.', 'garantias-online-360vo'); ?>
                            </p>
                            <p style="font-size:13px;margin:0 0 20px;color:#bc0000;line-height:1.6;font-weight:600;">
                                <a href="<?php echo esc_url($support_url); ?>" style="color:#bc0000;text-decoration:none;">
                                    <?php esc_html_e('Ir al centro de soporte', 'garantias-online-360vo'); ?>
                                </a>
                            </p>
                            <p style="font-size:13px;margin:0;color:#4b5563;line-height:1.6;">
                                <?php esc_html_e('Gracias por confiar en Garantías Online 360VO.', 'garantias-online-360vo'); ?>
                            </p>
                            <?php
                            $signature_html = $signature;
                            include __DIR__ . '/partials/signature.php';
                            ?>
                        </td>
                    </tr>
                </table>
                <p style="text-align:center;margin:16px 0 0;font-size:12px;color:#6b7280;">
                    <?php esc_html_e('Este mensaje ha sido enviado automáticamente por Garantías Online 360VO.', 'garantias-online-360vo'); ?>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
