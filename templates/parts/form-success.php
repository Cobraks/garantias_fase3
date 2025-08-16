<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>
    <h2 class="form-success__title">¡Garantía registrada!</h2>
    <p class="form-success__note">Descarga la documentación y realiza el pago para activar tu certificado.</p>
    <div class="form-success__docs">
        <h3>Documentación</h3>
        <ul class="form-success__doc-list">
            <li>
                <a href="#" class="form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    Factura proforma
                </a>
            </li>
            <li>
                <a href="#" class="form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    Certificado de garantía
                </a>
            </li>
            <li>
                <a href="#" class="form-success__doc-link">
                    <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
                    Coberturas
                </a>
            </li>
        </ul>
    </div>
    <div class="form-success__payment" hidden>
        <p class="form-success__payment-note">Contacta con 360VO 7 días después del inicio para activar el certificado.</p>
        <div class="form-success__transfer" hidden>
            <div class="form-success__field">
                <span class="form-success__label">Referencia:</span>
                <span class="form-success__value" data-ref></span>
                <button class="btn btn-link form-success__copy" data-copy="[data-ref]">Copiar</button>
            </div>
            <div class="form-success__field">
                <span class="form-success__label">IBAN:</span>
                <span class="form-success__value" data-iban>ES00 0000 0000 0000 0000 0000</span>
                <button class="btn btn-link form-success__copy" data-copy="[data-iban]">Copiar</button>
            </div>
        </div>
    </div>
    <div class="form-success__actions">
        <a href="/garantias-online/list/" class="btn btn-secondary">Ir a mis garantías</a>
        <a href="/garantias-online/add/" class="btn btn-primary">Nueva garantía</a>
    </div>
</div>
