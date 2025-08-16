<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>

    <header class="form-success__header">
        <?php echo Svg::icon('shield_alt', 'form-success__header-icon'); ?>
        <h2 class="form-success__title">¡Garantía registrada!</h2>
    </header>

    <div class="form-success__alert">
        <?php echo Svg::icon('warning', 'form-success__alert-icon'); ?>
        <p class="form-success__alert-text">Importante: realiza el pago antes de 7 días para activar tu certificado.</p>
    </div>

    <section class="form-success__section">
        <h3 class="form-success__section-title">
            <?php echo Svg::icon('pdf', 'form-success__section-icon'); ?>
            Documentación
        </h3>
        <div class="form-success__docs-grid">
            <a href="#" class="form-success__doc-card">
                <?php echo Svg::icon('pdf', 'form-success__doc-card-icon'); ?>
                <span class="form-success__doc-title">Factura proforma</span>
            </a>
            <a href="#" class="form-success__doc-card">
                <?php echo Svg::icon('pdf', 'form-success__doc-card-icon'); ?>
                <span class="form-success__doc-title">Certificado de garantía</span>
            </a>
            <a href="#" class="form-success__doc-card">
                <?php echo Svg::icon('pdf', 'form-success__doc-card-icon'); ?>
                <span class="form-success__doc-title">Coberturas</span>
            </a>
        </div>
    </section>

    <section class="form-success__section form-success__payment" hidden>
        <h3 class="form-success__section-title">
            <?php echo Svg::icon('info', 'form-success__section-icon'); ?>
            Instrucciones de pago
        </h3>
        <div class="form-success__transfer" hidden>
            <div class="form-success__payment-grid">
                <div class="form-success__detail">
                    <span class="form-success__detail-label">Referencia</span>
                    <span class="form-success__detail-value" data-ref></span>
                    <button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="¡Referencia copiada!" aria-label="Copiar referencia">
                        <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    </button>
                </div>
                <div class="form-success__detail">
                    <span class="form-success__detail-label">IBAN</span>
                    <span class="form-success__detail-value" data-iban>ES00 0000 0000 0000 0000 0000</span>
                    <button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="¡IBAN copiado!" aria-label="Copiar IBAN">
                        <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    </button>
                </div>
                <div class="form-success__detail">
                    <span class="form-success__detail-label">Cantidad</span>
                    <span class="form-success__detail-value" data-amount></span>
                    <button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="¡Cantidad copiada!" aria-label="Copiar cantidad">
                        <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="form-success__toast" aria-hidden="true">¡Copiado al portapapeles!</div>
</div>
