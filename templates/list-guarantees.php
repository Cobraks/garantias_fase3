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

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Estado', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Plan', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Todos los planes', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></option>
        <option value="Particular"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
        <option value="Profesional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
        <option value="Gestoría"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Concesionario', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Concesionario', 'garantias-online-360vo'); ?></option>
    </select>
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
            <a class="pdf-modal__download" href="#" download>
                <?php esc_html_e('Descargar PDF', 'garantias-online-360vo'); ?>
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
            <h3 class="confirm-modal__upload-title"><?php esc_html_e('Adjuntar comprobante', 'garantias-online-360vo'); ?></h3>
            <p class="confirm-modal__upload-text">
                <?php
                echo wp_kses(
                    __('Adjunta el documento (PDF, JPG o PNG) para validar el pago. Tamaño máximo: <strong>10 MB</strong>.', 'garantias-online-360vo'),
                    ['strong' => []]
                );
                ?>
            </p>
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