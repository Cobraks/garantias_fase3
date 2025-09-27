<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;

$is_clients_page = true;
$current_user    = wp_get_current_user();

\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_clients_page'));
?>

<div class="clients-page">
    <div class="guarantees-list__filters" style="view-transition-name: filtros">
        <div class="guarantees-list__search-container">
            <span class="guarantees-list__search-icon" aria-hidden="true">
                <?php echo Svg::icon('search'); ?>
            </span>
            <input
                type="text"
                class="guarantees-list__search"
                id="clientes-search"
                placeholder="<?php esc_attr_e('Buscar cliente o empresa…', 'garantias-online-360vo'); ?>"
                aria-label="<?php esc_attr_e('Buscar cliente o empresa', 'garantias-online-360vo'); ?>"
            >
            <span class="guarantees-list__close-icon" aria-hidden="true">
                <?php echo Svg::icon('cerrar'); ?>
            </span>
        </div>
    </div>

    <div class="guarantees-container">
        <section class="guarantees-list">
            <table class="guarantees-table" style="view-transition-name: garantias-table">
                <colgroup>
                    <col data-default-width="240">
                    <col>
                    <col>
                    <col data-default-width="280">
                    <col data-default-width="120">
                    <col>
                    <col>
                </colgroup>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Registrado desde', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Ofertas', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Nº Garantías', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Tipo de pago', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Comercial', 'garantias-online-360vo'); ?></th>
                    </tr>
                </thead>
                <tbody data-current-page="0" data-total-pages="0"></tbody>
            </table>
            <div id="scroll-end" class="scroll-sentinel">
                <div class="spinner" aria-hidden="true"></div>
            </div>
        </section>

        <aside class="guarantee-detail" style="view-transition-name: resume-derecha">
            <div class="guarantee-detail__panel active" id="detail-panel-1">
                <div class="guarantee-detail__empty">
                    <h3 class="guarantee-detail__title"><?php esc_html_e('Ningún cliente seleccionado', 'garantias-online-360vo'); ?></h3>
                    <p><?php esc_html_e('Haz clic en una fila para ver sus detalles aquí.', 'garantias-online-360vo'); ?></p>
                </div>
            </div>
            <div class="guarantee-detail__panel" id="detail-panel-2"></div>
        </aside>
    </div>
</div>

<style>
    .scroll-sentinel {
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .spinner {
        width: 3rem;
        height: 3rem;
        margin: 0 auto;
        border: 5px solid rgba(0, 0, 0, 0.1);
        border-top-color: rgba(255, 0, 0, 0.6);
        border-radius: 50%;
        animation: spin .5s linear infinite;
        display: none;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_clients_page', 'current_user')
);
?>
