<?php
if (! defined('ABSPATH')) {
    exit;
}

$initiator = $context['initiator'] ?? [];
$initiator_name = $initiator['name'] ?? '';
$plate_label = $guarantee['plate'] ?? '#' . ($guarantee['id'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html(__('Garantía activada', 'garantias-online-360vo')); ?></title>
</head>
<body style="margin:0; padding:0; background:#f7f7f7; font-family:Arial, Helvetica, sans-serif; color:#111;">
    <div style="max-width:640px; margin:0 auto; padding:32px 24px; background:#ffffff;">
        <h1 style="font-size:22px; margin:0 0 12px; color:#111;">
            <?php
            printf(
                /* translators: %s: vehicle plate */
                esc_html__('Garantía activada para %s', 'garantias-online-360vo'),
                esc_html($plate_label)
            );
            ?>
        </h1>
        <p style="font-size:15px; margin:0 0 16px; color:#444; line-height:1.5;">
            <?php if ($initiator_name) : ?>
                <?php
                printf(
                    /* translators: %s: user name */
                    esc_html__('El usuario %s ha completado la contratación y la garantía ya está activa.', 'garantias-online-360vo'),
                    '<strong style="color:#111;">' . esc_html($initiator_name) . '</strong>'
                );
                ?>
            <?php else : ?>
                <?php esc_html_e('La contratación se ha completado y la garantía ya está activa.', 'garantias-online-360vo'); ?>
            <?php endif; ?>
        </p>
        <?php include __DIR__ . '/partials/summary.php'; ?>
        <p style="margin-top:24px; font-size:13px; color:#777; line-height:1.4;">
            <?php esc_html_e('Puedes revisar los documentos adjuntos o acceder al expediente desde Mis Garantías.', 'garantias-online-360vo'); ?>
        </p>
    </div>
    <p style="text-align:center; margin:16px 0 0; font-size:12px; color:#999;">
        <?php esc_html_e('Este mensaje ha sido generado automáticamente por 360VO Garantías Online.', 'garantias-online-360vo'); ?>
    </p>
</body>
</html>
