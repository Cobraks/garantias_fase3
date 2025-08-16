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
    <div class="form-success__docs">
        <a href="#" class="form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Factura proforma</span>
        </a>
        <a href="#" class="form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Certificado de garantía</span>
        </a>
        <a href="#" class="form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Coberturas</span>
        </a>
    </div>

    <div class="form-success__payment" hidden>
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
                    <td data-ref></td>
                    <td class="form-success__action">
                        <button class="form-success__copy" data-copy="[data-ref]" data-label="Copiar referencia" data-done="¡Referencia copiada!" aria-label="Copiar referencia">
                            <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">IBAN</th>
                    <td data-iban>ES00 0000 0000 0000 0000 0000</td>
                    <td class="form-success__action">
                        <button class="form-success__copy" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="¡IBAN copiado!" aria-label="Copiar IBAN">
                            <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                        </button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cantidad</th>
                    <td data-amount></td>
                    <td class="form-success__action">
                        <button class="form-success__copy" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="¡Cantidad copiada!" aria-label="Copiar cantidad">
                            <?php echo Svg::icon('copy', 'form-success__copy-icon'); ?>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="form-success__toast" aria-hidden="true">¡Copiado al portapapeles!</div>
</div>
