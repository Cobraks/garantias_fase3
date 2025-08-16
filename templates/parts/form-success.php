<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
?>
<div id="form-success" class="form-success">
    <div class="form-success__confetti" aria-hidden="true"></div>
    <h2 class="form-success__title">¡Garantía registrada!</h2>
    <div class="form-success__docs">
        <a href="#" class="btn btn-secondary form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Factura proforma</span>
        </a>
        <a href="#" class="btn btn-secondary form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Certificado de garantía</span>
        </a>
        <a href="#" class="btn btn-secondary form-success__doc-link">
            <?php echo Svg::icon('pdf', 'form-success__doc-icon'); ?>
            <span>Coberturas</span>
        </a>
    </div>
    <div class="form-success__payment" hidden>
        <div class="form-success__transfer" hidden>
            <h3 class="form-success__transfer-title">
                <?php echo Svg::icon('info', 'form-success__transfer-icon'); ?>
                Transferencia bancaria
            </h3>
            <p class="form-success__transfer-note">Realiza el pago antes de 7 días para activar tu certificado.</p>
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
    </div>
</div>
