<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Clients\ClientSummary;
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
        'accent' => 'var(--admin-summary-action-alert)',
        'icon'   => Svg::icon('client_no_commercial'),
    ],
    [
        'key'    => 'no-offers',
        'label'  => __('Sin ofertas activas', 'garantias-online-360vo'),
        'filter' => 'sin_ofertas',
        'accent' => 'var(--admin-summary-action-alert)',
        'icon'   => Svg::icon('client_no_offer'),
    ],
];

$default_action_count_label = sprintf(
    _n('%d cliente', '%d clientes', 0, 'garantias-online-360vo'),
    0
);

$action_arrow_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';
$action_close_icon = Svg::icon('close');

$clients_summary_data = class_exists(ClientSummary::class)
    ? ClientSummary::get_summary()
    : [];
$clients_summary_contexts = isset($clients_summary_data['contexts']) && is_array($clients_summary_data['contexts'])
    ? $clients_summary_data['contexts']
    : [];
$clients_summary_default_context = isset($clients_summary_data['default_context'])
    && in_array($clients_summary_data['default_context'], ['year', 'month'], true)
        ? (string) $clients_summary_data['default_context']
        : 'month';
$clients_summary_has_toggle = count($clients_summary_contexts) > 1;
$clients_summary_context_labels = [
    'year'  => __('Global', 'garantias-online-360vo'),
    'month' => __('Mensual', 'garantias-online-360vo'),
];

\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_clients_page'));
?>

<div class="guarantees-list__filters">
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
            <table class="guarantees-table">
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

    <aside class="guarantee-detail">
        <div class="guarantee-detail__panel active" id="detail-panel-1">
            <div class="guarantee-detail__empty">
                <p class="guarantee-detail__hint">
                    <span class="guarantee-detail__hint-arrow" aria-hidden="true">
                        <?php echo Svg::icon('flecha_izquierda'); ?>
                    </span>
                    <?php esc_html_e('Selecciona un cliente para consultar su información, asignar comerciales, gestionar ofertas y más.', 'garantias-online-360vo'); ?>
                </p>
                <?php if (! empty($clients_summary_contexts)) : ?>
                    <?php
                    $clients_summary_classes = 'clients-summary';
                    if ($clients_summary_has_toggle) {
                        $clients_summary_classes .= ' clients-summary--interactive';
                    }
                    ?>
                    <section
                        class="<?php echo esc_attr($clients_summary_classes); ?>"
                        data-clients-summary
                        data-context="<?php echo esc_attr($clients_summary_default_context); ?>"
                        aria-labelledby="clients-summary-title"
                    >
                        <div class="clients-summary__card">
                            <header class="clients-summary__header">
                                <h3 id="clients-summary-title" class="clients-summary__title"><?php esc_html_e('Resumen de Clientes', 'garantias-online-360vo'); ?></h3>
                                <?php if ($clients_summary_has_toggle) : ?>
                                    <fieldset class="clients-summary__context" data-clients-summary-context role="radiogroup" aria-label="<?php esc_attr_e('Cambiar periodo', 'garantias-online-360vo'); ?>">
                                        <?php foreach ($clients_summary_context_labels as $context_key => $context_label) : ?>
                                            <?php if (! isset($clients_summary_contexts[$context_key])) { continue; } ?>
                                            <?php $input_id = 'clients-summary-context-' . $context_key; ?>
                                            <input
                                                type="radio"
                                                name="clients-summary-context"
                                                id="<?php echo esc_attr($input_id); ?>"
                                                class="clients-summary__context-input"
                                                value="<?php echo esc_attr($context_key); ?>"
                                                data-clients-summary-toggle
                                                <?php checked($clients_summary_default_context, $context_key); ?>
                                            >
                                            <label class="clients-summary__context-label" for="<?php echo esc_attr($input_id); ?>">
                                                <?php echo esc_html($context_label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endif; ?>
                            </header>
                            <div class="clients-summary__panels" data-clients-summary-panels>
                                <?php foreach ($clients_summary_contexts as $context_key => $context_data) : ?>
                                    <?php
                                    $metrics = isset($context_data['metrics']) && is_array($context_data['metrics']) ? $context_data['metrics'] : [];
                                    $spotlight = isset($context_data['spotlight']) && is_array($context_data['spotlight']) ? $context_data['spotlight'] : [];
                                    $trendline = isset($context_data['trendline']) && is_array($context_data['trendline']) ? $context_data['trendline'] : [];
                                    $points = isset($trendline['points']) && is_array($trendline['points']) ? $trendline['points'] : [];
                                    $sparkline_max = 0;
                                    foreach ($points as $point_entry) {
                                        $sparkline_max = max($sparkline_max, isset($point_entry['value']) ? (int) $point_entry['value'] : 0);
                                    }
                                    ?>
                                    <?php $is_active_panel = $context_key === $clients_summary_default_context; ?>
                                    <div
                                        class="clients-summary__panel<?php echo $is_active_panel ? ' is-active' : ''; ?>"
                                        data-clients-summary-panel
                                        data-context="<?php echo esc_attr($context_key); ?>"
                                        <?php echo $is_active_panel ? '' : 'hidden'; ?>
                                    >
                                        <?php if (! empty($metrics)) : ?>
                                            <div class="clients-summary__grid">
                                                <?php foreach ($metrics as $metric) : ?>
                                                    <?php
                                                    $metric_label = isset($metric['label']) ? (string) $metric['label'] : '';
                                                    $metric_value = isset($metric['formatted']) ? (string) $metric['formatted'] : number_format_i18n((int) ($metric['value'] ?? 0));
                                                    $metric_sublabel = isset($metric['sublabel']) ? (string) $metric['sublabel'] : '';
                                                    $trend = isset($metric['trend']) && is_array($metric['trend']) ? $metric['trend'] : [];
                                                    $trend_direction = isset($trend['direction']) ? (string) $trend['direction'] : 'neutral';
                                                    $trend_label = isset($trend['label']) ? (string) $trend['label'] : '—';
                                                    $trend_icon = $trend_direction === 'negative'
                                                        ? Svg::icon('arrow_drop_down')
                                                        : Svg::icon('arrow_drop_up');
                                                    $trend_class = 'clients-summary__trend';
                                                    if ($trend_direction === 'positive') {
                                                        $trend_class .= ' is-positive';
                                                    } elseif ($trend_direction === 'negative') {
                                                        $trend_class .= ' is-negative';
                                                    } else {
                                                        $trend_class .= ' is-neutral';
                                                    }
                                                    ?>
                                                    <article class="clients-summary__metric">
                                                        <div class="clients-summary__metric-label"><?php echo esc_html($metric_label); ?></div>
                                                        <div class="clients-summary__metric-value"><?php echo esc_html($metric_value); ?></div>
                                                        <?php if ($metric_sublabel !== '') : ?>
                                                            <p class="clients-summary__metric-sublabel"><?php echo esc_html($metric_sublabel); ?></p>
                                                        <?php endif; ?>
                                                        <div class="<?php echo esc_attr($trend_class); ?>">
                                                            <span class="clients-summary__trend-icon" aria-hidden="true">
                                                                <?php echo $trend_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                            </span>
                                                            <span class="clients-summary__trend-label"><?php echo esc_html($trend_label); ?></span>
                                                        </div>
                                                    </article>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        $spotlight_items = isset($spotlight['items']) && is_array($spotlight['items']) ? $spotlight['items'] : [];
                                        ?>
                                        <?php if (! empty($spotlight_items)) : ?>
                                            <div class="clients-summary__spotlight">
                                                <h4 class="clients-summary__spotlight-title"><?php echo esc_html($spotlight['title'] ?? __('Clientes destacados', 'garantias-online-360vo')); ?></h4>
                                                <ul class="clients-summary__spotlight-list">
                                                    <?php foreach ($spotlight_items as $item) : ?>
                                                        <?php
                                                        $item_label = isset($item['label']) ? (string) $item['label'] : '';
                                                        $item_value = isset($item['value']) ? (string) $item['value'] : '—';
                                                        $item_count = isset($item['count']) ? (int) $item['count'] : 0;
                                                        $item_meta = isset($item['meta']) ? (string) $item['meta'] : '';
                                                        $count_label = $item_meta;
                                                        if ($count_label === '' && $item_count > 0) {
                                                            $count_label = sprintf(
                                                                _n('%s garantía', '%s garantías', $item_count, 'garantias-online-360vo'),
                                                                number_format_i18n($item_count)
                                                            );
                                                        }
                                                        ?>
                                                        <li class="clients-summary__spotlight-item">
                                                            <span class="clients-summary__spotlight-label"><?php echo esc_html($item_label); ?></span>
                                                            <span class="clients-summary__spotlight-value"><?php echo esc_html($item_value); ?></span>
                                                            <?php if ($count_label !== '') : ?>
                                                                <span class="clients-summary__spotlight-meta"><?php echo esc_html($count_label); ?></span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (! empty($points)) : ?>
                                            <div class="clients-summary__trendline">
                                                <div class="clients-summary__trend-heading"><?php echo esc_html($trendline['title'] ?? __('Tendencia', 'garantias-online-360vo')); ?></div>
                                                <ul class="clients-summary__sparkline">
                                                    <?php foreach ($points as $point) : ?>
                                                        <?php
                                                        $point_value = isset($point['value']) ? (int) $point['value'] : 0;
                                                        $point_percent = $sparkline_max > 0 ? round(($point_value / $sparkline_max) * 100) : 0;
                                                        $point_label = isset($point['label']) ? (string) $point['label'] : '';
                                                        $point_formatted = isset($point['formatted']) ? (string) $point['formatted'] : number_format_i18n($point_value);
                                                        ?>
                                                        <li
                                                            class="clients-summary__sparkline-point"
                                                            style="--clients-summary-point-value: <?php echo esc_attr($point_percent); ?>%;"
                                                            aria-label="<?php echo esc_attr(sprintf('%s: %s', $point_label, $point_formatted)); ?>"
                                                        >
                                                            <span class="clients-summary__sparkline-bar" aria-hidden="true"></span>
                                                            <span class="clients-summary__sparkline-value"><?php echo esc_html($point_formatted); ?></span>
                                                            <span class="clients-summary__sparkline-label"><?php echo esc_html($point_label); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
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
                                data-count="0"
                                aria-pressed="false"
                            >
                                <span class="action-icon" aria-hidden="true" style="background-color: var(--action-accent)">
                                    <?php echo $action['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </span>
                                <div class="action-details">
                                    <span class="action-label"><?php echo esc_html($action['label']); ?></span>
                                    <span class="action-sublabel"><?php echo esc_html($default_action_count_label); ?></span>
                                </div>
                                <span class="action-cta" aria-hidden="true">
                                    <span class="action-cta__icon action-cta__icon--forward">
                                        <?php echo $action_arrow_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
                                    <span class="action-cta__icon action-cta__icon--close">
                                        <?php echo $action_close_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </span>
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
