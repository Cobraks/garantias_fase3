<?php
if (! defined('ABSPATH')) {
    exit;
}

$name          = isset($name) && $name !== '' ? $name : __('Profesional', 'garantias-online-360vo');
$company_name  = isset($company_name) ? (string) $company_name : '';
$channel_label = isset($channel_label) && $channel_label !== '' ? $channel_label : __('Profesional', 'garantias-online-360vo');
$channel_key   = isset($channel_key) ? (string) $channel_key : '';
$account_url   = isset($account_url) && $account_url !== '' ? $account_url : home_url('/garantias-online/');
$support_url   = isset($support_url) && $support_url !== '' ? $support_url : home_url('/garantias-online/contacto/');

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
        $checklist = [
            esc_html__('Accede a "Mis garantías" para comenzar a registrar vehículos y generar certificados.', 'garantias-online-360vo'),
            esc_html__('Revisa el apartado "Perfil" para completar los datos de tu empresa y subir firma o sello si los necesitas.', 'garantias-online-360vo'),
            esc_html__('Activa la domiciliación SEPA si vas a gestionar los cobros de forma automatizada.', 'garantias-online-360vo'),
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php esc_html_e('Bienvenida a Garantías Online', 'garantias-online-360vo'); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <div style="display:inline-block; padding:6px 14px; background:#15803d; color:#ffffff; font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; border-radius:999px; margin-bottom:18px;">
            <?php esc_html_e('Cuenta activada', 'garantias-online-360vo'); ?>
        </div>
        <h1 style="font-size:22px; margin:0 0 12px; color:#111;">
            <?php echo esc_html($headline); ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
            <?php
            printf(
                /* translators: %s: recipient name */
                esc_html__('Hola %s,', 'garantias-online-360vo'),
                esc_html($name)
            );
            ?>
        </p>
        <?php if ($intro_company !== '') : ?>
            <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
                <?php echo $intro_company; ?>
            </p>
        <?php else : ?>
            <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.6;">
                <?php esc_html_e('Tu cuenta ya está verificada y lista para comenzar a trabajar con nosotros.', 'garantias-online-360vo'); ?>
            </p>
        <?php endif; ?>

        <p style="font-size:15px; margin:0 0 16px; color:#1f2937; font-weight:600;">
            <?php esc_html_e('Próximos pasos recomendados', 'garantias-online-360vo'); ?>
        </p>
        <ul style="margin:0 0 20px 18px; padding:0; color:#444;">
            <?php foreach ($checklist as $item) : ?>
                <li style="margin:0 0 8px; font-size:14px; line-height:1.6;">
                    <?php echo esc_html($item); ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p style="margin:0 0 24px;">
            <a href="<?php echo esc_url($account_url); ?>" style="display:inline-block; padding:14px 26px; background:#1d4ed8; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; border-radius:999px;">
                <?php esc_html_e('Entrar en mi área privada', 'garantias-online-360vo'); ?>
            </a>
        </p>

        <p style="font-size:13px; margin:0 0 8px; color:#6b7280; line-height:1.6;">
            <?php esc_html_e('¿Necesitas ayuda? Nuestro equipo está disponible para acompañarte en los primeros pasos.', 'garantias-online-360vo'); ?>
        </p>
        <p style="font-size:13px; margin:0 0 16px; color:#1d4ed8; line-height:1.6;">
            <a href="<?php echo esc_url($support_url); ?>" style="color:#1d4ed8; text-decoration:none; font-weight:600;">
                <?php esc_html_e('Ir al centro de soporte', 'garantias-online-360vo'); ?>
            </a>
        </p>

        <p style="font-size:13px; margin:0; color:#6b7280; line-height:1.6;">
            <?php esc_html_e('Gracias por confiar en Garantías Online 360VO.', 'garantias-online-360vo'); ?>
        </p>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#9ca3af;">
        <?php esc_html_e('Este mensaje ha sido enviado automáticamente por Garantías Online 360VO.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
