<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;

$is_breakdowns_page = true;
$is_auth_page       = false;
$is_dashboard_page  = false;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_breakdowns_page', 'is_auth_page', 'is_dashboard_page')
);
?>

<?php
$averias_sort_presets = [
    [
        'key'        => 'apertura_desc',
        'label'      => __('Fecha de apertura más reciente', 'garantias-online-360vo'),
        'order_by'   => 'apertura',
        'order'      => 'desc',
        'is_default' => true,
    ],
    [
        'key'      => 'apertura_asc',
        'label'    => __('Fecha de apertura más antigua', 'garantias-online-360vo'),
        'order_by' => 'apertura',
        'order'    => 'asc',
    ],
    [
        'key'      => 'importe_desc',
        'label'    => __('Mayor importe autorizado', 'garantias-online-360vo'),
        'order_by' => 'importe_autorizado',
        'order'    => 'desc',
    ],
    [
        'key'      => 'importe_asc',
        'label'    => __('Menor importe autorizado', 'garantias-online-360vo'),
        'order_by' => 'importe_autorizado',
        'order'    => 'asc',
    ],
];

$averias_default_sort = current(array_filter($averias_sort_presets, static fn($preset) => ! empty($preset['is_default'])));
$averias_default_sort_key = is_array($averias_default_sort) && ! empty($averias_default_sort['key'])
    ? $averias_default_sort['key']
    : 'apertura_desc';
$averias_default_sort_label = is_array($averias_default_sort) && ! empty($averias_default_sort['label'])
    ? $averias_default_sort['label']
    : __('Fecha de apertura más reciente', 'garantias-online-360vo');
?>

<div class="guarantees-list__filters guarantees-list__filters--averias" style="view-transition-name: filtros-averias">
    <div class="guarantees-list__filters-row">
        <div class="guarantees-list__search-container">
            <span class="guarantees-list__search-icon" aria-hidden="true">
                <?php echo Svg::icon('search'); ?>
            </span>
            <input
                type="text"
                class="guarantees-list__search"
                placeholder="<?php esc_attr_e('Buscar vehículo o matrícula…', 'garantias-online-360vo'); ?>"
                aria-label="<?php esc_attr_e('Buscar vehículo o matrícula', 'garantias-online-360vo'); ?>"
                disabled>
            <span class="guarantees-list__close-icon" aria-hidden="true">
                <?php echo Svg::icon('cerrar'); ?>
            </span>
        </div>

        <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Estado de la avería', 'garantias-online-360vo'); ?>" disabled>
            <option value="">
                <?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?>
            </option>
            <option value="notificacion"><?php esc_html_e('Notificación de avería', 'garantias-online-360vo'); ?></option>
            <option value="abierta"><?php esc_html_e('Abierta', 'garantias-online-360vo'); ?></option>
            <option value="pendiente_taller"><?php esc_html_e('Pendiente de taller', 'garantias-online-360vo'); ?></option>
            <option value="espera_informacion"><?php esc_html_e('En espera de información', 'garantias-online-360vo'); ?></option>
            <option value="cerrada"><?php esc_html_e('Cerrada', 'garantias-online-360vo'); ?></option>
        </select>

        <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>" disabled>
            <option value="">
                <?php esc_html_e('Todos los tipos de avería', 'garantias-online-360vo'); ?>
            </option>
            <option value="motor"><?php esc_html_e('Motor', 'garantias-online-360vo'); ?></option>
            <option value="transmision"><?php esc_html_e('Transmisión', 'garantias-online-360vo'); ?></option>
            <option value="electrico"><?php esc_html_e('Sistema eléctrico', 'garantias-online-360vo'); ?></option>
            <option value="refrigeracion"><?php esc_html_e('Sistema de refrigeración', 'garantias-online-360vo'); ?></option>
            <option value="climatizacion"><?php esc_html_e('Climatización', 'garantias-online-360vo'); ?></option>
        </select>

        <div class="guarantees-list__filter-wrapper guarantees-list__filter-wrapper--clients">
            <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Clientes', 'garantias-online-360vo'); ?>" disabled>
                <option value="">
                    <?php esc_html_e('Todos los clientes', 'garantias-online-360vo'); ?>
                </option>
                <option value="motorsfera_valencia"><?php esc_html_e('MotorSfera Valencia', 'garantias-online-360vo'); ?></option>
                <option value="autopremium_madrid"><?php esc_html_e('AutoPremium Madrid', 'garantias-online-360vo'); ?></option>
                <option value="mobility_cars_bcn"><?php esc_html_e('Mobility Cars BCN', 'garantias-online-360vo'); ?></option>
                <option value="ecodrive_canarias"><?php esc_html_e('EcoDrive Canarias', 'garantias-online-360vo'); ?></option>
                <option value="redcar_sevilla"><?php esc_html_e('RedCar Sevilla', 'garantias-online-360vo'); ?></option>
            </select>
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
            <div class="guarantees-list__order" data-order-root>
                <button
                    type="button"
                    class="guarantees-list__order-btn"
                    data-order-toggle
                    data-default-sort="<?php echo esc_attr($averias_default_sort_key); ?>"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="averias-order-menu">
                    <?php echo Svg::icon('sort_lines', 'guarantees-list__order-icon'); ?>
                    <span class="guarantees-list__order-current" data-order-label>
                        <?php echo esc_html($averias_default_sort_label); ?>
                    </span>
                    <span class="guarantees-list__order-caret" aria-hidden="true">
                        <?php echo Svg::icon('arrow_drop_down'); ?>
                    </span>
                </button>
                <div
                    class="guarantees-list__order-menu"
                    id="averias-order-menu"
                    role="menu"
                    data-order-menu
                    hidden>
                    <?php foreach ($averias_sort_presets as $preset) : ?>
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
        </div>
    </div>
</div>

<div class="guarantees-container guarantees-container--averias">
    <section class="guarantees-list guarantees-list--averias">
        <table class="guarantees-table guarantees-table--averias" style="view-transition-name: averias-table">
            <colgroup>
                <col class="guarantees-table__col guarantees-table__col--vehiculo" data-min-width="220" data-max-width="380" data-default-width="260">
                <col class="guarantees-table__col guarantees-table__col--estado" data-min-width="160" data-max-width="320" data-default-width="200">
                <col class="guarantees-table__col guarantees-table__col--fecha" data-min-width="160" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--tipo" data-min-width="180" data-max-width="340" data-default-width="210">
                <col class="guarantees-table__col guarantees-table__col--importe" data-min-width="150" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--importe" data-min-width="150" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--cliente" data-min-width="210" data-max-width="360" data-default-width="240">
                <col class="guarantees-table__col guarantees-table__col--taller" data-min-width="210" data-max-width="360" data-default-width="240">
                <col class="guarantees-table__col guarantees-table__col--peritaje" data-min-width="140" data-max-width="200" data-default-width="150">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Vehículo', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Estado avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Apertura', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Ppto. Recibido', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Imp. Autorizado', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Taller', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">4567 LRT</div>
                            <div class="vehiculo__marca_modelo">Hyundai Tucson · 1.6 T-GDi</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <span class="averias-badge averias-badge--notificacion"><?php esc_html_e('Notificación de avería', 'garantias-online-360vo'); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-02-18">18/02/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Sistema eléctrico', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.420,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.420,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">MotorSfera Valencia</div>
                            <div class="vendedor__type">Profesional Premium</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">HiTech Motors</div>
                            <div class="vendedor__type">Laura Benítez</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                        <label class="averias-checkbox">
                            <input type="checkbox" checked disabled>
                            <span class="averias-checkbox__control" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Peritaje verificado', 'garantias-online-360vo'); ?></span>
                        </label>
                    </td>
                </tr>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">1234 LKF</div>
                            <div class="vehiculo__marca_modelo">SEAT León · 1.5 TSI</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <span class="averias-badge averias-badge--pendiente-taller"><?php esc_html_e('Pendiente de taller', 'garantias-online-360vo'); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-02-12">12/02/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Motor', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        2.450,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.800,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">AutoPremium Madrid</div>
                            <div class="vendedor__type">Profesional Gold</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">Talleres Ruiz</div>
                            <div class="vendedor__type">Marta Ruiz</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                        <label class="averias-checkbox">
                            <input type="checkbox" checked disabled>
                            <span class="averias-checkbox__control" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Peritaje verificado', 'garantias-online-360vo'); ?></span>
                        </label>
                    </td>
                </tr>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">8976 MNB</div>
                            <div class="vehiculo__marca_modelo">Audi Q3 · 2.0 TDI</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <span class="averias-badge averias-badge--espera"><?php esc_html_e('En espera de información', 'garantias-online-360vo'); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-01-28">28/01/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Transmisión', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        3.280,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        2.950,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">Mobility Cars BCN</div>
                            <div class="vendedor__type">Profesional Premium</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">ServicePoint Diésel</div>
                            <div class="vendedor__type">Álvaro Peña</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                        <label class="averias-checkbox">
                            <input type="checkbox" disabled>
                            <span class="averias-checkbox__control" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Peritaje pendiente', 'garantias-online-360vo'); ?></span>
                        </label>
                    </td>
                </tr>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">5621 LPH</div>
                            <div class="vehiculo__marca_modelo">Toyota Corolla · 1.8 Hybrid</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <span class="averias-badge averias-badge--cerrada"><?php esc_html_e('Cerrada', 'garantias-online-360vo'); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2024-12-19">19/12/2024</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Sistema eléctrico', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.150,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.150,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">EcoDrive Canarias</div>
                            <div class="vendedor__type">Profesional Estándar</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">ElectroAuto Las Palmas</div>
                            <div class="vendedor__type">Noelia Martín</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                        <label class="averias-checkbox">
                            <input type="checkbox" checked disabled>
                            <span class="averias-checkbox__control" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Peritaje verificado', 'garantias-online-360vo'); ?></span>
                        </label>
                    </td>
                </tr>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">3456 LZR</div>
                            <div class="vehiculo__marca_modelo">Peugeot 3008 · 1.2 PureTech</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <span class="averias-badge averias-badge--abierta"><?php esc_html_e('Abierta', 'garantias-online-360vo'); ?></span>
                    </td>
                    <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-02-03">03/02/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Sistema de refrigeración', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        980,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        750,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">RedCar Sevilla</div>
                            <div class="vendedor__type">Profesional Silver</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">CoolTech Garage</div>
                            <div class="vendedor__type">Sergio Vidal</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                        <label class="averias-checkbox">
                            <input type="checkbox" disabled>
                            <span class="averias-checkbox__control" aria-hidden="true"></span>
                            <span class="screen-reader-text"><?php esc_html_e('Peritaje pendiente', 'garantias-online-360vo'); ?></span>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</div>

<style>
    .guarantees-container--averias {
        grid-template-columns: 1fr;
    }

    .guarantees-container--averias .guarantees-list {
        grid-column: 1 / -1;
    }

    .guarantees-list--averias {
        background: var(--surface);
    }

    .guarantees-table--averias {
        width: 100%;
        table-layout: auto;
        min-width: 960px;
    }

    .guarantees-table__col {
        width: auto;
    }

    .guarantees-table__col--vehiculo {
        min-width: 220px;
    }

    .guarantees-table__col--estado,
    .guarantees-table__col--fecha,
    .guarantees-table__col--tipo,
    .guarantees-table__col--peritaje {
        min-width: 160px;
    }

    .guarantees-table__col--importe {
        min-width: 150px;
    }

    .guarantees-table__col--cliente,
    .guarantees-table__col--taller {
        min-width: 210px;
    }

    .guarantees-table__header--amount,
    .guarantees-table__cell--amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .averias-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: none;
        white-space: nowrap;
    }

    .averias-badge--abierta,
    .averias-badge--notificacion {
        background: rgba(227, 68, 68, 0.18);
        color: rgb(176, 35, 35);
    }

    .averias-badge--notificacion {
        font-variant-numeric: tabular-nums;
    }

    .averias-badge--pendiente-taller {
        background: rgba(255, 173, 66, 0.18);
        color: rgb(189, 96, 0);
    }

    .averias-badge--espera {
        background: rgba(238, 201, 55, 0.18);
        color: rgb(158, 128, 0);
    }

    .averias-badge--cerrada {
        background: rgba(52, 199, 89, 0.18);
        color: rgb(30, 130, 58);
    }

    .averias-checkbox {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: not-allowed;
    }

    .averias-checkbox input {
        position: absolute;
        inset: 0;
        opacity: 0;
        pointer-events: none;
    }

    .averias-checkbox__control {
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 0.35rem;
        border: 2px solid var(--border-strong);
        background: var(--surface);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .averias-checkbox input:checked + .averias-checkbox__control {
        background: var(--success-strong, var(--primary-color));
        border-color: var(--success-strong, var(--primary-color));
    }

    .averias-checkbox input:checked + .averias-checkbox__control::after {
        content: '';
        width: 0.45rem;
        height: 0.75rem;
        border: solid var(--text-inverse);
        border-width: 0 0.2rem 0.2rem 0;
        transform: rotate(45deg);
    }

    .averias-checkbox input:not(:checked) + .averias-checkbox__control::after {
        content: '';
        width: 0.6rem;
        height: 0.6rem;
        border-radius: 50%;
        background: var(--border-strong);
        opacity: 0.25;
    }

    .averias-checkbox input:checked + .averias-checkbox__control::after {
        opacity: 1;
    }

    .averias-checkbox .screen-reader-text {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    .guarantees-list__filters--averias {
        margin-bottom: var(--spacing-3);
    }

    .guarantees-list__filters--averias .guarantees-list__filters-row {
        flex-wrap: wrap;
        gap: var(--spacing-2);
    }

    @media (max-width: 1024px) {
        .guarantees-table--averias {
            min-width: 100%;
        }

        .guarantees-list__filters--averias .guarantees-list__filters-row {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page')
);
?>
