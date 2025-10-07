<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>

    <h2 class="form-success__title">¡Garantía contratada!</h2>
    <p class="form-success__subtitle" data-plan></p>
    <p class="form-success__message" hidden></p>

    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <h3 class="form-success__transfer-title">
                <?php echo Svg::icon('info', 'form-success__transfer-icon'); ?>
                Transferencia bancaria
            </h3>
            <p class="form-success__transfer-note">
                <?php echo Svg::icon('warning', 'form-success__transfer-note-icon'); ?>
                <span class="form-success__transfer-note-text">Realiza el pago antes de la fecha límite indicada.</span>
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
                    <tr data-copy-row>
                        <th scope="row">Justificante</th>
                        <td data-copy-cell data-tooltip="Copiar dirección">
                            Envía el justificante de ingreso a
                            <span
                                class="form-success__copy-target"
                                data-email
                                data-copy-value="garantias@460vo.es"
                                data-toast="Dirección copiada al portapapeles"
                            >
                                <a
                                    href="mailto:garantias@460vo.es"
                                    data-email-link
                                    data-email-base="garantias@460vo.es"
                                >garantias@460vo.es</a>
                            </span>
                            <button
                                class="form-success__copy"
                                data-copy="[data-email]"
                                data-label="Copiar dirección"
                                data-done="Dirección copiada"
                                data-toast="Dirección copiada al portapapeles"
                                aria-label="Copiar dirección"
                            >
                                <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="form-success__toast" aria-hidden="true"></div>
    </div>

    <div class="form-success__loading">
        <span class="form-success__loading-text">Generando documentos…</span>
        <span class="form-success__loading-spinner" aria-hidden="true"></span>
    </div>
    <div class="form-success__docs" hidden>
        <a href="#" class="document-card" data-doc="certificate" hidden>
            <span class="document-card__icon" aria-hidden="true"><?php echo Svg::icon('pdf'); ?></span>
            <span class="document-card__title"></span>
        </a>
        <a href="#" class="document-card" data-doc="cobertura" hidden>
            <span class="document-card__icon" aria-hidden="true"><?php echo Svg::icon('pdf'); ?></span>
            <span class="document-card__title"></span>
        </a>
        <a href="#" class="document-card" data-doc="condicionado" hidden>
            <span class="document-card__icon" aria-hidden="true"><?php echo Svg::icon('pdf'); ?></span>
            <span class="document-card__title"></span>
        </a>
    </div>
    <div class="form-success__actions">
        <a href="<?php echo esc_url(home_url('/garantias-online/mis-garantias/')); ?>" class="form-success__details-link" hidden>Ver garantía <span data-ref-text></span></a>
        <a href="<?php echo esc_url(home_url('/garantias-online/nueva-garantia/')); ?>" class="form-success__new" data-reset-draft>Añadir nueva garantía</a>
    </div>
    <audio id="form-success__sound" src="<?php echo esc_url(plugins_url('assets/sounds/success.mp3', GARANTIAS360VO__FILE__)); ?>" preload="auto"></audio>
</div>
