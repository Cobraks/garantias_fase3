<?php
if (! defined('ABSPATH')) {
    exit;
}

$is_breakdowns_page = true;
$is_dashboard_page  = false;
$is_auth_page       = false;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_breakdowns_page', 'is_dashboard_page', 'is_auth_page')
);

$license_plate = isset($license_plate) ? (string) $license_plate : '';
$license_plate = $license_plate !== '' ? strtoupper($license_plate) : '';

// Ajusta esta variable a 'missing' o 'no_state' para ver los mensajes de vacío durante la maquetación.
$view_mode = 'case';

$sample_case = [
    'reference'        => 'EXP-AV-2025-00123',
    'status'           => 'Pendiente de taller',
    'status_variant'   => 'warning',
    'opened'           => '12/10/2025',
    'type'             => 'Motor',
    'summary'          => 'Pérdida de potencia y testigo de avería motor encendido.',
    'kilometers_start' => '92.340 km',
    'kilometers_now'   => '94.210 km',
    'vendor'           => 'Concesionario Guay',
    'vendor_manager'   => 'Pepito Pérez',
    'owner'            => 'Laura García',
    'policy'           => 'Garantía Premium 24',
    'peritaje'         => true,
    'cover_element'    => 'Por confirmar',
    'license_plate'    => '1234 ABC',
    'peritaje_required'=> true,
    'workshop'         => [
        'name'        => 'Talleres Pérez S.L.',
        'contact'     => 'María Pérez',
        'phone'       => '+34 600 000 000',
        'email'       => 'taller@empresa.com',
        'address'     => 'Calle de ejemplo, 12, Madrid',
        'type'        => 'Asociado',
        'tax_id'      => 'B12345678',
        'fiscal_name' => 'Talleres Pérez S.L.',
    ],
    'financials'       => [
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
    <div class="averia-detail__header">
        <div class="averia-detail__heading">
            <h1 class="averia-detail__title">
                <?php esc_html_e('Gestión de avería', 'garantias-online-360vo'); ?>
            </h1>
            <?php if ($license_plate !== '') : ?>
                <p class="averia-detail__subtitle">
                    <?php
                    printf(
                        /* translators: %s: license plate */
                        esc_html__('Matrícula %s', 'garantias-online-360vo'),
                        esc_html($license_plate)
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($view_mode === 'case') : ?>
            <div class="averia-detail__badge averia-detail__badge--<?php echo esc_attr($sample_case['status_variant']); ?>">
                <span class="averia-detail__badge-dot" aria-hidden="true"></span>
                <?php echo esc_html($sample_case['status']); ?>
            </div>
        <?php endif; ?>
    </div>

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
        <section class="averia-detail__meta">
            <div class="averia-detail__meta-item">
                <span class="averia-detail__meta-label"><?php esc_html_e('Expediente', 'garantias-online-360vo'); ?></span>
                <span class="averia-detail__meta-value"><?php echo esc_html($sample_case['reference']); ?></span>
            </div>
            <div class="averia-detail__meta-item">
                <span class="averia-detail__meta-label"><?php esc_html_e('Fecha de apertura', 'garantias-online-360vo'); ?></span>
                <time datetime="2025-10-12" class="averia-detail__meta-value"><?php echo esc_html($sample_case['opened']); ?></time>
            </div>
            <div class="averia-detail__meta-item">
                <span class="averia-detail__meta-label"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></span>
                <span class="averia-detail__meta-value"><?php echo esc_html($sample_case['type']); ?></span>
            </div>
            <div class="averia-detail__meta-item">
                <span class="averia-detail__meta-label"><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></span>
                <span class="averia-detail__meta-value">
                    <?php echo $sample_case['peritaje'] ? esc_html__('Requerido', 'garantias-online-360vo') : esc_html__('No requerido', 'garantias-online-360vo'); ?>
                </span>
            </div>
        </section>

        <div
            class="guarantees-list__filters guarantees-list__filters--averia-detail"
            data-sticky-target=".averia-detail__layout"
            style="view-transition-name: filtros-averia-detalle"
        >
            <div class="guarantees-list__filters-row guarantees-list__filters-row--averia-detail">
                <nav class="averia-tabs" role="tablist">
                    <?php
                    $tabs = [
                        'historial'    => __('Historial', 'garantias-online-360vo'),
                        'resumen'      => __('Resumen', 'garantias-online-360vo'),
                        'taller'       => __('Taller', 'garantias-online-360vo'),
                        'importes'     => __('Importes', 'garantias-online-360vo'),
                        'documentacion'=> __('Documentación', 'garantias-online-360vo'),
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

                <div class="guarantees-list__filters-actions guarantees-list__filters-actions--averia-detail">
                    <button type="button" class="averia-detail__action-btn averia-detail__action-btn--ghost">
                        <?php esc_html_e('Guardar cambios', 'garantias-online-360vo'); ?>
                    </button>
                    <button type="button" class="averia-detail__action-btn">
                        <?php esc_html_e('Enviar actualización', 'garantias-online-360vo'); ?>
                    </button>
                    <button type="button" class="averia-detail__action-btn averia-detail__action-btn--danger">
                        <?php esc_html_e('Cerrar expediente', 'garantias-online-360vo'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="averia-detail__layout">
            <main class="averia-detail__content" data-averia-panels>
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
                    <div class="averia-panel__body">
                        <div class="averia-timeline">
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
                                    <button type="button" class="averia-detail__action-btn averia-detail__action-btn--ghost" disabled>
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
                        <article class="averia-card">
                            <h3><?php esc_html_e('Vehículo y póliza', 'garantias-online-360vo'); ?></h3>
                            <dl>
                                <div>
                                    <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['license_plate']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Garantía', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['policy']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Titular', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['owner']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['vendor']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Gestor comercial', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['vendor_manager']); ?></dd>
                                </div>
                            </dl>
                        </article>
                        <article class="averia-card">
                            <h3><?php esc_html_e('Seguimiento', 'garantias-online-360vo'); ?></h3>
                            <dl>
                                <div>
                                    <dt><?php esc_html_e('Estado actual', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['status']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Descripción breve', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['summary']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Elemento en cobertura', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['cover_element']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Km contratación', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['kilometers_start']); ?></dd>
                                </div>
                                <div>
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
                            <dl>
                                <div>
                                    <dt><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['name']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Contacto', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['contact']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['phone']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Correo', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['email']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['address']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Tipo', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['type']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('CIF/NIF', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['tax_id']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Razón social', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['workshop']['fiscal_name']); ?></dd>
                                </div>
                            </dl>
                        </article>
                        <article class="averia-card">
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
                        <article class="averia-card">
                            <dl>
                                <div>
                                    <dt><?php esc_html_e('Presupuesto recibido', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['financials']['budget']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Importe autorizado', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['financials']['authorized']); ?></dd>
                                </div>
                                <div>
                                    <dt><?php esc_html_e('Resolución', 'garantias-online-360vo'); ?></dt>
                                    <dd><?php echo esc_html($sample_case['financials']['resolution']); ?></dd>
                                </div>
                            </dl>
                        </article>
                        <article class="averia-card">
                            <h3><?php esc_html_e('Histórico de movimientos', 'garantias-online-360vo'); ?></h3>
                            <table class="averia-table" aria-label="Histórico de importes">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e('Fecha', 'garantias-online-360vo'); ?></th>
                                        <th scope="col"><?php esc_html_e('Concepto', 'garantias-online-360vo'); ?></th>
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
                        <h2><?php esc_html_e('Documentación asociada', 'garantias-online-360vo'); ?></h2>
                        <p><?php esc_html_e('Consulta los archivos compartidos con el taller, cliente y otros agentes.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-panel__body averia-panel__body--grid averia-panel__body--stretch">
                        <article class="averia-card averia-card--stretch">
                            <h3><?php esc_html_e('Carga rápida', 'garantias-online-360vo'); ?></h3>
                            <div class="averia-upload" role="presentation">
                                <p><?php esc_html_e('Arrastra y suelta archivos o selecciónalos manualmente.', 'garantias-online-360vo'); ?></p>
                                <button type="button" class="averia-detail__action-btn averia-detail__action-btn--ghost" disabled>
                                    <?php esc_html_e('Subir documentos', 'garantias-online-360vo'); ?>
                                </button>
                            </div>
                        </article>
                        <article class="averia-card averia-card--stretch">
                            <h3><?php esc_html_e('Adjuntos recientes', 'garantias-online-360vo'); ?></h3>
                            <ul class="averia-documents">
                                <?php foreach ($sample_documents as $document) : ?>
                                    <li>
                                        <span class="averia-documents__name"><?php echo esc_html($document['label']); ?></span>
                                        <span class="averia-documents__meta">
                                            <?php echo esc_html($document['type']); ?> · <?php echo esc_html($document['size']); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    </div>
                </section>
            </main>

            <aside class="averia-detail__sidebar">
                <section class="averia-summary">
                    <h2><?php esc_html_e('Resumen rápido', 'garantias-online-360vo'); ?></h2>
                    <dl>
                        <div>
                            <dt><?php esc_html_e('Estado', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['status']); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Expediente', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['reference']); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Última actualización', 'garantias-online-360vo'); ?></dt>
                            <dd>13/10/2025</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Responsable taller', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['workshop']['contact']); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo $sample_case['peritaje_required'] ? esc_html__('Asignado', 'garantias-online-360vo') : esc_html__('Pendiente', 'garantias-online-360vo'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('Importe autorizado', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['financials']['authorized']); ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="averia-summary averia-summary--secondary">
                    <h2><?php esc_html_e('Próximos pasos', 'garantias-online-360vo'); ?></h2>
                    <ul>
                        <li><?php esc_html_e('Confirmar disponibilidad de piezas con el taller.', 'garantias-online-360vo'); ?></li>
                        <li><?php esc_html_e('Actualizar cliente con resolución provisional.', 'garantias-online-360vo'); ?></li>
                        <li><?php esc_html_e('Programar visita de perito si procede.', 'garantias-online-360vo'); ?></li>
                    </ul>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>

<style>
    .averia-detail {
        padding: clamp(1.5rem, 3vw, 2.5rem);
        width: min(1280px, 100%);
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: clamp(1.5rem, 3vw, 2.5rem);
    }

    .averia-detail__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        flex-wrap: wrap;
    }

    .averia-detail__heading {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .averia-detail__title {
        font-size: clamp(1.5rem, 2.4vw, 2.1rem);
        font-weight: 700;
        color: var(--go-color-0f172a);
        margin: 0;
    }

    [data-theme="dark"] .averia-detail__title,
    body[data-theme="dark"] .averia-detail__title {
        color: var(--go-color-dbeafe);
    }

    .averia-detail__subtitle {
        margin: 0;
        color: var(--go-color-475569);
        font-size: 0.95rem;
    }

    .averia-detail__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 999px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .averia-detail__badge-dot {
        width: 0.6rem;
        height: 0.6rem;
        border-radius: 50%;
        background: currentColor;
    }

    .averia-detail__badge--warning {
        background: rgba(255, 173, 66, 0.16);
        color: #bd6000;
    }

    .averia-detail__meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: var(--surface);
        border-radius: 1rem;
        box-shadow: 0 8px 24px rgba(var(--go-color-000000-rgb), 0.05);
    }

    .averia-detail__meta-item {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .averia-detail__meta-label {
        color: var(--go-color-64748b);
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .averia-detail__meta-value {
        font-weight: 600;
        color: var(--go-color-0f172a);
    }

    .guarantees-list__filters--averia-detail {
        margin-bottom: 0;
    }

    .guarantees-list__filters-row--averia-detail {
        width: 100%;
    }

    .averia-tabs {
        display: inline-flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .averia-tabs__button {
        border: 1px solid rgba(var(--go-color-0f172a-rgb), 0.12);
        background: var(--surface);
        color: var(--go-color-0f172a);
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .averia-tabs__button.is-active {
        background: var(--go-color-1d4ed8);
        border-color: var(--go-color-1d4ed8);
        color: #fff;
    }

    [data-theme="dark"] .averia-tabs__button,
    body[data-theme="dark"] .averia-tabs__button {
        border-color: rgba(255, 255, 255, 0.12);
        color: var(--go-color-dbeafe);
    }

    .averia-detail__action-btn {
        border: none;
        border-radius: 0.75rem;
        background: var(--go-color-1d4ed8);
        color: #fff;
        padding: 0.55rem 1.25rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 8px 14px rgba(var(--go-color-1f63ff-rgb), 0.18);
    }

    .averia-detail__action-btn--ghost {
        background: rgba(var(--go-color-1d4ed8-rgb), 0.08);
        color: var(--go-color-1d4ed8);
        box-shadow: none;
    }

    .averia-detail__action-btn--danger {
        background: var(--go-color-b91c1c);
        box-shadow: 0 8px 14px rgba(var(--go-color-b91c1c), 0.25);
    }

    .averia-detail__action-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none;
    }

    .averia-detail__layout {
        display: grid;
        grid-template-columns: minmax(0, 2.4fr) minmax(280px, 1fr);
        gap: clamp(1.5rem, 3vw, 2.5rem);
        align-items: start;
    }

    .averia-detail__layout.sticky-active {
        scroll-margin-top: 6.5rem;
    }

    @media (max-width: 1080px) {
        .averia-detail__layout {
            grid-template-columns: 1fr;
        }
    }

    .averia-detail__content {
        display: grid;
        gap: clamp(1.5rem, 2.5vw, 2rem);
    }

    .averia-panel {
        background: var(--surface);
        border-radius: 1.25rem;
        padding: clamp(1.5rem, 2.8vw, 2.25rem);
        box-shadow: 0 12px 32px rgba(var(--go-color-000000-rgb), 0.06);
    }

    .averia-panel[hidden] {
        display: none;
    }

    .averia-panel__header {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin-bottom: 1.5rem;
    }

    .averia-panel__header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--go-color-0f172a);
    }

    .averia-panel__header p {
        margin: 0;
        color: var(--go-color-475569);
        font-size: 0.95rem;
    }

    .averia-panel__body {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }

    .averia-panel__body--grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
    }

    .averia-panel__body--stretch {
        align-items: stretch;
    }

    .averia-panel__body--grid > .averia-card--stretch {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .averia-timeline {
        display: grid;
        gap: 1.25rem;
        position: relative;
    }

    .averia-timeline::before {
        content: '';
        position: absolute;
        top: 0.5rem;
        bottom: 0.5rem;
        left: 0.9rem;
        width: 2px;
        background: rgba(var(--go-color-0f172a-rgb), 0.1);
    }

    .averia-timeline__item {
        display: grid;
        grid-template-columns: 2rem 1fr;
        gap: 1rem;
        position: relative;
    }

    .averia-timeline__marker {
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 50%;
        border: 3px solid var(--go-color-1d4ed8);
        background: var(--surface);
        position: relative;
        z-index: 1;
        margin-top: 0.35rem;
    }

    .averia-timeline__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        font-weight: 600;
    }

    .averia-timeline__meta time {
        font-variant-numeric: tabular-nums;
        color: var(--go-color-0f172a);
    }

    .averia-timeline__type,
    .averia-timeline__actor {
        color: var(--go-color-475569);
        font-size: 0.95rem;
    }

    .averia-timeline__description {
        margin: 0.35rem 0 0;
        color: var(--go-color-1f2937);
        line-height: 1.55;
    }

    .averia-note-form fieldset {
        border: 1px dashed rgba(var(--go-color-0f172a-rgb), 0.15);
        border-radius: 1rem;
        padding: 1.5rem;
        display: grid;
        gap: 1rem;
    }

    .averia-note-form__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .averia-note-form__field {
        display: grid;
        gap: 0.4rem;
    }

    .averia-note-form__field > span {
        font-weight: 600;
        color: var(--go-color-475569);
        font-size: 0.9rem;
    }

    .averia-note-form__field select,
    .averia-note-form__field input,
    .averia-note-form__field textarea {
        border: 1px solid rgba(var(--go-color-0f172a-rgb), 0.12);
        border-radius: 0.75rem;
        padding: 0.65rem 0.9rem;
        background: rgba(var(--go-color-0f172a-rgb), 0.015);
        font-size: 0.95rem;
        resize: vertical;
    }

    .averia-note-form__actions {
        display: flex;
        justify-content: flex-end;
    }

    .averia-card {
        background: rgba(var(--go-color-0f172a-rgb), 0.02);
        border: 1px solid rgba(var(--go-color-0f172a-rgb), 0.08);
        border-radius: 1rem;
        padding: 1.5rem;
        display: grid;
        gap: 1rem;
    }

    .averia-card h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
    }

    .averia-card dl {
        margin: 0;
        display: grid;
        gap: 0.75rem;
    }

    .averia-card dl div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: baseline;
    }

    .averia-card dt {
        color: var(--go-color-64748b);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .averia-card dd {
        margin: 0;
        font-weight: 600;
        color: var(--go-color-0f172a);
    }

    .averia-card__notes {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.75rem;
        color: var(--go-color-475569);
    }

    .averia-card__notes li {
        display: grid;
        gap: 0.25rem;
    }

    .averia-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }

    .averia-table th,
    .averia-table td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid rgba(var(--go-color-0f172a-rgb), 0.08);
    }

    .averia-upload {
        border: 2px dashed rgba(var(--go-color-0f172a-rgb), 0.15);
        border-radius: 1rem;
        padding: 1.5rem;
        text-align: center;
        color: var(--go-color-475569);
        display: grid;
        gap: 1rem;
    }

    .averia-documents {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.75rem;
    }

    .averia-documents li {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        background: rgba(var(--go-color-1d4ed8-rgb), 0.05);
        font-weight: 600;
    }

    .averia-documents__meta {
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--go-color-475569);
    }

    .averia-detail__sidebar {
        display: grid;
        gap: 1.5rem;
    }

    .averia-summary {
        background: var(--surface);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 12px 32px rgba(var(--go-color-000000-rgb), 0.05);
        display: grid;
        gap: 1rem;
    }

    .averia-summary h2 {
        margin: 0;
        font-size: 1.1rem;
    }

    .averia-summary dl {
        margin: 0;
        display: grid;
        gap: 0.75rem;
    }

    .averia-summary dl div {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
    }

    .averia-summary dt {
        color: var(--go-color-64748b);
        font-size: 0.85rem;
    }

    .averia-summary dd {
        margin: 0;
        font-weight: 600;
        color: var(--go-color-0f172a);
    }

    .averia-summary ul {
        margin: 0;
        padding-left: 1.25rem;
        display: grid;
        gap: 0.75rem;
        color: var(--go-color-475569);
    }

    .averia-summary--secondary ul {
        list-style: disc;
    }

    .averia-detail__empty {
        display: flex;
        justify-content: center;
        padding: clamp(4rem, 10vw, 6rem) 0;
    }

    .averia-detail__empty-card {
        max-width: 420px;
        text-align: center;
        background: var(--surface);
        border-radius: 1.5rem;
        padding: 2.5rem;
        box-shadow: 0 20px 44px rgba(var(--go-color-000000-rgb), 0.08);
        display: grid;
        gap: 1rem;
    }

    .averia-detail__empty-card h2 {
        margin: 0;
        font-size: 1.35rem;
    }

    .averia-detail__back-link {
        color: var(--go-color-1d4ed8);
        font-weight: 600;
        text-decoration: none;
    }

    @media (max-width: 760px) {
        .averia-detail__header {
            align-items: flex-start;
        }

        .averia-detail__meta {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }

        .averia-detail__layout {
            grid-template-columns: 1fr;
        }

        .averia-detail__sidebar {
            grid-template-columns: 1fr;
        }
    }

    .guarantees-table__row[data-expediente-url] {
        cursor: pointer;
    }

    .guarantees-table__row[data-expediente-url]:hover {
        background: rgba(var(--go-color-1d4ed8-rgb), 0.08);
    }
</style>

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
    compact('is_breakdowns_page', 'is_dashboard_page')
);
