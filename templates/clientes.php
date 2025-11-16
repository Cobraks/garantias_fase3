<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;

$is_clients_page = true;
$current_user    = wp_get_current_user();

$clients_quick_actions = [
    [
        'key'    => 'payment',
        'label'  => __('Con garantías pendientes de pago', 'garantias-online-360vo'),
        'filter' => 'pendiente_pago',
        'accent' => 'var(--admin-summary-action-payment)',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -960 960 960" fill="currentColor"><path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/></svg>',
    ],
    [
        'key'    => 'draft',
        'label'  => __('Con garantías sin finalizar', 'garantias-online-360vo'),
        'filter' => 'sin_finalizar',
        'accent' => 'var(--admin-summary-state-sin-finalizar)',
        'icon'   => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>',
    ],
    [
        'key'    => 'no-commercial',
        'label'  => __('Sin comercial asignado', 'garantias-online-360vo'),
        'filter' => 'sin_comercial',
        'accent' => 'var(--admin-summary-state-pendiente-revision)',
        'icon'   => Svg::icon('client_no_commercial'),
    ],
    [
        'key'    => 'no-offers',
        'label'  => __('Sin ofertas activas', 'garantias-online-360vo'),
        'filter' => 'sin_ofertas',
        'accent' => 'var(--admin-summary-action-payment)',
        'icon'   => Svg::icon('client_no_offer'),
    ],
];

$action_arrow_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';

\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_clients_page'));
?>

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
    <div class="guarantees-list__filter-wrapper">
        <label class="screen-reader-text" for="clientes-channel-filter"><?php esc_html_e('Filtrar por canal de venta', 'garantias-online-360vo'); ?></label>
        <select
            id="clientes-channel-filter"
            class="guarantees-list__filter"
            aria-label="<?php esc_attr_e('Filtrar por canal de venta', 'garantias-online-360vo'); ?>"
        >
            <option value=""><?php esc_html_e('Todos los canales', 'garantias-online-360vo'); ?></option>
        </select>
    </div>
    <div class="guarantees-list__filters-actions">
        <button
            type="button"
            class="guarantees-list__reset-btn"
            data-reset-filters
            hidden
        >
            <?php echo Svg::icon('filter_reset', 'guarantees-list__reset-icon'); ?>
            <span class="guarantees-list__reset-label">
                <?php esc_html_e('Reiniciar filtros', 'garantias-online-360vo'); ?>
            </span>
        </button>
    </div>

</div>

<div class="guarantees-container">
    <section class="guarantees-list">
        <div class="clients-cards" data-clients-cards>
            <div class="clients-cards__list" data-clients-cards-list></div>
            <div class="clients-cards__empty" data-clients-cards-empty hidden>
                <p data-clients-cards-empty-message>
                    <?php esc_html_e('No se han encontrado clientes con los filtros actuales.', 'garantias-online-360vo'); ?>
                </p>
            </div>
        </div>

        <div class="guarantees-table__scroll">
            <table class="guarantees-table" style="view-transition-name: garantias-table">
                <colgroup>
                    <col class="guarantees-table__col guarantees-table__col--client" data-default-width="360">
                    <col class="guarantees-table__col guarantees-table__col--registered" data-default-width="72">
                    <col class="guarantees-table__col guarantees-table__col--offers" data-default-width="220">
                    <col class="guarantees-table__col guarantees-table__col--guarantees" data-default-width="72">
                    <col class="guarantees-table__col guarantees-table__col--commercial" data-default-width="220">
                </colgroup>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Ofertas', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Nº Garantías', 'garantias-online-360vo'); ?></th>
                        <th><?php esc_html_e('Comercial', 'garantias-online-360vo'); ?></th>
                    </tr>
                </thead>
                <tbody data-current-page="0" data-total-pages="0"></tbody>
            </table>
        </div>
        <div id="scroll-end" class="scroll-sentinel" aria-hidden="true">
            <div class="spinner" aria-hidden="true">
                <div class="spinner__inner" aria-hidden="true"></div>
            </div>
        </div>
    </section>

    <aside class="guarantee-detail" style="view-transition-name: resume-derecha">
        <div class="guarantee-detail__panel active" id="detail-panel-1">
            <div class="guarantee-detail__empty">
                <p class="guarantee-detail__hint">
                    <span class="guarantee-detail__hint-arrow" aria-hidden="true">
                        <?php echo Svg::icon('flecha_izquierda'); ?>
                    </span>
                    <?php esc_html_e('Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.', 'garantias-online-360vo'); ?>
                </p>
                <section class="actions-section">
                    <h3 class="actions-section-title"><?php esc_html_e('Acciones rápidas', 'garantias-online-360vo'); ?></h3>
                    <ul class="actions-list actions-list--clients" data-admin-summary-actions data-clients-actions>
                        <?php foreach ($clients_quick_actions as $action) : ?>
                            <li
                                class="action-item"
                                data-action="<?php echo esc_attr($action['key']); ?>"
                                data-filter-value="<?php echo esc_attr($action['filter']); ?>"
                                style="--action-accent: <?php echo esc_attr($action['accent']); ?>"
                                role="button"
                                tabindex="0"
                            >
                                <span class="action-icon" aria-hidden="true" style="background-color: var(--action-accent)">
                                    <?php echo $action['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                                <div class="action-details">
                                    <span class="action-label"><?php echo esc_html($action['label']); ?></span>
                                    <span class="action-sublabel"><?php esc_html_e('— clientes', 'garantias-online-360vo'); ?></span>
                                </div>
                                <span class="action-cta" aria-hidden="true">
                                    <?php echo $action_arrow_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>
        </div>
        <div class="guarantee-detail__panel" id="detail-panel-2"></div>
    </aside>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_clients_page', 'current_user')
);
?>
