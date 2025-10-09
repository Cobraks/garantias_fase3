<?php
if (! defined('ABSPATH')) {
    exit;
}

// “Mis garantías”
$is_list_page = true;
\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_list_page'));

use GarantiasOnline360VO\Svg;
use GarantiasOnline360VO\Rest\GuaranteeRestController;

$current_user = wp_get_current_user();
$user_roles   = is_object($current_user) ? (array) $current_user->roles : [];
$new_guarantee_url = home_url('/garantias-online/nueva-garantia/');

$has_role = static function (string $role) use ($user_roles): bool {
    return in_array($role, $user_roles, true);
};

$is_professional = $has_role('go_profesional');
$is_particular   = $has_role('go_particular');
$is_commercial   = $has_role('go_comercial');
$is_director     = $has_role('go_director_comercial');
$is_garantias    = $has_role('go_garantias');
$is_admin_user   = $has_role('administrator') || $has_role('admin');

$show_channel_col = ! $is_professional;

$uses_admin_filters = $is_admin_user || $is_garantias || $is_director;
$show_channel_select = ($uses_admin_filters || $is_commercial)
    && ! $is_professional
    && ! $is_particular;
$show_clients_select = $show_channel_select;
$show_plan_in_main   = $is_professional;
$show_more_filters   = ! $is_professional && ! $is_particular && ($uses_admin_filters || $is_commercial);
$show_order_button   = ! $is_particular;

$show_plan_in_advanced   = $show_more_filters && ! $is_professional;
$show_payment_in_advanced = $show_more_filters;
$show_commercial_select   = $show_more_filters && $uses_admin_filters;

$sort_presets = [];
if ($show_order_button) {
    if ($is_professional) {
        $sort_presets = [
            [
                'key'        => 'created_desc',
                'label'      => __('Más recientes', 'garantias-online-360vo'),
                'order_by'   => 'created',
                'order'      => 'desc',
                'is_default' => true,
            ],
            [
                'key'      => 'created_asc',
                'label'    => __('Más antiguas', 'garantias-online-360vo'),
                'order_by' => 'created',
                'order'    => 'asc',
            ],
        ];
    } else {
        $sort_presets = [
            [
                'key'        => 'created_desc',
                'label'      => __('Más recientes', 'garantias-online-360vo'),
                'order_by'   => 'created',
                'order'      => 'desc',
                'is_default' => true,
            ],
            [
                'key'      => 'created_asc',
                'label'    => __('Más antiguas', 'garantias-online-360vo'),
                'order_by' => 'created',
                'order'    => 'asc',
            ],
            [
                'key'      => 'valid_until_asc',
                'label'    => __('Caduca antes', 'garantias-online-360vo'),
                'order_by' => 'valid_until',
                'order'    => 'asc',
            ],
            [
                'key'      => 'valid_until_desc',
                'label'    => __('Caduca más tarde', 'garantias-online-360vo'),
                'order_by' => 'valid_until',
                'order'    => 'desc',
            ],
        ];
    }
}

$default_sort = current(array_filter($sort_presets, static fn($preset) => ! empty($preset['is_default'])));
$default_sort_key = is_array($default_sort) && ! empty($default_sort['key'])
    ? $default_sort['key']
    : 'created_desc';
$default_sort_label = is_array($default_sort) && ! empty($default_sort['label'])
    ? $default_sort['label']
    : __('Más recientes', 'garantias-online-360vo');

$admin_summary_context_key = 'month';
$admin_summary_data        = [];
$admin_summary_default     = [];
$admin_summary_states      = [];
$admin_summary_total       = 0;
$admin_summary_label       = '';
$admin_summary_pending     = [];
$admin_summary_actions     = [];
$admin_summary_json        = '';

$format_summary_number = static function ($value): string {
    return number_format_i18n((int) $value);
};

$format_summary_currency = static function ($value): string {
    $amount = is_numeric($value) ? (float) $value : 0.0;
    return number_format_i18n($amount, 2) . ' €';
};

$format_summary_guarantees = static function ($count) use ($format_summary_number): string {
    $count_int  = (int) $count;
    $formatted  = $format_summary_number($count_int);
    $pluralized = _n('%s garantía', '%s garantías', $count_int, 'garantias-online-360vo');
    return sprintf($pluralized, $formatted);
};

if ($is_admin_user && class_exists(GuaranteeRestController::class) && GuaranteeRestController::can_view_summary()) {
    $admin_summary_data = GuaranteeRestController::get_admin_summary_data();

    if (is_array($admin_summary_data) && ! empty($admin_summary_data)) {
        $contexts = isset($admin_summary_data['contexts']) && is_array($admin_summary_data['contexts'])
            ? $admin_summary_data['contexts']
            : [];

        $admin_summary_default = $contexts[$admin_summary_context_key]
            ?? ($admin_summary_data[$admin_summary_context_key] ?? []);

        if (! is_array($admin_summary_default)) {
            $admin_summary_default = [];
        }

        $state_labels = [
            'activada'           => __('Activadas', 'garantias-online-360vo'),
            'pendiente_pago'     => __('Pend. Pago', 'garantias-online-360vo'),
            'pendiente_revision' => __('Verificar/cobrar', 'garantias-online-360vo'),
            'sin_finalizar'      => __('Sin finalizar', 'garantias-online-360vo'),
        ];

        $state_order = array_keys($state_labels);
        $raw_states  = isset($admin_summary_default['states']) && is_array($admin_summary_default['states'])
            ? $admin_summary_default['states']
            : [];
        $indexed_states = [];
        foreach ($raw_states as $entry) {
            if (! is_array($entry) || empty($entry['value'])) {
                continue;
            }
            $indexed_states[$entry['value']] = $entry;
        }

        foreach ($state_order as $state_key) {
            $entry = $indexed_states[$state_key] ?? [];
            $admin_summary_states[] = [
                'value' => $state_key,
                'label' => $entry['label'] ?? $state_labels[$state_key],
                'count' => isset($entry['count']) ? (int) $entry['count'] : 0,
            ];
        }

        $admin_summary_total = isset($admin_summary_default['count']) ? (int) $admin_summary_default['count'] : 0;
        $admin_summary_label = isset($admin_summary_default['label']) ? (string) $admin_summary_default['label'] : '';
        $admin_summary_pending = isset($admin_summary_data['pending']) && is_array($admin_summary_data['pending'])
            ? $admin_summary_data['pending']
            : [];

        $pending_payment    = $admin_summary_pending['payment'] ?? [];
        $pending_validation = $admin_summary_pending['validation'] ?? [];
        $pending_collect    = $admin_summary_pending['collect'] ?? [];

        $admin_summary_actions = [
            [
                'key'         => 'payment',
                'label'       => __('Pendientes de pago', 'garantias-online-360vo'),
                'filter'      => 'pendiente_pago',
                'accent'      => 'var(--admin-summary-action-payment)',
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M1.92.506a.5.5 0 0 1 .434.146L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.708 0L3 14.707l-.646.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l.5-1a.5.5 0 0 1 .367-.27zm.217 1.338L2 2.118v11.764l.137.274.51-.51a.5.5 0 0 1 .707 0l.646.647.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.51.51.137-.274V2.118l-.137-.274-.51.51a.5.5 0 0 1-.707 0L12 1.707l-.646.647a.5.5 0 0 1-.708 0L10 1.707l-.646.647a.5.5 0 0 1-.708 0L8 1.707l-.646.647a.5.5 0 0 1-.708 0L6 1.707l-.646.647a.5.5 0 0 1-.708 0L4 1.707l-.646.647a.5.5 0 0 1-.708 0l-.51-.51z"/></svg>',
                'pending'     => $pending_payment,
            ],
            [
                'key'         => 'validation',
                'label'       => __('Pendientes de verificar transferencia', 'garantias-online-360vo'),
                'filter'      => 'validacion_pendiente',
                'accent'      => 'var(--admin-summary-action-validation)',
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>',
                'pending'     => $pending_validation,
            ],
            [
                'key'         => 'collect',
                'label'       => __('Pendientes de cobrar domiciliación', 'garantias-online-360vo'),
                'filter'      => 'pendiente_cobro',
                'accent'      => 'var(--admin-summary-action-collect)',
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/></svg>',
                'pending'     => $pending_collect,
            ],
        ];

        $admin_summary_json = wp_json_encode($admin_summary_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
?>


<!-- 1. FILTROS -->
<div class="guarantees-list__filters" style="view-transition-name: filtros">
    <div class="guarantees-list__filters-row">
        <div class="guarantees-list__search-container">
            <span class="guarantees-list__search-icon" aria-hidden="true">
                <?php echo Svg::icon('search'); ?>
            </span>
            <input
                type="text"
                class="guarantees-list__search"
                placeholder="<?php esc_attr_e('Buscar vehículo o matrícula…', 'garantias-online-360vo'); ?>"
                aria-label="<?php esc_attr_e('Buscar vehículo o matrícula', 'garantias-online-360vo'); ?>" id="buscador_mis_garantias">
            <span class="guarantees-list__close-icon" aria-hidden="true">
                <?php echo Svg::icon('cerrar'); ?>
            </span>
        </div>

        <select
            class="guarantees-list__filter"
            data-filter="estado"
            aria-label="<?php esc_attr_e('Estado', 'garantias-online-360vo'); ?>">
            <option value=""><?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?></option>
        </select>

        <?php if ($show_plan_in_main) : ?>
            <select
                class="guarantees-list__filter"
                data-filter="plan"
                aria-label="<?php esc_attr_e('Coberturas', 'garantias-online-360vo'); ?>">
                <option value=""><?php esc_html_e('Todas las coberturas', 'garantias-online-360vo'); ?></option>
            </select>
        <?php endif; ?>

        <?php if ($show_channel_select) : ?>
            <select
                class="guarantees-list__filter"
                data-filter="canal"
                aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>">
                <option value="" data-channel="">
                    <?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?>
                </option>
                <option value="particular" data-channel="particular">
                    <?php esc_html_e('Particular', 'garantias-online-360vo'); ?>
                </option>
                <option value="profesional" data-channel="profesional" data-vendor-type="">
                    <?php esc_html_e('Profesional', 'garantias-online-360vo'); ?>
                </option>
                <option value="profesional" data-channel="profesional" data-vendor-type="compraventa">
                    <?php esc_html_e('Compraventa', 'garantias-online-360vo'); ?>
                </option>
                <option value="profesional" data-channel="profesional" data-vendor-type="concesionario_oficial">
                    <?php esc_html_e('Concesionario oficial', 'garantias-online-360vo'); ?>
                </option>
                <option value="gestoria" data-channel="gestoria" data-vendor-type="gestoria">
                    <?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?>
                </option>
            </select>
        <?php endif; ?>

        <?php if ($show_clients_select) : ?>
            <div class="guarantees-list__filter-wrapper guarantees-list__filter-wrapper--clients" data-clients-wrapper hidden>
                <select
                    class="guarantees-list__filter"
                    data-filter="cliente"
                    aria-label="<?php esc_attr_e('Empresas', 'garantias-online-360vo'); ?>">
                    <option value="">
                        <?php esc_html_e('Todos los clientes', 'garantias-online-360vo'); ?>
                    </option>
                </select>
            </div>
        <?php endif; ?>

        <div class="guarantees-list__filters-actions">
            <button
                type="button"
                class="guarantees-list__reset-btn"
                data-reset-filters
                hidden>
                <?php echo Svg::icon('filter_reset', 'guarantees-list__reset-icon'); ?>
                <span class="guarantees-list__reset-label">
                    <?php esc_html_e('Reiniciar filtros', 'garantias-online-360vo'); ?>
                </span>
            </button>
            <?php if ($show_order_button && ! empty($sort_presets)) : ?>
                <div class="guarantees-list__order" data-order-root>
                    <button
                        type="button"
                        class="guarantees-list__order-btn"
                        data-order-toggle
                        data-default-sort="<?php echo esc_attr($default_sort_key); ?>"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="guarantees-order-menu">
                        <?php echo Svg::icon('sort_lines', 'guarantees-list__order-icon'); ?>
                        <span class="guarantees-list__order-current" data-order-label>
                            <?php echo esc_html($default_sort_label); ?>
                        </span>
                        <span class="guarantees-list__order-caret" aria-hidden="true">
                            <?php echo Svg::icon('arrow_drop_down'); ?>
                        </span>
                    </button>
                    <div
                        class="guarantees-list__order-menu"
                        id="guarantees-order-menu"
                        role="menu"
                        data-order-menu
                        hidden>
                        <?php foreach ($sort_presets as $preset) : ?>
                            <button
                                type="button"
                                class="guarantees-list__order-option<?php echo ! empty($preset['is_default']) ? ' is-active' : ''; ?>"
                                role="menuitemradio"
                                aria-checked="<?php echo ! empty($preset['is_default']) ? 'true' : 'false'; ?>"
                                data-sort-key="<?php echo esc_attr($preset['key']); ?>"
                                data-order-by="<?php echo esc_attr($preset['order_by']); ?>"
                                data-order-direction="<?php echo esc_attr($preset['order']); ?>"
                                <?php echo ! empty($preset['is_default']) ? 'data-default="true"' : ''; ?>>
                                <?php echo esc_html($preset['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($show_more_filters) : ?>
                <div class="guarantees-list__filters-more">
                    <button
                        type="button"
                        class="guarantees-list__more-filters-btn"
                        data-more-filters
                        data-default-label="<?php esc_attr_e('Más filtros', 'garantias-online-360vo'); ?>"
                        data-active-label="<?php esc_attr_e('Ocultar filtros', 'garantias-online-360vo'); ?>"
                        aria-expanded="false">
                        <?php echo Svg::icon('filter_funnel', 'guarantees-list__more-filters-icon'); ?>
                        <span class="guarantees-list__more-filters-label">
                            <?php esc_html_e('Más filtros', 'garantias-online-360vo'); ?>
                        </span>
                        <span class="guarantees-list__more-filters-caret" aria-hidden="true">
                            <?php echo Svg::icon('arrow_drop_down'); ?>
                        </span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($show_more_filters) : ?>
        <div class="guarantees-list__filters-advanced" data-advanced-panel hidden>
            <div class="guarantees-list__filters-advanced-grid">
                <?php if ($show_plan_in_advanced) : ?>
                    <select
                        class="guarantees-list__filter"
                        data-filter="plan"
                        aria-label="<?php esc_attr_e('Coberturas', 'garantias-online-360vo'); ?>">
                        <option value=""><?php esc_html_e('Todas las coberturas', 'garantias-online-360vo'); ?></option>
                    </select>
                <?php endif; ?>

                <?php if ($show_payment_in_advanced) : ?>
                    <select
                        class="guarantees-list__filter"
                        data-filter="payment"
                        aria-label="<?php esc_attr_e('Método de pago', 'garantias-online-360vo'); ?>">
                        <option value=""><?php esc_html_e('Todos los métodos de pago', 'garantias-online-360vo'); ?></option>
                    </select>
                <?php endif; ?>

                <?php if ($show_commercial_select) : ?>
                    <select
                        class="guarantees-list__filter"
                        data-filter="commercial"
                        aria-label="<?php esc_attr_e('Garantías por comercial', 'garantias-online-360vo'); ?>">
                        <option value=""><?php esc_html_e('Todos los comerciales', 'garantias-online-360vo'); ?></option>
                    </select>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="guarantees-container">
    <!-- 2. LISTA: tabla semántica con columna “Garantía” al final y “Canal de venta” en vendedor -->
    <section class="guarantees-list">
        <table class="guarantees-table" style="view-transition-name: garantias-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Vehículo',  'garantias-online-360vo'); ?></th>
                    <th><?php esc_html_e('Validez',   'garantias-online-360vo'); ?></th>
                    <th>
                        <?php
                        if ($show_channel_col) {
                            esc_html_e('Canal de venta', 'garantias-online-360vo');
                        } else {
                            esc_html_e('Cliente', 'garantias-online-360vo');
                        }
                        ?>
                    </th>
                    <th><?php esc_html_e('Estado',    'garantias-online-360vo'); ?></th>
                    <th><?php esc_html_e('Garantía',  'garantias-online-360vo'); ?></th>
                </tr>
            </thead>
            <tbody data-current-page="0" data-total-pages="">
                <!-- Aquí sólo los ítems cargados dinámicamente -->
            </tbody>
        </table>

        <!-- Fila de carga fija, fuera del tbody para que no se elimine al vaciar -->
        <div id="scroll-end" class="scroll-sentinel">
            <div class="spinner" aria-hidden="true"></div>
        </div>
        <!-- <div class="scroll_up scroll_up--list">
            <button>^</button>
        </div> -->
    </section>

    <style>
        /* Fila de carga al fondo */
        .loading-row {
            text-align: center;
            padding: 1rem;
        }

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
            /* display: none; */
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>


    <!-- 3. DETALLE: dos paneles -->
    <?php ob_start(); ?>
    <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="awaiting">
        <p class="guarantee-detail__hint">
            <span class="guarantee-detail__hint-arrow" aria-hidden="true">
                <?php echo Svg::icon('flecha_izquierda'); ?>
            </span>
            <?php esc_html_e('Haz clic en una garantía para consultar la información completa.', 'garantias-online-360vo'); ?>
        </p>
        <?php if ($is_admin_user) : ?>
            <?php
            $summary_context_base = uniqid('summary-context-');
            $summary_global_id    = $summary_context_base . '-global';
            $summary_month_id     = $summary_context_base . '-month';
            $has_admin_summary    = $admin_summary_json !== '' && ! empty($admin_summary_states);

            $state_color_vars = [
                'activada'           => 'var(--admin-summary-state-activada)',
                'pendiente_pago'     => 'var(--admin-summary-state-pendiente-pago)',
                'pendiente_revision' => 'var(--admin-summary-state-pendiente-revision)',
                'sin_finalizar'      => 'var(--admin-summary-state-sin-finalizar)',
            ];

            $donut_style_map = [
                '--segment-0-end'   => '0%',
                '--segment-1-end'   => '0%',
                '--segment-2-end'   => '0%',
                '--segment-3-end'   => '0%',
                '--segment-4-end'   => '100%',
                '--segment-1-color' => 'transparent',
                '--segment-2-color' => 'transparent',
                '--segment-3-color' => 'transparent',
                '--segment-4-color' => 'transparent',
                '--p'               => '0',
            ];

            $donut_is_empty = true;
            if ($has_admin_summary && $admin_summary_total > 0) {
                $positive_states = array_values(array_filter(
                    $admin_summary_states,
                    static function ($entry) {
                        return isset($entry['count']) && (int) $entry['count'] > 0;
                    }
                ));

                if (! empty($positive_states)) {
                    $donut_is_empty = false;
                    $cumulative = 0.0;
                    $total_positive = count($positive_states);
                    foreach ($positive_states as $index => $entry) {
                        $count = isset($entry['count']) ? (int) $entry['count'] : 0;
                        $cumulative += ($count / max(1, $admin_summary_total)) * 100;
                        $end = ($index === $total_positive - 1)
                            ? 100
                            : max(0, min(100, $cumulative));
                        $segment_index = $index + 1;
                        $donut_style_map["--segment-{$segment_index}-end"]   = number_format($end, 2, '.', '') . '%';
                        $donut_style_map["--segment-{$segment_index}-color"] = $state_color_vars[$entry['value']] ?? 'transparent';
                    }

                    for ($segment_index = $total_positive + 1; $segment_index <= 4; $segment_index++) {
                        if (! isset($donut_style_map["--segment-{$segment_index}-end"])) {
                            $donut_style_map["--segment-{$segment_index}-end"] = '100%';
                        }
                        $donut_style_map["--segment-{$segment_index}-color"] = 'transparent';
                    }
                }
            }

            $donut_style_attr = '';
            foreach ($donut_style_map as $property => $value) {
                $donut_style_attr .= $property . ':' . $value . ';';
            }

            $legend_items = [];
            foreach ($admin_summary_states as $entry) {
                $value   = isset($entry['value']) ? (string) $entry['value'] : '';
                $count   = isset($entry['count']) ? (int) $entry['count'] : 0;
                $label   = isset($entry['label']) ? (string) $entry['label'] : '';
                $percent = $admin_summary_total > 0 ? round(($count / $admin_summary_total) * 100) : 0;
                $legend_items[] = [
                    'value'   => $value,
                    'label'   => $label,
                    'count'   => $count,
                    'percent' => $percent,
                    'color'   => $state_color_vars[$value] ?? 'transparent',
                ];
            }

            $action_arrow_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';
            ?>
            <section
                class="guarantee-admin-summary<?php echo $has_admin_summary ? '' : ' is-loading'; ?>"
                data-admin-summary
                data-context="<?php echo esc_attr($admin_summary_context_key); ?>"
                data-loaded="<?php echo $has_admin_summary ? '1' : '0'; ?>"
                data-loading="<?php echo $has_admin_summary ? '0' : '1'; ?>">
                <header class="guarantee-admin-summary__header">
                    <h4 class="guarantee-admin-summary__title"><?php esc_html_e('Resumen de Garantías', 'garantias-online-360vo'); ?></h4>
                    <div class="guarantee-admin-summary__controls">
                        <fieldset class="guarantee-admin-summary__context" data-admin-summary-context role="radiogroup" aria-label="<?php esc_attr_e('Cambiar periodo', 'garantias-online-360vo'); ?>">
                            <input
                                class="guarantee-admin-summary__context-input"
                                type="radio"
                                name="<?php echo esc_attr($summary_context_base); ?>"
                                id="<?php echo esc_attr($summary_global_id); ?>"
                                value="year"
                                data-admin-summary-context-toggle
                                <?php checked($admin_summary_context_key, 'year'); ?>>
                            <label class="guarantee-admin-summary__context-label" for="<?php echo esc_attr($summary_global_id); ?>">
                                <?php esc_html_e('Global', 'garantias-online-360vo'); ?>
                            </label>
                            <input
                                class="guarantee-admin-summary__context-input"
                                type="radio"
                                name="<?php echo esc_attr($summary_context_base); ?>"
                                id="<?php echo esc_attr($summary_month_id); ?>"
                                value="month"
                                data-admin-summary-context-toggle
                                <?php checked($admin_summary_context_key, 'month'); ?>>
                            <label class="guarantee-admin-summary__context-label" for="<?php echo esc_attr($summary_month_id); ?>">
                                <?php esc_html_e('Mensual', 'garantias-online-360vo'); ?>
                            </label>
                        </fieldset>
                        <button type="button" class="guarantee-admin-summary__help" data-admin-summary-help aria-label="<?php esc_attr_e('Mostrar ayuda', 'garantias-online-360vo'); ?>">
                            <?php echo Svg::icon('help'); ?>
                        </button>
                    </div>
                </header>
                <div class="guarantee-admin-summary__body">
                    <section class="visualization-section">
                        <div class="donut-chart<?php echo $donut_is_empty ? ' is-empty' : ''; ?>" data-admin-summary-donut style="<?php echo esc_attr($donut_style_attr); ?>">
                            <div class="chart-center">
                                <div class="chart-total" data-admin-summary-total data-value="<?php echo esc_attr($has_admin_summary ? $admin_summary_total : 0); ?>">
                                    <?php echo $has_admin_summary ? esc_html($format_summary_number($admin_summary_total)) : '—'; ?>
                                </div>
                                <div class="chart-label" data-admin-summary-label data-default-label="<?php esc_attr_e('Total', 'garantias-online-360vo'); ?>">
                                    <?php echo $has_admin_summary && $admin_summary_label !== '' ? esc_html($admin_summary_label) : esc_html__('Total', 'garantias-online-360vo'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="chart-legend" data-admin-summary-states>
                            <?php if ($has_admin_summary) : ?>
                                <?php foreach ($legend_items as $item) : ?>
                                    <div class="legend-item" data-state="<?php echo esc_attr($item['value']); ?>">
                                        <span class="legend-color" style="background-color: <?php echo esc_attr($item['color']); ?>"></span>
                                        <div class="legend-info">
                                            <span class="legend-label"><?php echo esc_html($item['label']); ?></span>
                                            <span class="legend-value"><?php echo esc_html(number_format_i18n($item['percent'])); ?>%</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div class="legend-item is-loading"></div>
                                <div class="legend-item is-loading"></div>
                                <div class="legend-item is-loading"></div>
                                <div class="legend-item is-loading"></div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <section class="actions-section">
                        <h3 class="actions-section-title"><?php esc_html_e('Acciones requeridas', 'garantias-online-360vo'); ?></h3>
                        <ul class="actions-list" data-admin-summary-actions>
                            <?php if ($has_admin_summary) : ?>
                                <?php foreach ($admin_summary_actions as $action) : ?>
                                    <?php
                                    $count   = isset($action['pending']['count']) ? (int) $action['pending']['count'] : 0;
                                    $amount  = $action['pending']['amount'] ?? 0;
                                    $subtitle = $count > 0
                                        ? $format_summary_guarantees($count) . ' · ' . $format_summary_currency($amount)
                                        : __('Sin pendientes', 'garantias-online-360vo');
                                    ?>
                                    <li
                                        class="action-item<?php echo $count === 0 ? ' is-empty' : ''; ?>"
                                        data-summary-action="<?php echo esc_attr($action['key']); ?>"
                                        data-filter="<?php echo esc_attr($action['filter']); ?>">
                                        <span class="action-icon" aria-hidden="true" style="background-color: <?php echo esc_attr($action['accent']); ?>">
                                            <?php echo $action['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </span>
                                        <div class="action-details">
                                            <span class="action-label"><?php echo esc_html($action['label']); ?></span>
                                            <span class="action-sublabel"><?php echo esc_html($subtitle); ?></span>
                                        </div>
                                        <span class="action-cta" aria-hidden="true">
                                            <?php echo $action_arrow_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <li class="action-item is-loading"></li>
                                <li class="action-item is-loading"></li>
                                <li class="action-item is-loading"></li>
                            <?php endif; ?>
                        </ul>
                    </section>
                </div>
                <div class="guarantee-admin-summary__error" data-admin-summary-error hidden>
                    <p><?php esc_html_e('No hemos podido cargar los datos. Vuelve a intentarlo en unos segundos.', 'garantias-online-360vo'); ?></p>
                </div>
                <?php if ($admin_summary_json !== '') : ?>
                    <script type="application/json" data-admin-summary-preload><?php echo $admin_summary_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php $empty_detail_awaiting = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="no-results">
        <p><?php esc_html_e('Crea una nueva garantía para ver aquí todos sus detalles.', 'garantias-online-360vo'); ?></p>
        <a class="guarantee-detail__cta" href="<?php echo esc_url($new_guarantee_url); ?>">
            <span class="guarantee-detail__cta-icon" aria-hidden="true">
                <?php echo Svg::icon('plus'); ?>
            </span>
            <span class="guarantee-detail__cta-label"><?php esc_html_e('Nueva Garantía', 'garantias-online-360vo'); ?></span>
        </a>
    </div>
    <?php $empty_detail_no_results = ob_get_clean(); ?>

    <aside class="guarantee-detail" style="view-transition-name: resume-derecha">
        <!-- Panel 1: mensaje cuando no hay selección -->
        <div class="guarantee-detail__panel active" id="detail-panel-1">
            <?php echo $empty_detail_awaiting; ?>
        </div>

        <!-- Panel 2: se rellenará desde JS -->
        <div class="guarantee-detail__panel" id="detail-panel-2"></div>
    </aside>

    <div class="guarantee-detail__empty-templates" hidden>
        <div data-empty-template="awaiting"><?php echo $empty_detail_awaiting; ?></div>
        <div data-empty-template="no-results"><?php echo $empty_detail_no_results; ?></div>
    </div>



</div>

<div class="pdf-modal" role="dialog" aria-modal="true" aria-labelledby="pdf-modal-title">
    <div class="pdf-modal__content">
        <button class="pdf-modal__close" aria-label="<?php esc_attr_e('Cerrar previsualización', 'garantias-online-360vo'); ?>">&times;</button>
        <div class="pdf-modal__header">
            <div class="pdf-modal__title-container">
                <h2 id="pdf-modal-title"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h2>
                <h3 class="pdf-modal-subttitle"></h3>
            </div>
            <ul class="pdf-modal__docs-list detail__docs-list">
                <!-- Se rellenará por JS clonando los enlaces -->
            </ul>
        </div>

        <div class="pdf-modal__body">
            <iframe class="pdf-modal__iframe" src="" title="<?php esc_attr_e('Vista previa de documento', 'garantias-online-360vo'); ?>"></iframe>
            <div class="pdf-modal__spinner" aria-hidden="true">
                <div class="spinner"></div>
            </div>
            <div class="pdf-modal__upload" hidden>
                <div class="pdf-modal__upload-inner">
                    <h3><?php esc_html_e('Añadir documento', 'garantias-online-360vo'); ?></h3>
                    <p><?php esc_html_e('Aquí el sistema para subir documentación.', 'garantias-online-360vo'); ?></p>
                </div>
            </div>
            <a class="pdf-modal__download" href="#" download aria-label="<?php esc_attr_e('Descargar PDF', 'garantias-online-360vo'); ?>">
                <?php echo Svg::icon('download', 'pdf-modal__download-icon'); ?>
                <span class="pdf-modal__download-text">
                    <?php esc_html_e('Descargar PDF', 'garantias-online-360vo'); ?>
                </span>
            </a>
            <div class="pdf-modal__nav">
                <button type="button" class="pdf-modal__nav-btn pdf-modal__nav-btn--prev" disabled>
                    <?php esc_html_e('Anterior', 'garantias-online-360vo'); ?>
                </button>
                <button type="button" class="pdf-modal__nav-btn pdf-modal__nav-btn--next" disabled>
                    <?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="confirm-modal" aria-hidden="true">
    <div class="confirm-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <button type="button" class="confirm-modal__close" aria-label="<?php esc_attr_e('Cerrar confirmación', 'garantias-online-360vo'); ?>">&times;</button>
        <div class="confirm-modal__intro">
            <h2 id="confirm-modal-title" class="confirm-modal__title"></h2>
            <p class="confirm-modal__subtitle"></p>
            <p class="confirm-modal__message"></p>
            <p class="confirm-modal__note" hidden></p>
        </div>
        <div class="confirm-modal__upload" hidden>
            <h3 class="confirm-modal__upload-title"><?php esc_html_e('Adjuntar justificante de pago', 'garantias-online-360vo'); ?></h3>
            <label class="confirm-modal__file-control">
                <input type="file" class="confirm-modal__file-input" accept=".pdf,.jpg,.jpeg,.png" />
                <span class="confirm-modal__file-cta"><?php esc_html_e('Seleccionar archivo', 'garantias-online-360vo'); ?></span>
                <span class="confirm-modal__file-name" data-empty="<?php esc_attr_e('Ningún archivo seleccionado', 'garantias-online-360vo'); ?>"><?php esc_html_e('Ningún archivo seleccionado', 'garantias-online-360vo'); ?></span>
            </label>
            <p class="confirm-modal__file-help"><?php esc_html_e('Formatos: PDF, JPG o PNG (máx. 10 MB).', 'garantias-online-360vo'); ?></p>
            <p class="confirm-modal__file-error" role="alert" hidden></p>
        </div>
        <label class="confirm-modal__checkbox" hidden>
            <input type="checkbox" class="confirm-modal__checkbox-input" />
            <span class="confirm-modal__checkbox-label"><?php esc_html_e('He revisado esta información y confirmo la operación.', 'garantias-online-360vo'); ?></span>
        </label>
        <div class="confirm-modal__actions">
            <button type="button" class="confirm-modal__btn confirm-modal__btn--cancel"><?php esc_html_e('Cancelar', 'garantias-online-360vo'); ?></button>
            <button type="button" class="confirm-modal__btn confirm-modal__btn--confirm" disabled></button>
        </div>
    </div>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_list_page')
);
?>