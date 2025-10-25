<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>
    <h2 class="form-success__title">¡Garantía registrada!</h2>
    <p class="form-success__note">Realiza el pago antes de 7 días para activar tu certificado.</p>
    <div class="form-success__docs">
        <h3>Documentación</h3>
        <ul class="form-success__doc-list">
            <li>
                <a href="#" class="btn btn-secondary form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    <span>Factura proforma</span>
                </a>
            </li>
            <li>
                <a href="#" class="btn btn-secondary form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    <span>Certificado de garantía</span>
                </a>
            </li>
            <li>
                <a href="#" class="btn btn-secondary form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    <span>Coberturas</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <p class="form-success__transfer-intro">Realiza una transferencia con el concepto "<span data-ref-text></span>" al número de cuenta "<span data-iban-text>ES00 0000 0000 0000 0000 0000</span>" de <span data-amount-text></span>.</p>
            <div class="form-success__field">
                <span class="form-success__label">Referencia:</span>
                <span class="form-success__value" data-ref></span>
                <button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="¡Referencia copiada!">
                    <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    <span class="form-success__copy-text">Copiar referencia</span>
                </button>
            </div>
            <div class="form-success__field">
                <span class="form-success__label">IBAN:</span>
                <span class="form-success__value" data-iban>ES00 0000 0000 0000 0000 0000</span>
                <button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="¡IBAN copiado!">
                    <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    <span class="form-success__copy-text">Copiar IBAN</span>
                </button>
            </div>
            <div class="form-success__field">
                <span class="form-success__label">Cantidad:</span>
                <span class="form-success__value" data-amount></span>
                <button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="¡Cantidad copiada!">
                    <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    <span class="form-success__copy-text">Copiar cantidad</span>
                </button>
            </div>
        </div>
    </div>
</div>
