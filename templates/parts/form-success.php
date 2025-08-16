<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="form-success" class="form-success" hidden>
    <div class="form-success__confetti" aria-hidden="true"></div>
    <h2 class="form-success__title">¡Garantía registrada!</h2>
    <p class="form-success__note">Para completar la contratación realiza el pago y revisa la documentación.</p>
    <div class="form-success__docs">
        <h3>Documentación</h3>
        <ul class="form-success__doc-list">
            <li><a href="#" class="form-success__doc-link">Factura proforma</a></li>
            <li><a href="#" class="form-success__doc-link">Certificado de garantía</a></li>
            <li><a href="#" class="form-success__doc-link">Coberturas</a></li>
        </ul>
    </div>
    <div class="form-success__payment" hidden>
        <p class="form-success__payment-note">Contacta con nosotros para activar el certificado pasados 7 días desde su inicio.</p>
        <div class="form-success__contact">
            <a href="tel:+34900123456" class="btn btn-secondary">Llamar</a>
            <a href="mailto:info@360vo.com" class="btn btn-secondary">Enviar correo</a>
        </div>
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
