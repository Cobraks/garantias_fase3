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

    <h3 class="form-success__docs-heading">Documentación</h3>
    <div class="form-success__docs">
        <a href="#" class="document-card">
            <?php echo Svg::icon('pdf', 'document-card__icon'); ?>
            <h3 class="document-card__title">Factura proforma</h3>
            <p class="document-card__desc">Documento con los detalles de tu compra</p>
        </a>
        <a href="#" class="document-card">
            <?php echo Svg::icon('pdf', 'document-card__icon'); ?>
            <h3 class="document-card__title">Certificado de garantía</h3>
            <p class="document-card__desc">Documento oficial de tu cobertura</p>
        </a>
        <a href="#" class="document-card">
            <?php echo Svg::icon('pdf', 'document-card__icon'); ?>
            <h3 class="document-card__title">Coberturas</h3>
            <p class="document-card__desc">Detalles de tu plan de protección</p>
        </a>
    </div>

    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <h3 class="form-success__transfer-title">
                <?php echo Svg::icon('info', 'form-success__transfer-icon'); ?>
                Transferencia bancaria
            </h3>
            <p class="form-success__transfer-note">
                <?php echo Svg::icon('warning', 'form-success__transfer-note-icon'); ?>
                Realiza el pago antes de 7 días para activar tu certificado.
            </p>
            <table class="form-success__transfer-table">
                <tbody>
                    <tr>
                        <th scope="row">Referencia</th>
                        <td><span data-ref></span><button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="¡Referencia copiada!" aria-label="Copiar referencia"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr>
                        <th scope="row">IBAN</th>
                        <td><span data-iban>ES00 0000 0000 0000 0000 0000</span><button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="¡IBAN copiado!" aria-label="Copiar IBAN"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                    <tr>
                        <th scope="row">Cantidad</th>
                        <td><span data-amount></span><button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="¡Cantidad copiada!" aria-label="Copiar cantidad"><?php echo Svg::icon('copy', 'form-success__copy-icon'); ?></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="form-success__toast" aria-hidden="true">¡Copiado al portapapeles!</div>
</div>
