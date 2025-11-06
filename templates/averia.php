<?php
use GarantiasOnline360VO\Svg;

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
        'label' => __('Días abierto', 'garantias-online-360vo'),
        'value' => '3',
        'caption' => __('desde el 12/10/2025', 'garantias-online-360vo'),
    ],
    [
        'label' => __('Nivel de urgencia', 'garantias-online-360vo'),
        'value' => __('Alto', 'garantias-online-360vo'),
        'caption' => __('Impacto en movilidad del cliente', 'garantias-online-360vo'),
    ],
    [
        'label' => __('SLA restante', 'garantias-online-360vo'),
        'value' => '18h',
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

$initials_helper = static function (string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $text);
    $initials = '';

    if (is_array($parts)) {
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $initials .= function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($part, 0, 1))
                : strtoupper(substr($part, 0, 1));

            if (function_exists('mb_strlen')) {
                if (mb_strlen($initials) >= 2) {
                    break;
                }
            } elseif (strlen($initials) >= 2) {
                break;
            }
        }
    }

    if ($initials !== '') {
        return $initials;
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr($text, 0, 1))
        : strtoupper(substr($text, 0, 1));
};
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
        <?php
        $status_options = [
            'notificacion_averia' => __('Notificación de avería', 'garantias-online-360vo'),
            'abierta'             => __('Abierta', 'garantias-online-360vo'),
            'pendiente_taller'    => __('Pendiente de taller', 'garantias-online-360vo'),
            'espera_info'         => __('En espera de información', 'garantias-online-360vo'),
            'cerrada'             => __('Cerrada', 'garantias-online-360vo'),
        ];

        $current_status_key = $sample_case['status_key'] ?? 'sin_estado';
        $status_label       = $sample_case['status'] ?? __('Sin estado', 'garantias-online-360vo');
        $status_variant     = $sample_case['status_variant'] ?? 'info';
        $opened_label       = $sample_case['opened'] ?? '';
        $last_update_label  = $sample_case['last_update'] ?? '';

        $plate_value_source = ($sample_case['license_plate'] ?? '') !== ''
            ? (string) $sample_case['license_plate']
            : $license_plate;
        $plate_compact = strtoupper(str_replace(' ', '', (string) $plate_value_source));
        $expediente_label = trim((string) ($sample_case['reference'] ?? ''));
        if ($expediente_label === '' && $plate_compact !== '') {
            $expediente_label = 'EXP-AV-' . $plate_compact;
        }

        $vehicle_name  = $sample_case['vehicle_name'] ?? '';
        $vehicle_age   = $sample_case['vehicle_age'] ?? '';
        $summary_text  = $sample_case['summary'] ?? '';
        $owner_label   = $sample_case['owner'] ?? '';
        $peritaje_required = ! empty($sample_case['peritaje_required']);

        $insight_metrics = [
            __('Kilómetros al abrir', 'garantias-online-360vo')   => $sample_case['kilometers_start'] ?? '',
            __('Kilómetros actuales', 'garantias-online-360vo')   => $sample_case['kilometers_now'] ?? '',
            __('Variación', 'garantias-online-360vo')             => $sample_case['kilometers_delta'] ?? '',
            __('Elemento a cubrir', 'garantias-online-360vo')     => $sample_case['cover_element'] ?? '',
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
        ?>

        <header class="averia-detail__masthead">
            <div class="averia-detail__masthead-primary">
                <div class="averia-detail__status">
                    <span class="averia-status-badge averia-status-badge--<?php echo esc_attr($status_variant); ?>">
                        <?php echo esc_html($status_label); ?>
                    </span>
                    <?php if ($plate_value_source !== '') : ?>
                        <span class="averia-detail__plate" aria-label="<?php esc_attr_e('Matrícula asociada', 'garantias-online-360vo'); ?>">
                            <?php echo esc_html($plate_value_source); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h1 class="averia-detail__title">
                    <?php echo esc_html($expediente_label); ?>
                </h1>
                <?php if ($summary_text !== '') : ?>
                    <p class="averia-detail__lead"><?php echo esc_html($summary_text); ?></p>
                <?php endif; ?>
            </div>

            <div class="averia-detail__masthead-actions">
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

            <dl class="averia-detail__masthead-meta">
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
        </header>

        <div class="averia-detail__layout">
            <section class="averia-detail__summary" aria-label="<?php esc_attr_e('Resumen del expediente', 'garantias-online-360vo'); ?>">
                <article class="averia-card averia-card--highlight">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Situación actual', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php echo esc_html(trim($vehicle_name . ' · ' . $vehicle_age)); ?></p>
                    </header>
                    <dl class="averia-data-list">
                        <?php if ($plate_value_source !== '') : ?>
                            <div>
                                <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                                <dd><?php echo esc_html($plate_value_source); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($vehicle_name !== '') : ?>
                            <div>
                                <dt><?php esc_html_e('Vehículo', 'garantias-online-360vo'); ?></dt>
                                <dd><?php echo esc_html($vehicle_name); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($vehicle_age !== '') : ?>
                            <div>
                                <dt><?php esc_html_e('Antigüedad', 'garantias-online-360vo'); ?></dt>
                                <dd><?php echo esc_html($vehicle_age); ?></dd>
                            </div>
                        <?php endif; ?>
                        <?php if ($owner_label !== '') : ?>
                            <div>
                                <dt><?php esc_html_e('Titular', 'garantias-online-360vo'); ?></dt>
                                <dd><?php echo esc_html($owner_label); ?></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></dt>
                            <dd>
                                <span class="averia-pill<?php echo $peritaje_required ? ' averia-pill--alert' : ' averia-pill--success'; ?>">
                                    <?php echo $peritaje_required
                                        ? esc_html__('Requerido', 'garantias-online-360vo')
                                        : esc_html__('No requerido', 'garantias-online-360vo'); ?>
                                </span>
                            </dd>
                        </div>
                    </dl>
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Indicadores clave', 'garantias-online-360vo'); ?></h2>
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
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Cobertura y límites', 'garantias-online-360vo'); ?></h2>
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
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Diagnóstico inicial', 'garantias-online-360vo'); ?></h2>
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
                </article>
            </section>

            <section class="averia-detail__main" aria-label="<?php esc_attr_e('Gestión operativa', 'garantias-online-360vo'); ?>">
                <article class="averia-card averia-card--actions">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Gestión del expediente', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Estado, responsables y acciones inmediatas', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-actions">
                        <label class="averia-actions__field" for="averia-status-select">
                            <span><?php esc_html_e('Estado actual', 'garantias-online-360vo'); ?></span>
                            <select id="averia-status-select" class="averia-select">
                                <?php foreach ($status_options as $status_value => $status_option_label) : ?>
                                    <option value="<?php echo esc_attr($status_value); ?>"<?php selected($current_status_key, $status_value); ?>>
                                        <?php echo esc_html($status_option_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="averia-actions__buttons">
                            <button type="button" class="averia-button averia-button--secondary">
                                <?php esc_html_e('Añadir nota interna', 'garantias-online-360vo'); ?>
                            </button>
                            <button type="button" class="averia-button averia-button--secondary">
                                <?php esc_html_e('Crear tarea', 'garantias-online-360vo'); ?>
                            </button>
                            <button type="button" class="averia-button averia-button--ghost">
                                <?php esc_html_e('Ver comunicaciones', 'garantias-online-360vo'); ?>
                            </button>
                        </div>
                    </div>
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Timeline de actividades', 'garantias-online-360vo'); ?></h2>
                        <button type="button" class="averia-button averia-button--ghost">
                            <?php esc_html_e('Registrar evento', 'garantias-online-360vo'); ?>
                        </button>
                    </header>
                    <ol class="averia-timeline">
                        <?php foreach ($sample_history as $history_item) : ?>
                            <li class="averia-timeline__item">
                                <div class="averia-timeline__badge" aria-hidden="true"></div>
                                <div class="averia-timeline__content">
                                    <p class="averia-timeline__meta">
                                        <span><?php echo esc_html($history_item['date']); ?></span>
                                        <span>·</span>
                                        <span><?php echo esc_html($history_item['type']); ?></span>
                                        <span>·</span>
                                        <span><?php echo esc_html($history_item['actor']); ?></span>
                                    </p>
                                    <p class="averia-timeline__description"><?php echo esc_html($history_item['description']); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Checklist de seguimiento', 'garantias-online-360vo'); ?></h2>
                        <button type="button" class="averia-button averia-button--ghost">
                            <?php esc_html_e('Gestionar tareas', 'garantias-online-360vo'); ?>
                        </button>
                    </header>
                    <ul class="averia-checklist">
                        <?php foreach ($sample_checklist as $task) :
                            $status = $task['status'] ?? 'pending';
                            $status_label = [
                                'pending'     => __('Pendiente', 'garantias-online-360vo'),
                                'in_progress' => __('En curso', 'garantias-online-360vo'),
                                'done'        => __('Completada', 'garantias-online-360vo'),
                            ][$status] ?? __('Pendiente', 'garantias-online-360vo');
                            ?>
                            <li class="averia-checklist__item averia-checklist__item--<?php echo esc_attr($status); ?>">
                                <div class="averia-checklist__status" aria-hidden="true"></div>
                                <div class="averia-checklist__content">
                                    <p class="averia-checklist__label"><?php echo esc_html($task['label']); ?></p>
                                    <p class="averia-checklist__meta">
                                        <span><?php echo esc_html($status_label); ?></span>
                                        <?php if (! empty($task['owner'])) : ?>
                                            <span>· <?php echo esc_html($task['owner']); ?></span>
                                        <?php endif; ?>
                                        <?php if (! empty($task['due'])) : ?>
                                            <span>· <?php printf('%s %s', esc_html__('Vence', 'garantias-online-360vo'), esc_html($task['due'])); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Importes y cobertura', 'garantias-online-360vo'); ?></h2>
                        <button type="button" class="averia-button averia-button--ghost">
                            <?php esc_html_e('Añadir presupuesto', 'garantias-online-360vo'); ?>
                        </button>
                    </header>
                    <dl class="averia-data-list averia-data-list--grid">
                        <?php foreach ($financial_summary as $financial_label => $financial_value) :
                            $financial_value = trim((string) $financial_value);
                            if ($financial_value === '') {
                                continue;
                            }
                            ?>
                            <div>
                                <dt><?php echo esc_html($financial_label); ?></dt>
                                <dd><?php echo esc_html($financial_value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Documentación recibida', 'garantias-online-360vo'); ?></h2>
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
                </article>
            </section>

            <aside class="averia-detail__contacts" aria-label="<?php esc_attr_e('Contactos y notas', 'garantias-online-360vo'); ?>">
                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Contactos clave', 'garantias-online-360vo'); ?></h2>
                        <p class="averia-card__subtitle"><?php esc_html_e('Canales de comunicación directos', 'garantias-online-360vo'); ?></p>
                    </header>
                    <div class="averia-contact-grid">
                        <?php foreach ($contact_cards as $contact) :
                            $phone = $contact['phone'] ?? [];
                            $email = $contact['email'] ?? [];
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
                </article>

                <article class="averia-card">
                    <header class="averia-card__header">
                        <h2><?php esc_html_e('Notas internas', 'garantias-online-360vo'); ?></h2>
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
                </article>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page', 'is_breakdown_detail_page')
);
