<?php
if (! defined('ABSPATH')) {
    exit;
}

// “Mis garantías”
$is_list_page = true;
\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_list_page'));

use GarantiasOnline360VO\Svg;

$current_user = wp_get_current_user();
$user_roles   = is_object($current_user) ? (array) $current_user->roles : [];
$is_professional  = in_array('go_profesional', $user_roles, true);
$show_channel_col = ! $is_professional;
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
            <?php if (! $is_professional) : ?>
                <?php
                $sort_presets = [
                    [
                        'key'          => 'created_desc',
                        'label'        => __('Más recientes', 'garantias-online-360vo'),
                        'order_by'     => 'created',
                        'order'        => 'desc',
                        'is_default'   => true,
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
                $default_sort = current(array_filter($sort_presets, static fn($preset) => !empty($preset['is_default'])));
                $default_sort_key = is_array($default_sort) && ! empty($default_sort['key'])
                    ? $default_sort['key']
                    : 'created_desc';
                $default_sort_label = is_array($default_sort) && ! empty($default_sort['label'])
                    ? $default_sort['label']
                    : __('Más recientes', 'garantias-online-360vo');
                ?>
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
        </div>
    </div>

        <div class="guarantees-list__filters-advanced" data-advanced-panel hidden>
            <div class="guarantees-list__filters-advanced-grid">
                <select
                    class="guarantees-list__filter"
                    data-filter="plan"
                    aria-label="<?php esc_attr_e('Coberturas', 'garantias-online-360vo'); ?>">
                    <option value=""><?php esc_html_e('Todas las coberturas', 'garantias-online-360vo'); ?></option>
                </select>

                <select
                    class="guarantees-list__filter"
                    data-filter="payment"
                    aria-label="<?php esc_attr_e('Método de pago', 'garantias-online-360vo'); ?>">
                    <option value=""><?php esc_html_e('Todos los métodos de pago', 'garantias-online-360vo'); ?></option>
                </select>

                <?php if (! $is_professional) : ?>
                    <select
                        class="guarantees-list__filter"
                        data-filter="commercial"
                        aria-label="<?php esc_attr_e('Garantías por comercial', 'garantias-online-360vo'); ?>">
                        <option value=""><?php esc_html_e('Todos los comerciales', 'garantias-online-360vo'); ?></option>
                    </select>
                <?php endif; ?>
            </div>
        </div>
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
    <aside class="guarantee-detail" style="view-transition-name: resume-derecha">
        <!-- Panel 1: mensaje cuando no hay selección -->
        <div class="guarantee-detail__panel active" id="detail-panel-1">
            <div class="guarantee-detail__empty">
                <h3 class="guarantee-detail__title">Ninguna garantía seleccionada</h3>
                <p>Haz clic en una fila para ver sus detalles aquí.</p>
            </div>
        </div>

        <!-- Panel 2: se rellenará desde JS -->
        <div class="guarantee-detail__panel" id="detail-panel-2"></div>
    </aside>



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