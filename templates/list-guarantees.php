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

$show_channel_col = ! $is_professional && ! $is_particular;

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
$show_period_filters      = $show_more_filters && $uses_admin_filters;

$period_current_year      = (int) current_time('Y');
$period_current_month     = (int) current_time('n');
$period_default_from_month = 1;
$period_months            = [];

if ($show_period_filters) {
    for ($month_index = 1; $month_index <= 12; $month_index++) {
        $timestamp = strtotime(sprintf('2000-%02d-01', $month_index));
        $label = $timestamp ? wp_date('F', $timestamp) : '';
        if ($label !== '') {
            if (function_exists('mb_convert_case')) {
                $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
            } else {
                $label = ucwords($label);
            }
        } else {
            $label = sprintf(__('Mes %d', 'garantias-online-360vo'), $month_index);
        }
        $period_months[$month_index] = $label;
    }
}

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
$can_view_summary          = false;
$show_kpi_grid             = $is_admin_user;

$format_summary_number = static function ($value): string {
    $number = is_numeric($value) ? (float) $value : 0.0;

    return number_format($number, 0, ',', '.');
};

$format_summary_currency = static function ($value): string {
    $amount = is_numeric($value) ? (float) $value : 0.0;

    return number_format($amount, 2, ',', '.') . '€';
};

$format_summary_guarantees = static function ($count) use ($format_summary_number): string {
    $count_int  = (int) $count;
    $formatted  = $format_summary_number($count_int);
    $pluralized = _n('%s garantía', '%s garantías', $count_int, 'garantias-online-360vo');
    return sprintf($pluralized, $formatted);
};

if (($is_admin_user || $is_director || $is_professional)
    && class_exists(GuaranteeRestController::class)
    && GuaranteeRestController::can_view_summary()
) {
    $can_view_summary = true;
    $admin_summary_data = GuaranteeRestController::get_summary_data_for_user($current_user);

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
        if ($admin_summary_label !== '') {
            $admin_summary_label = preg_replace('/\s+\d{4}$/', '', $admin_summary_label);
            $admin_summary_label = trim((string) $admin_summary_label);
        }
        $admin_summary_pending = isset($admin_summary_data['pending']) && is_array($admin_summary_data['pending'])
            ? $admin_summary_data['pending']
            : [];

        $pending_draft      = $admin_summary_pending['draft'] ?? [];
        $pending_payment    = $admin_summary_pending['payment'] ?? [];
        $pending_validation = $admin_summary_pending['validation'] ?? [];
        $pending_collect    = $admin_summary_pending['collect'] ?? [];

        $actions_catalog = [
            'draft' => [
                'key'         => 'draft',
                'label'       => __('Sin finalizar', 'garantias-online-360vo'),
                'filter'      => 'sin_finalizar',
                'accent'      => 'var(--admin-summary-state-sin-finalizar)',
                'icon'        => Svg::icon('continue'),
                'pending'     => $pending_draft,
                'show_amount' => false,
            ],
            'payment' => [
                'key'         => 'payment',
                'label'       => __('Pendientes de pago', 'garantias-online-360vo'),
                'filter'      => 'pendiente_pago',
                'accent'      => 'var(--admin-summary-action-payment)',
                'icon'        => Svg::icon('warning'),
                'pending'     => $pending_payment,
            ],
            'validation' => [
                'key'         => 'validation',
                'label'       => __('Pendientes de verificar transferencia', 'garantias-online-360vo'),
                'filter'      => 'validacion_pendiente',
                'accent'      => 'var(--admin-summary-action-validation)',
                'icon'        => Svg::icon('transfer_verify'),
                'pending'     => $pending_validation,
            ],
            'collect' => [
                'key'         => 'collect',
                'label'       => __('Pendientes de cobrar domiciliación', 'garantias-online-360vo'),
                'filter'      => 'pendiente_cobro',
                'accent'      => 'var(--admin-summary-action-collect)',
                'icon'        => Svg::icon('payment'),
                'pending'     => $pending_collect,
            ],
        ];

        $allowed_action_keys = $is_professional
            ? ['draft', 'payment']
            : array_keys($actions_catalog);

        $admin_summary_actions = [];
        foreach ($allowed_action_keys as $action_key) {
            if (isset($actions_catalog[$action_key])) {
                $admin_summary_actions[] = $actions_catalog[$action_key];
            }
        }

        $admin_summary_json = wp_json_encode($admin_summary_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
?>


<!-- 1. FILTROS -->
<div
    class="guarantees-list__filters"
    style="view-transition-name: filtros"
    data-mobile-open="false"
    data-mobile-search-open="false">
    <div class="guarantees-list__filters-modal" data-mobile-filters-overlay>
        <div class="guarantees-list__filters-modal-backdrop" data-mobile-filters-dismiss></div>

        <div
            class="guarantees-list__filters-main"
            id="guarantees-list-base-filters"
            data-mobile-filters-panel
            aria-labelledby="guarantees-list-filters-title"
            tabindex="-1">
            <div class="guarantees-list__filters-header">
                <h2 class="guarantees-list__filters-title" id="guarantees-list-filters-title">
                    <?php esc_html_e('Filtros', 'garantias-online-360vo'); ?>
                </h2>
                <button
                    type="button"
                    class="guarantees-list__filters-close"
                    data-mobile-filters-dismiss>
                    <?php echo Svg::icon('cerrar', 'guarantees-list__filters-close-icon'); ?>
                    <span class="screen-reader-text">
                        <?php esc_html_e('Cerrar filtros', 'garantias-online-360vo'); ?>
                    </span>
                </button>
            </div>

            <div class="guarantees-list__filters-body">
                <div class="guarantees-list__filters-controls">
                    <div class="guarantees-list__filters-search-slot" data-desktop-search-slot></div>

                    <select
                        id="guarantees-filter-estado"
                        name="estado"
                        class="guarantees-list__filter"
                        data-filter="estado"
                        aria-label="<?php esc_attr_e('Estado', 'garantias-online-360vo'); ?>">
                        <option value=""><?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?></option>
                    </select>

                    <?php if ($show_plan_in_main) : ?>
                        <select
                            id="guarantees-filter-plan-main"
                            name="plan"
                            class="guarantees-list__filter"
                            data-filter="plan"
                            aria-label="<?php esc_attr_e('Coberturas', 'garantias-online-360vo'); ?>">
                            <option value=""><?php esc_html_e('Todas las coberturas', 'garantias-online-360vo'); ?>
                            </option>
                        </select>
                    <?php endif; ?>

                    <?php if ($show_channel_select) : ?>
                        <select
                            id="guarantees-filter-canal"
                            name="canal"
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
                                id="guarantees-filter-cliente"
                                name="cliente"
                                class="guarantees-list__filter"
                                data-filter="cliente"
                                aria-label="<?php esc_attr_e('Empresas', 'garantias-online-360vo'); ?>">
                                <option value="">
                                    <?php esc_html_e('Todos los clientes', 'garantias-online-360vo'); ?>
                                </option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

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

                <?php if ($show_more_filters) : ?>
                    <div class="guarantees-list__filters-advanced" data-advanced-panel hidden>
                        <div class="guarantees-list__filters-advanced-grid">
                            <?php if ($show_plan_in_advanced) : ?>
                                <select
                                    id="guarantees-filter-plan-advanced"
                                    name="plan"
                                    class="guarantees-list__filter"
                                    data-filter="plan"
                                    aria-label="<?php esc_attr_e('Coberturas', 'garantias-online-360vo'); ?>">
                                    <option value=""><?php esc_html_e('Todas las coberturas', 'garantias-online-360vo'); ?></option>
                                </select>
                            <?php endif; ?>

                            <?php if ($show_payment_in_advanced) : ?>
                                <select
                                    id="guarantees-filter-payment"
                                    name="payment"
                                    class="guarantees-list__filter"
                                    data-filter="payment"
                                    aria-label="<?php esc_attr_e('Método de pago', 'garantias-online-360vo'); ?>">
                                    <option value=""><?php esc_html_e('Todos los métodos de pago', 'garantias-online-360vo'); ?></option>
                                </select>
                            <?php endif; ?>

                            <?php if ($show_commercial_select) : ?>
                                <select
                                    id="guarantees-filter-commercial"
                                    name="commercial"
                                    class="guarantees-list__filter"
                                    data-filter="commercial"
                                    aria-label="<?php esc_attr_e('Garantías por comercial', 'garantias-online-360vo'); ?>">
                                    <option value=""><?php esc_html_e('Todos los comerciales', 'garantias-online-360vo'); ?></option>
                                </select>
                            <?php endif; ?>

                            <?php if ($show_period_filters) : ?>
                                <select
                                    id="guarantees-filter-year"
                                    name="year"
                                    class="guarantees-list__filter"
                                    data-filter="year"
                                    data-all-label="<?php esc_attr_e('Todos los años', 'garantias-online-360vo'); ?>"
                                    aria-label="<?php esc_attr_e('Año de inicio', 'garantias-online-360vo'); ?>">
                                    <option value=""><?php esc_html_e('Todos los años', 'garantias-online-360vo'); ?></option>
                                    <?php if (! empty($period_current_year)) : ?>
                                        <option value="<?php echo esc_attr($period_current_year); ?>"><?php echo esc_html($period_current_year); ?></option>
                                    <?php endif; ?>
                                </select>
                                <div class="guarantees-list__filter-field">
                                    <label class="guarantees-list__filter-field-label" for="guarantees-filter-month-from">
                                        <?php esc_html_e('Desde', 'garantias-online-360vo'); ?>
                                    </label>
                                    <select
                                        id="guarantees-filter-month-from"
                                        name="month-from"
                                        class="guarantees-list__filter"
                                        data-filter="month-from">
                                        <?php foreach ($period_months as $month_number => $month_label) : ?>
                                            <option value="<?php echo esc_attr($month_number); ?>" <?php selected($month_number, $period_default_from_month); ?>>
                                                <?php echo esc_html($month_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="guarantees-list__filter-field">
                                    <label class="guarantees-list__filter-field-label" for="guarantees-filter-month-to">
                                        <?php esc_html_e('Hasta', 'garantias-online-360vo'); ?>
                                    </label>
                                    <select
                                        id="guarantees-filter-month-to"
                                        name="month-to"
                                        class="guarantees-list__filter"
                                        data-filter="month-to">
                                        <?php foreach ($period_months as $month_number => $month_label) : ?>
                                            <option value="<?php echo esc_attr($month_number); ?>" <?php selected($month_number, $period_current_month); ?>>
                                                <?php echo esc_html($month_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="guarantees-container">
    <!-- 2. LISTA: tabla semántica con columna “Garantía” al final y “Canal de venta” en vendedor -->
    <section class="guarantees-list">
        <div class="guarantees-cards" data-mobile-cards>
            <div class="guarantees-cards__list" data-mobile-cards-list></div>
            <div class="guarantees-cards__empty" data-mobile-cards-empty hidden>
                <p><?php esc_html_e('Todavía no hay garantías.', 'garantias-online-360vo'); ?></p>
            </div>
        </div>

        <div class="guarantees-table__scroll">
            <table class="guarantees-table" style="view-transition-name: garantias-table">
                <thead>
                    <tr>
                        <th>
                            <span class="guarantees-table__header-label">
                                <?php esc_html_e('Vehículo',  'garantias-online-360vo'); ?>
                            </span>
                        </th>
                        <th>
                            <span class="guarantees-table__header-label guarantees-table__header-label--sticky">
                                <?php esc_html_e('Validez',   'garantias-online-360vo'); ?>
                            </span>
                        </th>
                        <th>
                            <span class="guarantees-table__header-label guarantees-table__header-label--sticky">
                                <?php
                                if ($show_channel_col) {
                                    esc_html_e('Canal de venta', 'garantias-online-360vo');
                                } else {
                                    esc_html_e('Cliente', 'garantias-online-360vo');
                                }
                                ?>
                            </span>
                        </th>
                        <th>
                            <span class="guarantees-table__header-label guarantees-table__header-label--sticky">
                                <?php esc_html_e('Estado',    'garantias-online-360vo'); ?>
                            </span>
                        </th>
                        <th>
                            <span class="guarantees-table__header-label guarantees-table__header-label--sticky">
                                <?php esc_html_e('Garantía',  'garantias-online-360vo'); ?>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody data-current-page="0" data-total-pages="">
                    <!-- Aquí sólo los ítems cargados dinámicamente -->
                </tbody>
            </table>
            <!-- Fila de carga fija, fuera del tbody para que no se elimine al vaciar -->

        </div>
        <div
            class="guarantees-list__result-message"
            data-search-result-message
            aria-live="polite"
            aria-hidden="true"
            hidden
        ></div>
        <!-- <div class="scroll_up scroll_up--list">
            <button>^</button>
        </div> -->
        <!-- Aquí el sentinel -->
        <div id="scroll-end" class="scroll-sentinel" aria-hidden="true">
            <div class="spinner" aria-hidden="true">
                <div class="spinner__inner" aria-hidden="true"></div>
            </div>
        </div>
    </section>

    <!-- 3. DETALLE: dos paneles -->
    <?php ob_start(); ?>
    <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="awaiting">
        <p class="guarantee-detail__hint">
            <span class="guarantee-detail__hint-arrow" aria-hidden="true">
                <?php echo Svg::icon('flecha_izquierda'); ?>
            </span>
            <?php esc_html_e('Haz clic en una garantía para consultar la información completa.', 'garantias-online-360vo'); ?>
        </p>
        <?php if ($can_view_summary) : ?>
            <?php
            $summary_context_base = uniqid('summary-context-');
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

            $context_amount_entries = isset($admin_summary_default['amounts']) && is_array($admin_summary_default['amounts'])
                ? $admin_summary_default['amounts']
                : [];
            $context_amounts = [];
            foreach ($context_amount_entries as $amount_entry) {
                $amount_key = isset($amount_entry['value']) ? (string) $amount_entry['value'] : '';
                if ($amount_key === '') {
                    continue;
                }
                $context_amounts[$amount_key] = isset($amount_entry['amount']) ? (float) $amount_entry['amount'] : 0.0;
            }

            $summary_active_amount = $context_amounts['activada'] ?? 0.0;

            $summary_month_name = '';
            if ($admin_summary_context_key === 'month') {
                $raw_month_name = isset($admin_summary_default['month_name'])
                    ? (string) $admin_summary_default['month_name']
                    : '';
                if ($raw_month_name !== '') {
                    $clean_month = preg_replace('/\s+\d{4}$/', '', $raw_month_name);
                    $summary_month_name = trim((string) $clean_month);
                }
            }

            $summary_amount_label = $admin_summary_context_key === 'year'
                ? __('Valor acumulado', 'garantias-online-360vo')
                : ($summary_month_name !== ''
                    ? sprintf('%s %s', __('Acumulado', 'garantias-online-360vo'), $summary_month_name)
                    : __('Acumulado mensual', 'garantias-online-360vo'));

            $summary_count_label = $admin_summary_context_key === 'year'
                ? __('Total garantías', 'garantias-online-360vo')
                : ($summary_month_name !== ''
                    ? sprintf('%s %s', __('Garantías', 'garantias-online-360vo'), $summary_month_name)
                    : __('Garantías este mes', 'garantias-online-360vo'));

            $summary_trends = isset($admin_summary_default['trends']) && is_array($admin_summary_default['trends'])
                ? $admin_summary_default['trends']
                : [];

            $amount_trend = isset($summary_trends['amount']) && is_array($summary_trends['amount'])
                ? $summary_trends['amount']
                : [];
            $count_trend = isset($summary_trends['count']) && is_array($summary_trends['count'])
                ? $summary_trends['count']
                : [];

            $trend_icon_up = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/></svg>';
            $trend_icon_down = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z"/></svg>';

            $amount_trend_direction = isset($amount_trend['direction']) ? (string) $amount_trend['direction'] : 'neutral';
            $amount_trend_value = isset($amount_trend['formatted']) ? (string) $amount_trend['formatted'] : '0%';
            $amount_trend_suffix = isset($amount_trend['label']) ? (string) $amount_trend['label'] : ($admin_summary_context_key === 'year'
                ? __('vs año ant.', 'garantias-online-360vo')
                : __('vs mes ant.', 'garantias-online-360vo'));
            $amount_trend_text = trim($amount_trend_value . ' ' . $amount_trend_suffix);
            if ($amount_trend_text === '') {
                $amount_trend_text = '—';
            }
            $amount_trend_class = 'kpi-trend';
            if ($amount_trend_direction === 'positive') {
                $amount_trend_class .= ' positive';
            } elseif ($amount_trend_direction === 'negative') {
                $amount_trend_class .= ' negative';
            } else {
                $amount_trend_class .= ' neutral';
            }
            $amount_trend_icon = $amount_trend_direction === 'negative' ? $trend_icon_down : $trend_icon_up;

            $count_trend_direction = isset($count_trend['direction']) ? (string) $count_trend['direction'] : 'neutral';
            $count_trend_value = isset($count_trend['formatted']) ? (string) $count_trend['formatted'] : '0%';
            $count_trend_suffix = isset($count_trend['label']) ? (string) $count_trend['label'] : ($admin_summary_context_key === 'year'
                ? __('vs año ant.', 'garantias-online-360vo')
                : __('vs mes ant.', 'garantias-online-360vo'));
            $count_trend_text = trim($count_trend_value . ' ' . $count_trend_suffix);
            if ($count_trend_text === '') {
                $count_trend_text = '—';
            }
            $count_trend_class = 'kpi-trend';
            if ($count_trend_direction === 'positive') {
                $count_trend_class .= ' positive';
            } elseif ($count_trend_direction === 'negative') {
                $count_trend_class .= ' negative';
            } else {
                $count_trend_class .= ' neutral';
            }
            $count_trend_icon = $count_trend_direction === 'negative' ? $trend_icon_down : $trend_icon_up;

            $summary_active_amount_label = $has_admin_summary
                ? $format_summary_currency($summary_active_amount)
                : '—';
            $summary_total_label = $has_admin_summary
                ? $format_summary_number($admin_summary_total)
                : '—';

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
                    <?php if (! $is_professional) : ?>
                        <?php
                        $summary_context_global_id = $summary_context_base . '-global';
                        $summary_context_month_id  = $summary_context_base . '-month';
                        ?>
                        <fieldset class="guarantee-admin-summary__context" data-admin-summary-context role="radiogroup" aria-label="<?php esc_attr_e('Cambiar periodo', 'garantias-online-360vo'); ?>">
                            <input
                                class="guarantee-admin-summary__context-input"
                                type="radio"
                                name="<?php echo esc_attr($summary_context_base); ?>"
                                id="<?php echo esc_attr($summary_context_global_id); ?>"
                                value="year"
                                data-admin-summary-context-toggle
                                <?php checked($admin_summary_context_key, 'year'); ?>>
                            <label class="guarantee-admin-summary__context-label" for="<?php echo esc_attr($summary_context_global_id); ?>">
                                <?php esc_html_e('Global', 'garantias-online-360vo'); ?>
                            </label>
                            <input
                                class="guarantee-admin-summary__context-input"
                                type="radio"
                                name="<?php echo esc_attr($summary_context_base); ?>"
                                id="<?php echo esc_attr($summary_context_month_id); ?>"
                                value="month"
                                data-admin-summary-context-toggle
                                <?php checked($admin_summary_context_key, 'month'); ?>>
                            <label class="guarantee-admin-summary__context-label" for="<?php echo esc_attr($summary_context_month_id); ?>">
                                <?php esc_html_e('Mensual', 'garantias-online-360vo'); ?>
                            </label>
                        </fieldset>
                    <?php endif; ?>
                </header>
                <div class="guarantee-admin-summary__body">
                    <?php if ($show_kpi_grid) : ?>
                        <section class="kpi-grid" data-admin-summary-kpis>
                            <article class="kpi-card" data-admin-summary-kpi="amount">
                                <div class="kpi-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 -960 960 960" fill="var(--summary-accent)">
                                        <path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H360q0 33-23.5 56.5T280-640v160q33 0 56.5 23.5T360-400Zm440 240H120q-33 0-56.5-23.5T40-240v-440h80v440h680v80ZM280-400v-320 320Z" />
                                    </svg>
                                    <span data-admin-summary-kpi-label><?php echo esc_html($summary_amount_label); ?></span>
                                </div>
                                <div
                                    class="kpi-value is-currency"
                                    data-admin-summary-kpi-value
                                    data-format="currency"
                                    data-value="<?php echo esc_attr($summary_active_amount); ?>">
                                    <?php echo esc_html($summary_active_amount_label); ?>
                                </div>
                                <div
                                    class="<?php echo esc_attr($amount_trend_class); ?>"
                                    data-admin-summary-kpi-trend
                                    data-trend-type="amount"
                                    data-direction="<?php echo esc_attr($amount_trend_direction); ?>">
                                    <span class="kpi-trend__icon" data-admin-summary-trend-icon aria-hidden="true">
                                        <?php echo $amount_trend_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                        ?>
                                    </span>
                                    <span data-admin-summary-trend-label><?php echo esc_html($amount_trend_text); ?></span>
                                </div>
                            </article>
                            <article class="kpi-card" data-admin-summary-kpi="count">
                                <div class="kpi-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 -960 960 960" fill="var(--summary-accent)">
                                        <path d="M480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z" />
                                    </svg>
                                    <span data-admin-summary-kpi-label><?php echo esc_html($summary_count_label); ?></span>
                                </div>
                                <div
                                    class="kpi-value"
                                    data-admin-summary-kpi-value
                                    data-format="integer"
                                    data-value="<?php echo esc_attr($admin_summary_total); ?>">
                                    <?php echo esc_html($summary_total_label); ?>
                                </div>
                                <div
                                    class="<?php echo esc_attr($count_trend_class); ?>"
                                    data-admin-summary-kpi-trend
                                    data-trend-type="count"
                                    data-direction="<?php echo esc_attr($count_trend_direction); ?>">
                                    <span class="kpi-trend__icon" data-admin-summary-trend-icon aria-hidden="true">
                                        <?php echo $count_trend_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                                        ?>
                                    </span>
                                    <span data-admin-summary-trend-label><?php echo esc_html($count_trend_text); ?></span>
                                </div>
                            </article>
                        </section>
                    <?php endif; ?>
                    <?php if (! $is_professional) : ?>
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
                    <?php endif; ?>
                    <section class="actions-section">
                        <h3 class="actions-section-title"><?php esc_html_e('Acciones rápidas', 'garantias-online-360vo'); ?></h3>
                        <ul class="actions-list" data-admin-summary-actions>
                            <?php if ($has_admin_summary) : ?>
                                <?php foreach ($admin_summary_actions as $action) : ?>
                                    <?php
                                    $count   = isset($action['pending']['count']) ? (int) $action['pending']['count'] : 0;
                                    $amount  = $action['pending']['amount'] ?? 0;
                                    $show_amount = array_key_exists('show_amount', $action) ? (bool) $action['show_amount'] : true;
                                    $subtitle = $show_amount
                                        ? ($count > 0
                                            ? $format_summary_guarantees($count) . ' · ' . $format_summary_currency($amount)
                                            : __('Sin pendientes', 'garantias-online-360vo'))
                                        : $format_summary_guarantees($count);
                                    ?>
                                    <li
                                        class="action-item<?php echo $count === 0 ? ' is-empty' : ''; ?>"
                                        data-summary-action="<?php echo esc_attr($action['key']); ?>"
                                        data-filter="<?php echo esc_attr($action['filter']); ?>">
                                        <span class="action-icon" aria-hidden="true" style="background-color: <?php echo esc_attr($action['accent']); ?>">
                                            <?php echo $action['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ?>
                                        </span>
                                        <div class="action-details">
                                            <span class="action-label"><?php echo esc_html($action['label']); ?></span>
                                            <span class="action-sublabel"><?php echo esc_html($subtitle); ?></span>
                                        </div>
                                        <span class="action-cta" aria-hidden="true">
                                            <?php echo $action_arrow_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <?php
                                $placeholder_count = count($admin_summary_actions);
                                if ($placeholder_count === 0) {
                                    $placeholder_count = $is_professional ? 2 : 4;
                                }
                                for ($placeholder_index = 0; $placeholder_index < $placeholder_count; $placeholder_index++) :
                                    ?>
                                    <li class="action-item is-loading"></li>
                                <?php endfor; ?>
                            <?php endif; ?>
                        </ul>
                    </section>
                </div>
                <div class="guarantee-admin-summary__error" data-admin-summary-error hidden>
                    <p><?php esc_html_e('No hemos podido cargar los datos. Vuelve a intentarlo en unos segundos.', 'garantias-online-360vo'); ?></p>
                </div>
                <?php if ($admin_summary_json !== '') : ?>
                    <script type="application/json" data-admin-summary-preload>
                        <?php echo $admin_summary_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                        ?>
                    </script>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php $empty_detail_awaiting = ob_get_clean(); ?>

    <?php ob_start(); ?>
    <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="no-results">
        <p>
            <?php
            printf(
                /* translators: %s: link to create the first guarantee. */
                wp_kses(
                    __('%s para ver aquí todos sus detalles.', 'garantias-online-360vo'),
                    [
                        'a' => [
                            'href'  => [],
                            'class' => [],
                        ],
                    ]
                ),
                sprintf(
                    '<a class="guarantee-detail__cta-link" href="%s">%s</a>',
                    esc_url($new_guarantee_url),
                    esc_html__('Contrata tu primera garantía', 'garantias-online-360vo')
                )
            );
            ?>
        </p>
    </div>
    <?php $empty_detail_no_results = ob_get_clean(); ?>

    <aside
        class="guarantee-detail"
        style="view-transition-name: resume-derecha"
        data-mobile-open="false"
        aria-hidden="true">
        <div class="guarantee-detail__backdrop" data-mobile-detail-dismiss></div>
        <div
            class="guarantee-detail__dialog"
            data-detail-dialog
            role="dialog"
            aria-modal="true"
            aria-label="<?php esc_attr_e('Detalles de la garantía', 'garantias-online-360vo'); ?>"
            tabindex="-1">
            <button
                type="button"
                class="guarantee-detail__close"
                data-mobile-detail-dismiss>
                <?php echo Svg::icon('cerrar', 'guarantee-detail__close-icon'); ?>
                <span class="screen-reader-text">
                    <?php esc_html_e('Cerrar detalles de la garantía', 'garantias-online-360vo'); ?>
                </span>
            </button>

            <!-- Panel 1: mensaje cuando no hay selección -->
            <div class="guarantee-detail__panel active" id="detail-panel-1">
                <?php echo $empty_detail_awaiting; ?>
            </div>

            <!-- Panel 2: se rellenará desde JS -->
            <div class="guarantee-detail__panel" id="detail-panel-2"></div>
        </div>
    </aside>

    <div class="guarantee-detail__empty-templates" hidden>
        <div data-empty-template="awaiting"><?php echo $empty_detail_awaiting; ?></div>
        <div data-empty-template="no-results"><?php echo $empty_detail_no_results; ?></div>
    </div>

    <div
        class="guarantee-management"
        data-management-modal
        data-state="closed"
        aria-hidden="true">
        <div class="guarantee-management__backdrop" data-management-dismiss></div>
        <div
            class="guarantee-management__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="guarantee-management-title"
            data-management-dialog
            tabindex="-1">
            <div class="guarantee-management__shell">
                <header class="guarantee-management__sticky">
                    <div class="guarantee-management__topline">
                        <h2 class="guarantee-management__title" id="guarantee-management-title">
                            <?php esc_html_e('Gestionar garantía', 'garantias-online-360vo'); ?>
                        </h2>
                        <button type="button" class="guarantee-management__close" data-management-dismiss>
                            <?php echo Svg::icon('cerrar', 'guarantee-management__close-icon'); ?>
                            <span class="screen-reader-text">
                                <?php esc_html_e('Cerrar panel de gestión', 'garantias-online-360vo'); ?>
                            </span>
                        </button>
                    </div>
                    <div class="guarantee-management__summary">
                        <div class="guarantee-management__plate" data-management-plate>— — —</div>
                        <div class="guarantee-management__status" data-management-status>
                            <span class="guarantee-management__status-dot" aria-hidden="true"></span>
                            <span data-management-status-text><?php esc_html_e('Estado pendiente', 'garantias-online-360vo'); ?></span>
                        </div>
                    </div>
                    <nav
                        class="guarantee-management__tabs"
                        role="tablist"
                        aria-label="<?php esc_attr_e('Opciones de gestión', 'garantias-online-360vo'); ?>"
                        data-management-tabs>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="true"
                            aria-controls="management-panel-actions"
                            data-management-tab="actions">
                            <?php esc_html_e('Acciones', 'garantias-online-360vo'); ?>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="false"
                            aria-controls="management-panel-notes"
                            data-management-tab="notes">
                            <?php esc_html_e('Notas', 'garantias-online-360vo'); ?>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="false"
                            aria-controls="management-panel-breakdown"
                            data-management-tab="breakdown">
                            <?php esc_html_e('Desglose', 'garantias-online-360vo'); ?>
                        </button>
                    </nav>
                </header>
                <div class="guarantee-management__panels">
                    <section
                        class="guarantee-management__panel is-active"
                        id="management-panel-actions"
                        role="tabpanel"
                        data-management-panel="actions">
                        <div class="management-actions" role="list">
                            <button
                                type="button"
                                class="management-actions__item"
                                data-management-action="certificate-error">
                                <span class="management-actions__icon" aria-hidden="true">
                                    <?php echo Svg::icon('check_shield'); ?>
                                </span>
                                <span class="management-actions__copy">
                                    <strong><?php esc_html_e('Revisar certificado', 'garantias-online-360vo'); ?></strong>
                                    <span><?php esc_html_e('Detecta y corrige incidencias del documento actual.', 'garantias-online-360vo'); ?></span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="management-actions__item"
                                data-management-action="email-client">
                                <span class="management-actions__icon" aria-hidden="true">
                                    <?php echo Svg::icon('email'); ?>
                                </span>
                                <span class="management-actions__copy">
                                    <strong><?php esc_html_e('Enviar e-mail al cliente', 'garantias-online-360vo'); ?></strong>
                                    <span><?php esc_html_e('Activa las plantillas oficiales sin salir del panel.', 'garantias-online-360vo'); ?></span>
                                </span>
                            </button>
                        </div>
                        <div class="management-danger" role="list">
                            <button type="button" class="management-danger__link" data-management-action="cancel-for-nonpayment">
                                <span aria-hidden="true"><?php echo Svg::icon('warning'); ?></span>
                                <span><?php esc_html_e('Cancelar garantía por impago', 'garantias-online-360vo'); ?></span>
                            </button>
                            <button type="button" class="management-danger__link" data-management-action="delete-guarantee">
                                <span aria-hidden="true"><?php echo Svg::icon('delete'); ?></span>
                                <span><?php esc_html_e('Eliminar garantía', 'garantias-online-360vo'); ?></span>
                            </button>
                        </div>
                    </section>
                    <section
                        class="guarantee-management__panel"
                        id="management-panel-notes"
                        role="tabpanel"
                        data-management-panel="notes"
                        hidden>
                        <div class="management-thread">
                            <div class="management-thread__composer">
                                <label class="management-thread__field">
                                    <span><?php esc_html_e('Tipo de nota', 'garantias-online-360vo'); ?></span>
                                    <select name="management-note-type">
                                        <option><?php esc_html_e('Seguimiento', 'garantias-online-360vo'); ?></option>
                                        <option><?php esc_html_e('Aviso al cliente', 'garantias-online-360vo'); ?></option>
                                        <option><?php esc_html_e('Interna', 'garantias-online-360vo'); ?></option>
                                    </select>
                                </label>
                                <label class="management-thread__field">
                                    <span><?php esc_html_e('Mensaje', 'garantias-online-360vo'); ?></span>
                                    <textarea
                                        name="management-note-message"
                                        rows="4"
                                        placeholder="<?php esc_attr_e('Escribe un mensaje breve para el equipo', 'garantias-online-360vo'); ?>"
                                    ></textarea>
                                </label>
                                <div class="management-thread__actions">
                                    <button type="button" class="management-thread__submit">
                                        <?php esc_html_e('Publicar nota', 'garantias-online-360vo'); ?>
                                    </button>
                                </div>
                            </div>
                            <ul class="management-thread__list">
                                <li class="management-thread__note">
                                    <header>
                                        <span class="management-thread__tag"><?php esc_html_e('Interna', 'garantias-online-360vo'); ?></span>
                                        <div class="management-thread__meta">
                                            <span class="management-thread__author">Laura Méndez</span>
                                            <time datetime="2024-03-20T10:24">20/03/2024 · 10:24h</time>
                                        </div>
                                    </header>
                                    <p class="management-thread__body">
                                        <?php esc_html_e('Revisado el cobro con el cliente. Esperamos justificante antes de 48h.', 'garantias-online-360vo'); ?>
                                    </p>
                                </li>
                                <li class="management-thread__note">
                                    <header>
                                        <span class="management-thread__tag"><?php esc_html_e('Seguimiento', 'garantias-online-360vo'); ?></span>
                                        <div class="management-thread__meta">
                                            <span class="management-thread__author">Diego Álvarez</span>
                                            <time datetime="2024-03-18T16:05">18/03/2024 · 16:05h</time>
                                        </div>
                                    </header>
                                    <p class="management-thread__body">
                                        <?php esc_html_e('Se compartió la actualización con el taller y quedó registrada la llamada.', 'garantias-online-360vo'); ?>
                                    </p>
                                </li>
                            </ul>
                        </div>
                    </section>
                    <section
                        class="guarantee-management__panel"
                        id="management-panel-breakdown"
                        role="tabpanel"
                        data-management-panel="breakdown"
                        hidden>
                        <div class="management-breakdown">
                            <div class="management-breakdown__card management-breakdown__vehicle">
                                <h3><?php esc_html_e('Vehículo', 'garantias-online-360vo'); ?></h3>
                                <dl>
                                    <dt><?php esc_html_e('Marca', 'garantias-online-360vo'); ?></dt>
                                    <dd>Peugeot</dd>
                                    <dt><?php esc_html_e('Modelo', 'garantias-online-360vo'); ?></dt>
                                    <dd>3008 Hybrid</dd>
                                    <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                                    <dd>3981 LXD</dd>
                                    <dt><?php esc_html_e('VIN', 'garantias-online-360vo'); ?></dt>
                                    <dd>VF3CUHNZ6MS203811</dd>
                                </dl>
                            </div>
                            <div class="management-breakdown__card">
                                <h3><?php esc_html_e('Desglose económico', 'garantias-online-360vo'); ?></h3>
                                <ul class="management-breakdown__list">
                                    <li>
                                        <span><?php esc_html_e('Precio base', 'garantias-online-360vo'); ?></span>
                                        <strong>435,00 €</strong>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Recargos aplicados', 'garantias-online-360vo'); ?></span>
                                        <strong>+ 85,00 €</strong>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Descuentos', 'garantias-online-360vo'); ?></span>
                                        <strong>- 20,00 €</strong>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('IVA (21%)', 'garantias-online-360vo'); ?></span>
                                        <strong>92,15 €</strong>
                                    </li>
                                    <li class="management-breakdown__total">
                                        <span><?php esc_html_e('Total a facturar', 'garantias-online-360vo'); ?></span>
                                        <strong>592,15 €</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>
                <footer class="guarantee-management__footer">
                    <button type="button" class="guarantee-management__btn guarantee-management__btn--ghost" data-management-dismiss>
                        <?php esc_html_e('Volver al detalle', 'garantias-online-360vo'); ?>
                    </button>
                    <button type="button" class="guarantee-management__btn guarantee-management__btn--primary">
                        <?php esc_html_e('Registrar acción', 'garantias-online-360vo'); ?>
                    </button>
                </footer>
            </div>
        </div>
    </div>


    <div class="guarantees-mobile-search" data-mobile-search-panel>
        <div class="guarantees-mobile-search__slot" data-mobile-search-slot>
            <div class="guarantees-list__search-container" data-search-field>
                <span class="guarantees-list__search-icon" aria-hidden="true">
                    <?php echo Svg::icon('search'); ?>
                </span>
                <input
                    type="text"
                    class="guarantees-list__search"
                    placeholder="<?php esc_attr_e('Buscar vehículo o matrícula…', 'garantias-online-360vo'); ?>"
                    aria-label="<?php esc_attr_e('Buscar vehículo o matrícula', 'garantias-online-360vo'); ?>"
                    id="buscador_mis_garantias">
                <span class="guarantees-list__close-icon" aria-hidden="true">
                    <?php echo Svg::icon('cerrar'); ?>
                </span>
            </div>
        </div>
    </div>

    <nav class="guarantees-bottom-bar" data-mobile-bottom-bar>
        <button
            type="button"
            class="guarantees-bottom-bar__button"
            data-mobile-nav-action="summary"
            aria-pressed="false">
            <span class="guarantees-bottom-bar__icon" aria-hidden="true">
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--default">
                    <?php echo Svg::icon('summary_outline'); ?>
                </span>
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--active">
                    <?php echo Svg::icon('summary_filled'); ?>
                </span>
            </span>
            <span class="guarantees-bottom-bar__label">
                <?php esc_html_e('Resumen', 'garantias-online-360vo'); ?>
            </span>
        </button>
        <button
            type="button"
            class="guarantees-bottom-bar__button"
            data-mobile-nav-action="search"
            data-mobile-search-toggle
            aria-pressed="false">
            <span class="guarantees-bottom-bar__icon" aria-hidden="true">
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--default">
                    <?php echo Svg::icon('search'); ?>
                </span>
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--active">
                    <?php echo Svg::icon('search_filled'); ?>
                </span>
            </span>
            <span class="guarantees-bottom-bar__label">
                <?php esc_html_e('Buscar', 'garantias-online-360vo'); ?>
            </span>
        </button>
        <button
            type="button"
            class="guarantees-bottom-bar__button"
            data-mobile-nav-action="filters"
            data-mobile-filters-toggle
            aria-pressed="false"
            aria-haspopup="dialog"
            aria-controls="guarantees-list-base-filters"
            aria-expanded="false">
            <span class="guarantees-bottom-bar__icon" aria-hidden="true">
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--default">
                    <?php echo Svg::icon('filter_funnel'); ?>
                </span>
                <span class="guarantees-bottom-bar__icon-graphic guarantees-bottom-bar__icon-graphic--active">
                    <?php echo Svg::icon('filter_funnel_filled'); ?>
                </span>
            </span>
            <span class="guarantees-bottom-bar__label">
                <?php esc_html_e('Filtros', 'garantias-online-360vo'); ?>
            </span>
        </button>
    </nav>
    <a
        class="guarantees-quick-add is-hidden"
        data-mobile-quick-add
        href="<?php echo esc_url($new_guarantee_url); ?>"
        data-reset-draft
        aria-label="<?php esc_attr_e('Crear nueva garantía', 'garantias-online-360vo'); ?>"
        aria-hidden="true"
        tabindex="-1"
    >
        <span class="guarantees-quick-add__icon" aria-hidden="true">
            <?php echo Svg::icon('new_shield'); ?>
        </span>
        <span class="screen-reader-text">
            <?php esc_html_e('Crear nueva garantía', 'garantias-online-360vo'); ?>
        </span>
    </a>

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
        <?php
        $confirm_checkbox_id   = uniqid('confirm-modal-checkbox-');
        $confirm_checkbox_name = $confirm_checkbox_id . '-field';
        ?>
        <label class="confirm-modal__checkbox" hidden>
            <input
                type="checkbox"
                class="confirm-modal__checkbox-input"
                id="<?php echo esc_attr($confirm_checkbox_id); ?>"
                name="<?php echo esc_attr($confirm_checkbox_name); ?>"
            />
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