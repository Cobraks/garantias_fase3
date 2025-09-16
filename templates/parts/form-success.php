<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>

    <h2 class="form-success__title">¡Garantía registrada!</h2>
    <p class="form-success__subtitle" data-plan></p>

    <div class="form-success__loading">
        <span class="form-success__loading-text">Generando documentos…</span>
        <span class="form-success__loading-spinner" aria-hidden="true"></span>
    </div>
    <div class="form-success__docs" hidden>
        <a href="#" class="form-success__download form-success__download--certificado" hidden></a>
        <a href="#" class="form-success__download form-success__download--condicionado" hidden></a>
        <a href="#" class="form-success__download form-success__download--cobertura" hidden></a>
    </div>
    <a href="#" class="form-success__details-link">Ver garantía <span data-ref-text></span></a>

    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <h3 class="form-success__transfer-title">
                <?php echo Svg::icon('info', 'form-success__transfer-icon'); ?>
                Transferencia bancaria
            </h3>
            <p class="form-success__transfer-note">
                <?php echo Svg::icon('warning', 'form-success__transfer-note-icon'); ?>
                Tienes 7 días para realizar el pago y activar tu certificado.
            </p>
            <table class="form-success__transfer-table">
                <tbody>
                    <tr>
                        <th scope="row">Referencia</th>
                        <td><span class="form-success__copy-target" data-ref data-toast="Referencia copiada al portapapeles."></span><button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="Referencia copiada" data-toast="Referencia copiada al portapapeles." aria-label="Copiar referencia"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr>
                        <th scope="row">IBAN</th>
                        <td><span class="form-success__copy-target" data-iban data-toast="IBAN copiado al portapapeles.">ES00 0000 0000 0000 0000 0000</span><button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr>
                        <th scope="row">Cantidad</th>
                        <td><span class="form-success__copy-target" data-amount data-toast="Cantidad copiada al portapapeles."></span><button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="form-success__toast" aria-hidden="true"></div>
    <audio id="form-success__sound" src="<?php echo esc_url(plugins_url('assets/sounds/success.mp3', GARANTIAS360VO__FILE__)); ?>" preload="auto"></audio>
</div>
