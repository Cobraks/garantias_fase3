<?php
if (! defined('ABSPATH')) {
    exit;
}

$is_breakdowns_page          = true;
$is_dashboard_page           = false;
$is_auth_page                = false;
$is_breakdown_detail_page    = true;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_breakdowns_page', 'is_dashboard_page', 'is_auth_page', 'is_breakdown_detail_page')
);

$license_plate = isset($license_plate) ? (string) $license_plate : '';
$license_plate = $license_plate !== '' ? strtoupper($license_plate) : '';

// Ajusta esta variable a 'missing' o 'no_state' para ver los mensajes de vacío durante la maquetación.
$view_mode = 'case';

$sample_case = [
    'reference'         => 'EXP-AV-2025-00123',
    'status'            => 'Pendiente de taller',
    'status_variant'    => 'warning',
    'opened'            => '12/10/2025',
    'type'              => 'Motor',
    'summary'           => 'Pérdida de potencia y testigo de avería motor encendido.',
    'kilometers_start'  => '92.340 km',
    'kilometers_now'    => '94.210 km',
    'vendor'            => 'Concesionario Guay',
    'vendor_manager'    => 'Pepito Pérez',
    'owner'             => 'Laura García',
    'policy'            => 'Garantía Premium 24',
    'last_update'       => '13/10/2025',
    'peritaje_required' => true,
    'cover_element'     => 'Por confirmar',
    'license_plate'     => '1234 ABC',
    'workshop'          => [
        'name'        => 'Talleres Pérez S.L.',
        'contact'     => 'María Pérez',
        'phone'       => '+34 600 000 000',
        'email'       => 'taller@empresa.com',
        'address'     => 'Calle de ejemplo, 12, Madrid',
        'type'        => 'Asociado',
        'tax_id'      => 'B12345678',
        'fiscal_name' => 'Talleres Pérez S.L.',
    ],
    'financials'        => [
        'budget'     => '1.200,00 €',
        'authorized' => '800,00 €',
        'resolution' => 'Por determinar',
    ],
];

$sample_history = [
    [
        'date'        => '13/10/2025',
        'type'        => 'Llamada',
        'actor'       => 'Gestor 360VO',
        'description' => 'El taller confirma diagnóstico inicial. Pendiente de peritaje presencial.',
    ],
    [
        'date'        => '12/10/2025',
        'type'        => 'Correo',
        'actor'       => 'Admin 360VO',
        'description' => 'Recepción avería y apertura de expediente. Se solicita documentación inicial.',
    ],
    [
        'date'        => '12/10/2025',
        'type'        => 'Automático',
        'actor'       => 'Sistema',
        'description' => 'Generación de expediente vinculado a la garantía y notificación al profesional.',
    ],
];

$sample_notes = [
    [
        'title' => 'Resumen interno',
        'body'  => 'Cliente solicita vehículo de sustitución. Se valorará tras peritaje.',
    ],
    [
        'title' => 'Pendientes',
        'body'  => 'Revisión de cobertura específica del turbo en póliza Premium 24.',
    ],
];

$sample_documents = [
    [
        'label' => 'Presupuesto preliminar',
        'type'  => 'PDF',
        'size'  => '540 KB',
    ],
    [
        'label' => 'Fotos motor',
        'type'  => 'Imágenes',
        'size'  => '2,4 MB',
    ],
    [
        'label' => 'Informe cliente',
        'type'  => 'Documento',
        'size'  => '180 KB',
    ],
];

$empty_messages = [
    'missing'  => __('No existe ninguna garantía para la matrícula indicada.', 'garantias-online-360vo'),
    'no_state' => __('No hay expediente abierto para esta garantía. ¿Deseas abrirlo?', 'garantias-online-360vo'),
];
?>

<div class="averia-detail" data-averia-app>
    <?php if ($view_mode !== 'case') : ?>
        <section class="averia-detail__empty">
            <div class="averia-detail__empty-card">
                <h2><?php esc_html_e('Sin expediente disponible', 'garantias-online-360vo'); ?></h2>
                <p><?php echo esc_html($empty_messages[$view_mode] ?? ''); ?></p>
                <a class="averia-detail__back-link" href="<?php echo esc_url(home_url('/garantias-online/averias/')); ?>">
                    <?php esc_html_e('Volver al listado de averías', 'garantias-online-360vo'); ?>
                </a>
            </div>
        </section>
    <?php else : ?>
        <div
            class="guarantees-list__filters guarantees-list__filters--averia-detail averia-detail__filters"
            data-sticky-target=".averia-detail__columns"
            style="view-transition-name: filtros-averia-detalle"
        >
            <div class="averia-detail__filters-row">
                <nav
                    class="averia-tabs"
                    role="tablist"
                    aria-label="<?php esc_attr_e('Secciones del expediente', 'garantias-online-360vo'); ?>"
                >
                    <?php
                    $tabs = [
                        'historial'     => __('Historial', 'garantias-online-360vo'),
                        'resumen'       => __('Resumen', 'garantias-online-360vo'),
                        'taller'        => __('Taller', 'garantias-online-360vo'),
                        'importes'      => __('Importes', 'garantias-online-360vo'),
                        'documentacion' => __('Documentación', 'garantias-online-360vo'),
                    ];
                    $first = true;
                    foreach ($tabs as $tab_key => $tab_label) :
                        $tab_id = 'averia-tab-' . $tab_key;
                        ?>
                        <button
                            type="button"
                            class="averia-tabs__button<?php echo $first ? ' is-active' : ''; ?>"
                            id="<?php echo esc_attr($tab_id); ?>"
                            role="tab"
                            aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
                            aria-controls="averia-panel-<?php echo esc_attr($tab_key); ?>"
                            data-tab-trigger="<?php echo esc_attr($tab_key); ?>"
                        >
                            <?php echo esc_html($tab_label); ?>
                        </button>
                        <?php
                        $first = false;
                    endforeach;
                    ?>
                </nav>

                <div class="averia-detail__filters-actions">
                    <button type="button" class="averia-detail__primary-action">
                        <?php esc_html_e('Guardar cambios', 'garantias-online-360vo'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="averia-detail__columns">
            <aside class="averia-detail__column averia-detail__column--context" aria-label="<?php esc_attr_e('Contexto del expediente', 'garantias-online-360vo'); ?>">
                <section class="averia-card averia-card--context">
                    <header class="averia-card__header">
                        <p class="averia-card__eyebrow"><?php esc_html_e('Expediente', 'garantias-online-360vo'); ?></p>
                        <h2 class="averia-card__title"><?php echo esc_html($sample_case['reference']); ?></h2>
                        <span class="averia-status averia-status--<?php echo esc_attr($sample_case['status_variant']); ?>">
                            <?php echo esc_html($sample_case['status']); ?>
                        </span>
                    </header>
                    <dl class="averia-meta-list">
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['license_plate'] !== '' ? $sample_case['license_plate'] : $license_plate); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Garantía', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['policy']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Fecha de apertura', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['opened']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Última actualización', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['last_update']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['type']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo $sample_case['peritaje_required'] ? esc_html__('Requerido', 'garantias-online-360vo') : esc_html__('No requerido', 'garantias-online-360vo'); ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="averia-card averia-card--people">
                    <h3 class="averia-card__subtitle"><?php esc_html_e('Personas clave', 'garantias-online-360vo'); ?></h3>
                    <dl class="averia-meta-list averia-meta-list--compact">
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['vendor']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Titular', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['owner']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Gestor comercial', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['vendor_manager']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Elemento en cobertura', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['cover_element']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Km contratación', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['kilometers_start']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Km entrada taller', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['kilometers_now']); ?></dd>
                        </div>
                    </dl>
                </section>
            </aside>

            <section class="averia-detail__column averia-detail__column--main" data-averia-panels>
                <section
                    class="averia-panel is-active"
                    id="averia-panel-historial"
                    role="tabpanel"
                    aria-labelledby="averia-tab-historial"
                    data-panel="historial"
                >
                    <header class="averia-panel__header">
                        <h2><?php esc_html_e('Historial de comunicación', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Centraliza llamadas, correos y eventos internos relacionados con la avería.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--stack">
                        <div class="averia-timeline" aria-label="<?php esc_attr_e('Eventos recientes', 'garantias-online-360vo'); ?>">
                            <?php foreach ($sample_history as $event) : ?>
                                <article class="averia-timeline__item">
                                    <div class="averia-timeline__marker" aria-hidden="true"></div>
                                    <div class="averia-timeline__content">
                                        <header class="averia-timeline__meta">
                                            <time datetime="<?php echo esc_attr($event['date']); ?>">
                                                <?php echo esc_html($event['date']); ?>
                                            </time>
                                            <span class="averia-timeline__type"><?php echo esc_html($event['type']); ?></span>
                                            <span class="averia-timeline__actor"><?php echo esc_html($event['actor']); ?></span>
                                        </header>
                                        <p class="averia-timeline__description">
                                            <?php echo esc_html($event['description']); ?>
                                        </p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <form class="averia-note-form" action="#" method="post">
                            <fieldset>
                                <legend><?php esc_html_e('Añadir registro', 'garantias-online-360vo'); ?></legend>
                                <div class="averia-note-form__grid">
                                    <label class="averia-note-form__field">
                                        <span><?php esc_html_e('Tipo', 'garantias-online-360vo'); ?></span>
                                        <select name="note_type" disabled>
                                            <option><?php esc_html_e('Seleccionar…', 'garantias-online-360vo'); ?></option>
                                        </select>
                                    </label>
                                    <label class="averia-note-form__field">
                                        <span><?php esc_html_e('Fecha', 'garantias-online-360vo'); ?></span>
                                        <input type="date" name="note_date" disabled />
                                    </label>
                                </div>
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Resumen', 'garantias-online-360vo'); ?></span>
                                    <textarea name="note_summary" rows="4" disabled></textarea>
                                </label>
                                <div class="averia-note-form__actions">
                                    <button type="button" class="averia-detail__ghost-action" disabled>
                                        <?php esc_html_e('Registrar evento', 'garantias-online-360vo'); ?>
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </section>

                <section
                    class="averia-panel"
                    id="averia-panel-resumen"
                    role="tabpanel"
                    aria-labelledby="averia-tab-resumen"
                    data-panel="resumen"
                    hidden
                >
                    <header class="averia-panel__header">
                        <h2><?php esc_html_e('Resumen del expediente', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Información clave del vehículo, póliza y estado actual.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--grid">
                        <article class="averia-card averia-card--section">
                            <h3><?php esc_html_e('Vehículo y póliza', 'garantias-online-360vo'); ?></h3>
                            <dl class="averia-meta-list">
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['license_plate']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Garantía', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['policy']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Titular', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['owner']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['vendor']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Gestor comercial', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['vendor_manager']); ?></dd>
                                </div>
                            </dl>
                        </article>
                        <article class="averia-card averia-card--section">
                            <h3><?php esc_html_e('Seguimiento', 'garantias-online-360vo'); ?></h3>
                            <dl class="averia-meta-list">
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Estado actual', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['status']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Descripción breve', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['summary']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Elemento en cobertura', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['cover_element']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Km contratación', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['kilometers_start']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Km entrada taller', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['kilometers_now']); ?></dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </section>

                <section
                    class="averia-panel"
                    id="averia-panel-taller"
                    role="tabpanel"
                    aria-labelledby="averia-tab-taller"
                    data-panel="taller"
                    hidden
                >
                    <header class="averia-panel__header">
                        <h2><?php esc_html_e('Taller responsable', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Datos de contacto y seguimiento del taller encargado.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--grid">
                        <article class="averia-card averia-card--highlight">
                            <dl class="averia-meta-list">
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['name']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Contacto', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['contact']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['phone']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Correo', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['email']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['address']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Tipo', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['type']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('CIF/NIF', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['tax_id']); ?></dd>
                                </div>
                                <div class="averia-meta-list__item">
                                    <dt><?php esc_html_e('Razón social', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['fiscal_name']); ?></dd>
                                </div>
                            </dl>
                        </article>
                        <article class="averia-card averia-card--section">
                            <h3><?php esc_html_e('Notas internas', 'garantias-online-360vo'); ?></h3>
                            <ul class="averia-card__notes">
                                <?php foreach ($sample_notes as $note) : ?>
                                    <li>
                                        <strong><?php echo esc_html($note['title']); ?>:</strong>
                                        <span><?php echo esc_html($note['body']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    </div>
                </section>

                <section
                    class="averia-panel"
                    id="averia-panel-importes"
                    role="tabpanel"
                    aria-labelledby="averia-tab-importes"
                    data-panel="importes"
                    hidden
                >
                    <header class="averia-panel__header">
                        <h2><?php esc_html_e('Importes y resolución', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Controla el presupuesto recibido, la autorización y el estado de la resolución.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--grid">
                        <article class="averia-card averia-card--section">
                            <div class="averia-note-form__grid averia-note-form__grid--triple">
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Presupuesto recibido (€)', 'garantias-online-360vo'); ?></span>
                                    <input type="number" inputmode="decimal" placeholder="0,00" disabled />
                                </label>
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Importe autorizado (€)', 'garantias-online-360vo'); ?></span>
                                    <input type="number" inputmode="decimal" placeholder="0,00" disabled />
                                </label>
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Resolución', 'garantias-online-360vo'); ?></span>
                                    <select disabled>
                                        <option><?php esc_html_e('Por determinar', 'garantias-online-360vo'); ?></option>
                                    </select>
                                </label>
                            </div>
                            <label class="averia-note-form__field">
                                <span><?php esc_html_e('Detalle de resolución', 'garantias-online-360vo'); ?></span>
                                <textarea rows="4" placeholder="Motivo, condiciones, piezas cubiertas, observaciones..." disabled></textarea>
                            </label>
                            <div class="averia-note-form__actions averia-note-form__actions--wrap">
                                <button type="button" class="averia-detail__ghost-action" disabled><?php esc_html_e('Generar resolución', 'garantias-online-360vo'); ?></button>
                                <button type="button" class="averia-detail__ghost-action" disabled><?php esc_html_e('Enviar al taller', 'garantias-online-360vo'); ?></button>
                                <button type="button" class="averia-detail__ghost-action" disabled><?php esc_html_e('Enviar al propietario', 'garantias-online-360vo'); ?></button>
                                <button type="button" class="averia-detail__ghost-action" disabled><?php esc_html_e('Enviar al vendedor', 'garantias-online-360vo'); ?></button>
                            </div>
                        </article>
                        <article class="averia-card averia-card--section">
                            <h3><?php esc_html_e('Historial de importes', 'garantias-online-360vo'); ?></h3>
                            <table class="averia-table" aria-label="<?php esc_attr_e('Historial de importes', 'garantias-online-360vo'); ?>">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Fecha', 'garantias-online-360vo'); ?></th>
                                        <th scope="col"><?php esc_html_e('Evento', 'garantias-online-360vo'); ?></th>
                                        <th scope="col"><?php esc_html_e('Detalle', 'garantias-online-360vo'); ?></th>
                                        <th scope="col"><?php esc_html_e('Importe', 'garantias-online-360vo'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>12/10/2025</td>
                                        <td><?php esc_html_e('Presupuesto', 'garantias-online-360vo'); ?></td>
                                        <td><?php esc_html_e('Desglose del taller', 'garantias-online-360vo'); ?></td>
                                        <td>1.200,00 €</td>
                                    </tr>
                                    <tr>
                                        <td>13/10/2025</td>
                                        <td><?php esc_html_e('Autorizado', 'garantias-online-360vo'); ?></td>
                                        <td><?php esc_html_e('Reparación parcial', 'garantias-online-360vo'); ?></td>
                                        <td>800,00 €</td>
                                    </tr>
                                </tbody>
                            </table>
                        </article>
                    </div>
                </section>

                <section
                    class="averia-panel"
                    id="averia-panel-documentacion"
                    role="tabpanel"
                    aria-labelledby="averia-tab-documentacion"
                    data-panel="documentacion"
                    hidden
                >
                    <header class="averia-panel__header">
                        <h2><?php esc_html_e('Documentación y medios', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Comparte archivos del expediente con el equipo y los implicados.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--stack">
                        <div class="averia-upload">
                            <div class="averia-upload__zone" aria-label="<?php esc_attr_e('Zona de subida', 'garantias-online-360vo'); ?>">
                                <strong><?php esc_html_e('Arrastra y suelta los archivos aquí', 'garantias-online-360vo'); ?></strong>
                                <span><?php esc_html_e('o pulsa para seleccionar', 'garantias-online-360vo'); ?></span>
                                <input type="file" multiple aria-label="<?php esc_attr_e('Seleccionar archivos', 'garantias-online-360vo'); ?>" disabled />
                            </div>
                            <div class="averia-upload__grid">
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Tipo de documento', 'garantias-online-360vo'); ?></span>
                                    <select disabled>
                                        <option><?php esc_html_e('Foto', 'garantias-online-360vo'); ?></option>
                                    </select>
                                </label>
                                <label class="averia-note-form__field">
                                    <span><?php esc_html_e('Notas', 'garantias-online-360vo'); ?></span>
                                    <input type="text" placeholder="<?php esc_attr_e('Ej.: Testigo motor encendido al ralentí', 'garantias-online-360vo'); ?>" disabled />
                                </label>
                            </div>
                        </div>
                        <div class="averia-documents" aria-label="<?php esc_attr_e('Adjuntos', 'garantias-online-360vo'); ?>">
                            <?php foreach ($sample_documents as $document) : ?>
                                <article class="averia-document">
                                    <h3><?php echo esc_html($document['label']); ?></h3>
                                    <p><?php echo esc_html($document['type']); ?> · <?php echo esc_html($document['size']); ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </section>

            <aside class="averia-detail__column averia-detail__column--support" aria-label="<?php esc_attr_e('Resumen y siguientes pasos', 'garantias-online-360vo'); ?>">
                <section class="averia-summary-card">
                    <h2><?php esc_html_e('Resumen rápido', 'garantias-online-360vo'); ?></h2>
                    <dl class="averia-meta-list averia-meta-list--tight">
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Estado', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['status']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Expediente', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['reference']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Última actualización', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['last_update']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Responsable taller', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['workshop']['contact']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo $sample_case['peritaje_required'] ? esc_html__('Asignado', 'garantias-online-360vo') : esc_html__('Pendiente', 'garantias-online-360vo'); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Importe autorizado', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['financials']['authorized']); ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="averia-summary-card averia-summary-card--secondary">
                    <h2><?php esc_html_e('Próximos pasos', 'garantias-online-360vo'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Confirmar disponibilidad de piezas con el taller.', 'garantias-online-360vo'); ?></li>
                        <li><?php esc_html_e('Actualizar cliente con resolución provisional.', 'garantias-online-360vo'); ?></li>
                        <li><?php esc_html_e('Programar visita de perito si procede.', 'garantias-online-360vo'); ?></li>
                    </ul>
                </section>

                <section class="averia-summary-card averia-summary-card--links">
                    <h2><?php esc_html_e('Recursos útiles', 'garantias-online-360vo'); ?></h2>
                    <ul>
                        <li><a href="#" aria-disabled="true"><?php esc_html_e('Ver póliza vinculada', 'garantias-online-360vo'); ?></a></li>
                        <li><a href="#" aria-disabled="true"><?php esc_html_e('Plantilla de resolución', 'garantias-online-360vo'); ?></a></li>
                        <li><a href="#" aria-disabled="true"><?php esc_html_e('Contactar con soporte 360VO', 'garantias-online-360vo'); ?></a></li>
                    </ul>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        function onReady(callback) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
            } else {
                callback();
            }
        }

        onReady(function () {
            var tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tab-trigger]'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));

            function activateTab(tab) {
                var target = tab.getAttribute('data-tab-trigger');
                tabButtons.forEach(function (button) {
                    var isActive = button === tab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var matches = panel.getAttribute('data-panel') === target;
                    panel.classList.toggle('is-active', matches);
                    if (matches) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                    }
                });
            }

            tabButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    activateTab(button);
                });
            });
        });
    })();
</script>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page', 'is_breakdown_detail_page')
);
