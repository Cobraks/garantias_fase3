<?php
use GarantiasOnline360VO\Svg;

if (! defined('ABSPATH')) {
    exit;
}

$is_breakdowns_page       = true;
$is_dashboard_page        = false;
$is_auth_page             = false;
$is_breakdown_detail_page = true;

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
    'status_key'        => 'pendiente_taller',
    'status_variant'    => 'warning',
    'opened'            => '12/10/2025',
    'type'              => 'Motor',
    'summary'           => 'Pérdida de potencia y testigo de avería motor encendido.',
    'kilometers_start'  => '92.340 km',
    'kilometers_now'    => '94.210 km',
    'kilometers_delta'  => '+2.000 km',
    'vehicle_name'      => 'Seat Panda',
    'vehicle_age'       => '2015 (5 años)',
    'vendor'            => 'Concesionario Guay',
    'vendor_manager'    => 'Pepito Pérez',
    'owner'             => 'Laura García',
    'policy'            => 'Cobertura Exclusive Camiones',
    'limit_per_claim'   => '1.500,00 €',
    'limit_per_contract'=> '12.000,00 €',
    'last_update'       => '13/10/2025',
    'peritaje_required' => true,
    'cover_element'     => 'Por confirmar',
    'license_plate'     => '1234 ABC',
    'vehicle_details'   => '#',
    'sales_channel'     => [
        'role'        => __('Profesional', 'garantias-online-360vo'),
        'professional_type' => __('Profesional Compraventa', 'garantias-online-360vo'),
        'company'      => 'Concesionario Guay',
        'contact_name' => 'Pepito Pérez',
        'avatar'       => [
            'initials'   => 'CG',
            'background' => '#1d4ed8',
            'url'        => '',
        ],
        'phone'        => [
            'display' => '+34 600 123 456',
            'href'    => 'tel:+34600123456',
            'source'  => __('Teléfono principal', 'garantias-online-360vo'),
        ],
        'email'        => [
            'display' => 'gestion@concesionarioguay.com',
            'href'    => 'mailto:gestion@concesionarioguay.com',
            'source'  => __('Correo de notificaciones', 'garantias-online-360vo'),
        ],
        'details'      => [
            [
                'label' => __('Número de garantías activas', 'garantias-online-360vo'),
                'value' => '32',
            ],
            [
                'label' => __('Canal de venta', 'garantias-online-360vo'),
                'value' => __('Concesionario oficial', 'garantias-online-360vo'),
            ],
            [
                'label' => __('Método de pago preferido', 'garantias-online-360vo'),
                'value' => __('Domiciliación SEPA', 'garantias-online-360vo'),
            ],
            [
                'label' => __('Gestor asignado', 'garantias-online-360vo'),
                'value' => 'Isabel Gómez',
            ],
        ],
    ],
    'workshop'          => [
        'name'        => 'Talleres Pérez S.L.',
        'contact'     => 'María Pérez',
        'phone'       => [
            'display' => '+34 600 000 000',
            'href'    => 'tel:+34600000000',
            'source'  => __('Teléfono del taller', 'garantias-online-360vo'),
        ],
        'email'       => [
            'display' => 'taller@empresa.com',
            'href'    => 'mailto:taller@empresa.com',
            'source'  => __('Correo del taller', 'garantias-online-360vo'),
        ],
        'address'     => 'Calle de ejemplo, 12, Madrid',
        'type'        => 'Asociado',
        'tax_id'      => 'B12345678',
        'fiscal_name' => 'Talleres Pérez S.L.',
        'details'     => [
            [
                'label' => __('Tipo de taller', 'garantias-online-360vo'),
                'value' => __('Asociado', 'garantias-online-360vo'),
            ],
            [
                'label' => __('CIF/NIF', 'garantias-online-360vo'),
                'value' => 'B12345678',
            ],
            [
                'label' => __('Razón social', 'garantias-online-360vo'),
                'value' => 'Talleres Pérez S.L.',
            ],
            [
                'label' => __('Dirección', 'garantias-online-360vo'),
                'value' => 'Calle de ejemplo, 12, Madrid',
            ],
        ],
    ],
    'customer'          => [
        'name'    => 'Laura García',
        'phone'   => [
            'display' => '+34 610 555 982',
            'href'    => 'tel:+34610555982',
            'source'  => __('Teléfono de contacto', 'garantias-online-360vo'),
        ],
        'email'   => [
            'display' => 'laura.garcia@email.com',
            'href'    => 'mailto:laura.garcia@email.com',
            'source'  => __('Correo personal', 'garantias-online-360vo'),
        ],
        'details' => [
            [
                'label' => __('Documento', 'garantias-online-360vo'),
                'value' => 'DNI 12345678A',
            ],
            [
                'label' => __('Dirección', 'garantias-online-360vo'),
                'value' => 'Av. de la Innovación, 45, Sevilla',
            ],
            [
                'label' => __('Vehículo contratado', 'garantias-online-360vo'),
                'value' => 'SUV Premium 24',
            ],
            [
                'label' => __('Garantías totales', 'garantias-online-360vo'),
                'value' => '2',
            ],
        ],
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

$sample_metrics = [
    [
        'label'   => __('Días abierto', 'garantias-online-360vo'),
        'value'   => '3',
        'caption' => __('desde el 12/10/2025', 'garantias-online-360vo'),
    ],
    [
        'label'   => __('Nivel de urgencia', 'garantias-online-360vo'),
        'value'   => __('Alto', 'garantias-online-360vo'),
        'caption' => __('Impacto en movilidad del cliente', 'garantias-online-360vo'),
    ],
    [
        'label'   => __('SLA restante', 'garantias-online-360vo'),
        'value'   => '18h',
        'caption' => __('para autorizar presupuesto', 'garantias-online-360vo'),
    ],
];

$sample_checklist = [
    [
        'label'   => __('Solicitar peritaje presencial', 'garantias-online-360vo'),
        'due'     => '14/10/2025',
        'owner'   => 'Gestor 360VO',
        'status'  => 'pending',
    ],
    [
        'label'   => __('Validar documentación del taller', 'garantias-online-360vo'),
        'due'     => '15/10/2025',
        'owner'   => 'Admin 360VO',
        'status'  => 'in_progress',
    ],
    [
        'label'   => __('Confirmar cobertura con el cliente', 'garantias-online-360vo'),
        'due'     => '16/10/2025',
        'owner'   => 'Gestor 360VO',
        'status'  => 'done',
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
        <section class="averia-empty" aria-labelledby="averia-empty-title">
            <div class="averia-empty__card">
                <h2 id="averia-empty-title"><?php esc_html_e('Sin expediente disponible', 'garantias-online-360vo'); ?></h2>
                <p><?php echo esc_html($empty_messages[$view_mode] ?? ''); ?></p>
                <a class="averia-button averia-button--primary" href="<?php echo esc_url(home_url('/garantias-online/averias/')); ?>">
                    <?php esc_html_e('Volver al listado de averías', 'garantias-online-360vo'); ?>
                </a>
            </div>
        </section>
    <?php else : ?>
        <?php
        $status_label       = $sample_case['status'] ?? __('Sin estado', 'garantias-online-360vo');
        $status_variant     = $sample_case['status_variant'] ?? 'info';
        $opened_label       = $sample_case['opened'] ?? '';
        $last_update_label  = $sample_case['last_update'] ?? '';
        $owner_label        = $sample_case['owner'] ?? '';
        $summary_text       = $sample_case['summary'] ?? '';
        $vehicle_name       = $sample_case['vehicle_name'] ?? '';
        $vehicle_age        = $sample_case['vehicle_age'] ?? '';
        $peritaje_required  = ! empty($sample_case['peritaje_required']);
        $expediente_label   = trim((string) ($sample_case['reference'] ?? ''));
        $plate_value_source = ($sample_case['license_plate'] ?? '') !== ''
            ? (string) $sample_case['license_plate']
            : $license_plate;

        if ($expediente_label === '' && $plate_value_source !== '') {
            $expediente_label = 'EXP-AV-' . strtoupper(str_replace(' ', '', (string) $plate_value_source));
        }

        $context_overview = [
            __('Vehículo', 'garantias-online-360vo')        => $vehicle_name,
            __('Antigüedad', 'garantias-online-360vo')      => $vehicle_age,
            __('Titular', 'garantias-online-360vo')         => $owner_label,
            __('Estado del peritaje', 'garantias-online-360vo') => $peritaje_required
                ? __('Peritaje pendiente', 'garantias-online-360vo')
                : __('Peritaje no requerido', 'garantias-online-360vo'),
        ];

        $insight_metrics = [
            __('Kilómetros al abrir', 'garantias-online-360vo') => $sample_case['kilometers_start'] ?? '',
            __('Kilómetros actuales', 'garantias-online-360vo') => $sample_case['kilometers_now'] ?? '',
            __('Variación', 'garantias-online-360vo')           => $sample_case['kilometers_delta'] ?? '',
            __('Elemento a cubrir', 'garantias-online-360vo')   => $sample_case['cover_element'] ?? '',
        ];

        $guarantee_details = [
            __('Garantía asociada', 'garantias-online-360vo')   => $sample_case['policy'] ?? '',
            __('Límite por avería', 'garantias-online-360vo')   => $sample_case['limit_per_claim'] ?? '',
            __('Límite por contrato', 'garantias-online-360vo') => $sample_case['limit_per_contract'] ?? '',
            __('Vendedor', 'garantias-online-360vo')            => $sample_case['vendor'] ?? '',
            __('Gestor del vendedor', 'garantias-online-360vo') => $sample_case['vendor_manager'] ?? '',
        ];

        $financial_summary = [
            __('Presupuesto estimado', 'garantias-online-360vo') => $sample_case['financials']['budget'] ?? '',
            __('Importe autorizado', 'garantias-online-360vo')   => $sample_case['financials']['authorized'] ?? '',
            __('Resolución propuesta', 'garantias-online-360vo') => $sample_case['financials']['resolution'] ?? '',
        ];

        $contact_cards = [
            [
                'chip'     => $sample_case['sales_channel']['role'] ?? __('Profesional', 'garantias-online-360vo'),
                'title'    => $sample_case['sales_channel']['company'] ?? '',
                'subtitle' => $sample_case['sales_channel']['contact_name'] ?? '',
                'meta'     => $sample_case['sales_channel']['professional_type'] ?? '',
                'phone'    => $sample_case['sales_channel']['phone'] ?? [],
                'email'    => $sample_case['sales_channel']['email'] ?? [],
                'details'  => $sample_case['sales_channel']['details'] ?? [],
            ],
            [
                'chip'     => __('Taller', 'garantias-online-360vo'),
                'title'    => $sample_case['workshop']['name'] ?? '',
                'subtitle' => $sample_case['workshop']['contact'] ?? '',
                'meta'     => $sample_case['workshop']['type'] ?? '',
                'phone'    => $sample_case['workshop']['phone'] ?? [],
                'email'    => $sample_case['workshop']['email'] ?? [],
                'details'  => array_merge(
                    (array) ($sample_case['workshop']['details'] ?? []),
                    [
                        [
                            'label' => __('Dirección', 'garantias-online-360vo'),
                            'value' => $sample_case['workshop']['address'] ?? '',
                        ],
                    ]
                ),
            ],
            [
                'chip'     => __('Cliente', 'garantias-online-360vo'),
                'title'    => $sample_case['customer']['name'] ?? '',
                'subtitle' => '',
                'meta'     => '',
                'phone'    => $sample_case['customer']['phone'] ?? [],
                'email'    => $sample_case['customer']['email'] ?? [],
                'details'  => $sample_case['customer']['details'] ?? [],
            ],
        ];

        $checklist_status_labels = [
            'pending'     => __('Pendiente', 'garantias-online-360vo'),
            'in_progress' => __('En progreso', 'garantias-online-360vo'),
            'done'        => __('Completada', 'garantias-online-360vo'),
        ];
        ?>

        <header class="averia-hero">
            <div class="averia-hero__primary">
                <div class="averia-hero__status">
                    <span class="averia-status-badge averia-status-badge--<?php echo esc_attr($status_variant); ?>">
                        <?php echo esc_html($status_label); ?>
                    </span>
                    <?php if ($plate_value_source !== '') : ?>
                        <span class="averia-hero__plate" aria-label="<?php esc_attr_e('Matrícula asociada', 'garantias-online-360vo'); ?>">
                            <?php echo esc_html($plate_value_source); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="averia-hero__title"><?php echo esc_html($expediente_label); ?></h1>
                <?php if ($summary_text !== '') : ?>
                    <p class="averia-hero__summary"><?php echo esc_html($summary_text); ?></p>
                <?php endif; ?>
            </div>

            <dl class="averia-hero__meta">
                <?php if ($opened_label !== '') : ?>
                    <div>
                        <dt><?php esc_html_e('Fecha de apertura', 'garantias-online-360vo'); ?></dt>
                        <dd><?php echo esc_html($opened_label); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($last_update_label !== '') : ?>
                    <div>
                        <dt><?php esc_html_e('Última actualización', 'garantias-online-360vo'); ?></dt>
                        <dd><?php echo esc_html($last_update_label); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($owner_label !== '') : ?>
                    <div>
                        <dt><?php esc_html_e('Responsable actual', 'garantias-online-360vo'); ?></dt>
                        <dd><?php echo esc_html($owner_label); ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <div class="averia-hero__actions">
                <button type="button" class="averia-button averia-button--primary">
                    <?php esc_html_e('Cambiar estado', 'garantias-online-360vo'); ?>
                </button>
                <button type="button" class="averia-button averia-button--surface">
                    <?php esc_html_e('Registrar actualización', 'garantias-online-360vo'); ?>
                </button>
                <button type="button" class="averia-button averia-button--surface">
                    <?php esc_html_e('Enviar comunicación', 'garantias-online-360vo'); ?>
                </button>
            </div>
        </header>

        <div class="averia-layout">
            <main class="averia-layout__main" aria-label="<?php esc_attr_e('Gestión de la avería', 'garantias-online-360vo'); ?>">
                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Acciones rápidas', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Prioriza las próximas intervenciones', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-quick-actions">
                        <button type="button" class="averia-button averia-button--primary">
                            <?php esc_html_e('Actualizar estado', 'garantias-online-360vo'); ?>
                        </button>
                        <button type="button" class="averia-button averia-button--surface">
                            <?php esc_html_e('Añadir evento', 'garantias-online-360vo'); ?>
                        </button>
                        <button type="button" class="averia-button averia-button--surface">
                            <?php esc_html_e('Enviar correo', 'garantias-online-360vo'); ?>
                        </button>
                    </div>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Checklist operativa', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Controla los hitos clave del expediente', 'garantias-online-360vo'); ?></p>
                    </header>
                    <ul class="averia-checklist">
                        <?php foreach ($sample_checklist as $item) :
                            $status_key   = $item['status'] ?? 'pending';
                            $status_label = $checklist_status_labels[$status_key] ?? $checklist_status_labels['pending'];
                            ?>
                            <li class="averia-checklist__item averia-checklist__item--<?php echo esc_attr($status_key); ?>">
                                <div class="averia-checklist__marker" aria-hidden="true"></div>
                                <div class="averia-checklist__content">
                                    <p class="averia-checklist__label"><?php echo esc_html($item['label']); ?></p>
                                    <p class="averia-checklist__meta">
                                        <span><?php printf(esc_html__('Responsable: %s', 'garantias-online-360vo'), esc_html($item['owner'])); ?></span>
                                        <span><?php printf(esc_html__('Límite: %s', 'garantias-online-360vo'), esc_html($item['due'])); ?></span>
                                    </p>
                                </div>
                                <span class="averia-checklist__status"><?php echo esc_html($status_label); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Historial de actividad', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Seguimiento centralizado de comunicaciones y cambios', 'garantias-online-360vo'); ?></p>
                    </header>
                    <ol class="averia-timeline">
                        <?php foreach ($sample_history as $entry) : ?>
                            <li class="averia-timeline__item">
                                <div class="averia-timeline__point" aria-hidden="true"></div>
                                <div class="averia-timeline__body">
                                    <header class="averia-timeline__header">
                                        <span class="averia-timeline__type"><?php echo esc_html($entry['type']); ?></span>
                                        <time datetime="<?php echo esc_attr($entry['date']); ?>"><?php echo esc_html($entry['date']); ?></time>
                                    </header>
                                    <p class="averia-timeline__actor"><?php echo esc_html($entry['actor']); ?></p>
                                    <p class="averia-timeline__description"><?php echo esc_html($entry['description']); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Gestión económica', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Revisa presupuestos y autorizaciones', 'garantias-online-360vo'); ?></p>
                    </header>
                    <dl class="averia-data-list">
                        <?php foreach ($financial_summary as $label => $value) :
                            $value = trim((string) $value);
                            if ($value === '') {
                                continue;
                            }
                            ?>
                            <div>
                                <dt><?php echo esc_html($label); ?></dt>
                                <dd><?php echo esc_html($value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            </main>

            <aside class="averia-layout__context" aria-label="<?php esc_attr_e('Contexto del expediente', 'garantias-online-360vo'); ?>">
                <section class="averia-card averia-card--highlight">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Resumen del expediente', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Datos clave del vehículo y titular', 'garantias-online-360vo'); ?></p>
                    </header>
                    <dl class="averia-data-list">
                        <?php if ($plate_value_source !== '') : ?>
                            <div>
                                <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                                <dd><?php echo esc_html($plate_value_source); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($context_overview as $context_label => $context_value) :
                            $context_value = trim((string) $context_value);
                            if ($context_value === '') {
                                continue;
                            }
                            ?>
                            <div>
                                <dt><?php echo esc_html($context_label); ?></dt>
                                <dd><?php echo esc_html($context_value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Indicadores de seguimiento', 'garantias-online-360vo'); ?></h2>
                    </header>
                    <ul class="averia-metrics">
                        <?php foreach ($sample_metrics as $metric) : ?>
                            <li class="averia-metrics__item">
                                <span class="averia-metrics__label"><?php echo esc_html($metric['label']); ?></span>
                                <strong class="averia-metrics__value"><?php echo esc_html($metric['value']); ?></strong>
                                <?php if (! empty($metric['caption'])) : ?>
                                    <span class="averia-metrics__caption"><?php echo esc_html($metric['caption']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Cobertura y límites', 'garantias-online-360vo'); ?></h2>
                    </header>
                    <dl class="averia-data-list">
                        <?php foreach ($guarantee_details as $detail_label => $detail_value) :
                            $detail_value = trim((string) $detail_value);
                            if ($detail_value === '') {
                                continue;
                            }
                            ?>
                            <div>
                                <dt><?php echo esc_html($detail_label); ?></dt>
                                <dd><?php echo esc_html($detail_value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Insights de avería', 'garantias-online-360vo'); ?></h2>
                    </header>
                    <dl class="averia-data-list">
                        <?php foreach ($insight_metrics as $metric_label => $metric_value) :
                            $metric_value = trim((string) $metric_value);
                            if ($metric_value === '') {
                                continue;
                            }
                            ?>
                            <div>
                                <dt><?php echo esc_html($metric_label); ?></dt>
                                <dd><?php echo esc_html($metric_value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            </aside>

            <aside class="averia-layout__support" aria-label="<?php esc_attr_e('Soporte y documentación', 'garantias-online-360vo'); ?>">
                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h2>
                        <button type="button" class="averia-button averia-button--ghost">
                            <?php esc_html_e('Subir archivo', 'garantias-online-360vo'); ?>
                        </button>
                    </header>
                    <ul class="averia-documents">
                        <?php foreach ($sample_documents as $document) : ?>
                            <li class="averia-documents__item">
                                <div class="averia-documents__info">
                                    <p class="averia-documents__label"><?php echo esc_html($document['label']); ?></p>
                                    <span class="averia-documents__meta"><?php echo esc_html($document['type'] . ' · ' . $document['size']); ?></span>
                                </div>
                                <button type="button" class="averia-button averia-button--inline">
                                    <?php esc_html_e('Previsualizar', 'garantias-online-360vo'); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Contactos clave', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Canales de comunicación directos', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-contact-grid">
                        <?php foreach ($contact_cards as $contact) :
                            $phone   = $contact['phone'] ?? [];
                            $email   = $contact['email'] ?? [];
                            $details = $contact['details'] ?? [];
                            ?>
                            <article class="averia-contact">
                                <header class="averia-contact__header">
                                    <?php if (! empty($contact['chip'])) : ?>
                                        <span class="averia-contact__chip"><?php echo esc_html($contact['chip']); ?></span>
                                    <?php endif; ?>
                                    <h3 class="averia-contact__title"><?php echo esc_html($contact['title']); ?></h3>
                                    <?php if (! empty($contact['subtitle'])) : ?>
                                        <p class="averia-contact__subtitle"><?php echo esc_html($contact['subtitle']); ?></p>
                                    <?php endif; ?>
                                    <?php if (! empty($contact['meta'])) : ?>
                                        <p class="averia-contact__meta"><?php echo esc_html($contact['meta']); ?></p>
                                    <?php endif; ?>
                                </header>
                                <div class="averia-contact__channels">
                                    <?php if (! empty($phone['href']) && ! empty($phone['display'])) : ?>
                                        <a class="averia-contact__channel" href="<?php echo esc_url($phone['href']); ?>">
                                            <?php echo Svg::icon('phone', 'averia-contact__icon'); ?>
                                            <span><?php echo esc_html($phone['display']); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (! empty($email['href']) && ! empty($email['display'])) : ?>
                                        <a class="averia-contact__channel" href="<?php echo esc_url($email['href']); ?>">
                                            <?php echo Svg::icon('email', 'averia-contact__icon'); ?>
                                            <span><?php echo esc_html($email['display']); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php if (! empty($details)) : ?>
                                    <dl class="averia-contact__details">
                                        <?php foreach ($details as $detail) :
                                            $detail_value = trim((string) ($detail['value'] ?? ''));
                                            if ($detail_value === '') {
                                                continue;
                                            }
                                            ?>
                                            <div>
                                                <dt><?php echo esc_html($detail['label'] ?? ''); ?></dt>
                                                <dd><?php echo esc_html($detail_value); ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="averia-card">
                    <header class="averia-card__header">
                        <h2 class="averia-card__title"><?php esc_html_e('Notas internas', 'garantias-online-360vo'); ?></h2>
                        <button type="button" class="averia-button averia-button--ghost">
                            <?php esc_html_e('Añadir nota', 'garantias-online-360vo'); ?>
                        </button>
                    </header>
                    <div class="averia-notes">
                        <?php foreach ($sample_notes as $note) : ?>
                            <article class="averia-note">
                                <h3 class="averia-note__title"><?php echo esc_html($note['title']); ?></h3>
                                <p class="averia-note__body"><?php echo esc_html($note['body']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page', 'is_breakdown_detail_page')
);
