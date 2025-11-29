<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Rest\GuaranteeRestController;
?>
<?php
$proforma_settings = GuaranteeRestController::get_proforma_feature_settings();
$show_proforma_on_success = ! empty($proforma_settings['options']['mostrar_en_pantalla_exito']);
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>

    <h2 class="form-success__title">¡Garantía contratada!</h2>
    <p class="form-success__subtitle" data-plan></p>
    <p class="form-success__message" hidden></p>

    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <p class="form-success__transfer-note">
                <?php echo Svg::icon('warning', 'form-success__transfer-note-icon'); ?>
                <span class="form-success__transfer-note-text">Realiza la transferencia antes del 6 de diciembre.</span>
            </p>
            <table class="form-success__transfer-table">
                <tbody>
                    <tr data-copy-row>
                        <th scope="row">Referencia</th>
                        <td data-copy-cell data-tooltip="Copiar referencia"><span class="form-success__copy-target" data-ref data-toast="Referencia copiada al portapapeles."></span><button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="Referencia copiada" data-toast="Referencia copiada al portapapeles." aria-label="Copiar referencia"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr data-copy-row>
                        <th scope="row">IBAN</th>
                        <td data-copy-cell data-tooltip="Copiar IBAN"><span class="form-success__copy-target" data-iban data-toast="IBAN copiado al portapapeles."></span><button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr data-copy-row>
                        <th scope="row">Cantidad</th>
                        <td data-copy-cell data-tooltip="Copiar cantidad"><span class="form-success__copy-target" data-amount data-toast="Cantidad copiada al portapapeles."></span><button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                </tbody>
            </table>
            <p class="form-success__transfer-instructions">
                Puedes remitir el justificante desde tu panel de gestión o enviarlo por correo a
                <a
                    href="mailto:garantias@360vo.es"
                    data-email-link
                    data-email-base="garantias@360vo.es"
                >garantias@360vo.es</a>.
            </p>
            <div class="form-success__toast" aria-hidden="true"></div>
        </div>
    </div>

    <div class="form-success__loading">
        <span class="form-success__loading-text">Generando documentos…</span>
        <span class="form-success__loading-spinner" aria-hidden="true"></span>
    </div>
    <div class="form-success__docs" hidden>
        <p class="form-success__docs-message" hidden></p>
        <a href="#" class="document-card" data-doc="certificate" hidden>
            <span class="document-card__icon" aria-hidden="true"><?php echo Svg::icon('pdf'); ?></span>
            <span class="document-card__title"></span>
        </a>
        <?php if ($show_proforma_on_success) : ?>
            <a href="#" class="document-card" data-doc="proforma" hidden>
                <span class="document-card__icon" aria-hidden="true"><?php echo Svg::icon('pdf'); ?></span>
                <span class="document-card__title"></span>
            </a>
        <?php endif; ?>
    </div>
    <div class="form-success__actions">
        <a href="<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>" class="form-success__new" data-reset-draft>Añadir nueva garantía</a>
        <a href="<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>" class="form-success__details-link" hidden>Ver garantía <span data-ref-text></span></a>
    </div>
    <audio id="form-success__sound" src="<?php echo esc_url(plugins_url('assets/sounds/success.mp3', GARANTIAS360VO__FILE__)); ?>" preload="auto"></audio>
</div>
