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
        $current_status_key   = $sample_case['status_key'] ?? 'sin_estado';
        $opened_label         = $sample_case['opened'] ?? '';
        $last_update_label    = $sample_case['last_update'] ?? '';
        $plate_value_source   = ($sample_case['license_plate'] ?? '') !== ''
            ? (string) $sample_case['license_plate']
            : $license_plate;
        $plate_compact        = strtoupper(str_replace(' ', '', (string) $plate_value_source));
        $expediente_label     = trim((string) ($sample_case['reference'] ?? ''));
        if ($expediente_label === '' && $plate_compact !== '') {
            $expediente_label = 'EXP-AV-' . $plate_compact;
        }
        ?>
        <div
            class="guarantees-list__filters guarantees-list__filters--averia-detail averia-detail__filters"
            data-sticky-target=".averia-detail__columns"
            style="view-transition-name: filtros-averia-detalle"
        >
            <div class="averia-detail__filters-row">
                <div class="averia-detail__filters-meta">
                    <div class="averia-detail__identity">
                        <div class="averia-detail__identity-block averia-detail__identity-block--plate">
                            <span class="averia-detail__identity-label"><?php esc_html_e('Expediente nº', 'garantias-online-360vo'); ?></span>
                            <p class="averia-detail__identity-value averia-detail__identity-value--plate" aria-label="<?php esc_attr_e('Identificador del expediente', 'garantias-online-360vo'); ?>">
                                <?php echo esc_html($expediente_label); ?>
                            </p>
                        </div>
                    </div>
                    <label class="averia-detail__status" for="averia-status-select">
                        <span class="screen-reader-text"><?php esc_html_e('Estado de la avería', 'garantias-online-360vo'); ?></span>
                        <select
                            id="averia-status-select"
                            name="averia-status-select"
                            class="averia-detail__status-select"
                        >
                            <?php foreach ($status_options as $status_value => $status_label) : ?>
                                <option value="<?php echo esc_attr($status_value); ?>"<?php selected($current_status_key, $status_value); ?>>
                                    <?php echo esc_html($status_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="averia-detail__filters-tabs">
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
                </div>

                <div class="averia-detail__filters-info">
                    <div class="averia-detail__date">
                        <span class="averia-detail__date-label"><?php esc_html_e('Fecha de apertura', 'garantias-online-360vo'); ?></span>
                        <strong class="averia-detail__date-value"><?php echo esc_html($opened_label); ?></strong>
                    </div>
                    <span class="averia-detail__info-divider" aria-hidden="true">•</span>
                    <div class="averia-detail__date">
                        <span class="averia-detail__date-label"><?php esc_html_e('Última actualización', 'garantias-online-360vo'); ?></span>
                        <strong class="averia-detail__date-value"><?php echo esc_html($last_update_label); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $contact_cards = [
            [
                'label'      => $sample_case['sales_channel']['role'] ?? __('Profesional', 'garantias-online-360vo'),
                'type_label' => $sample_case['sales_channel']['professional_type'] ?? '',
                'title'      => $sample_case['sales_channel']['company'],
                'subtitle'   => $sample_case['sales_channel']['contact_name'],
                'avatar'   => [
                    'initials'   => $sample_case['sales_channel']['avatar']['initials']
                        ?: $initials_helper($sample_case['sales_channel']['company']),
                    'background' => $sample_case['sales_channel']['avatar']['background'] ?? '#1d4ed8',
                    'url'        => $sample_case['sales_channel']['avatar']['url'] ?? '',
                ],
                'phone'    => $sample_case['sales_channel']['phone'],
                'email'    => $sample_case['sales_channel']['email'],
                'cta'      => __('Detalles profesional', 'garantias-online-360vo'),
                'details'  => array_merge(
                    (array) ($sample_case['sales_channel']['details'] ?? []),
                    [
                        [
                            'label' => __('Correo preferente', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['sales_channel']['email']['display'] ?? '')
                                . (! empty($sample_case['sales_channel']['email']['source'])
                                    ? ' — ' . $sample_case['sales_channel']['email']['source']
                                    : '')
                            ),
                        ],
                        [
                            'label' => __('Teléfono principal', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['sales_channel']['phone']['display'] ?? '')
                                . (! empty($sample_case['sales_channel']['phone']['source'])
                                    ? ' — ' . $sample_case['sales_channel']['phone']['source']
                                    : '')
                            ),
                        ],
                    ]
                ),
            ],
            [
                'label'      => __('Taller', 'garantias-online-360vo'),
                'type_label' => $sample_case['workshop']['type'] ?? '',
                'title'      => $sample_case['workshop']['name'],
                'subtitle'   => $sample_case['workshop']['contact'],
                'avatar'   => [
                    'initials'   => $initials_helper($sample_case['workshop']['name']),
                    'background' => '#0f766e',
                    'url'        => '',
                ],
                'phone'    => [
                    'display' => $sample_case['workshop']['phone']['display'] ?? '',
                    'href'    => $sample_case['workshop']['phone']['href'] ?? '',
                    'source'  => $sample_case['workshop']['phone']['source'] ?? '',
                ],
                'email'    => [
                    'display' => $sample_case['workshop']['email']['display'] ?? '',
                    'href'    => $sample_case['workshop']['email']['href'] ?? '',
                    'source'  => $sample_case['workshop']['email']['source'] ?? '',
                ],
                'cta'      => __('Detalles taller', 'garantias-online-360vo'),
                'details'  => array_merge(
                    (array) ($sample_case['workshop']['details'] ?? []),
                    [
                        [
                            'label' => __('Correo electrónico', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['workshop']['email']['display'] ?? '')
                                . (! empty($sample_case['workshop']['email']['source'])
                                    ? ' — ' . $sample_case['workshop']['email']['source']
                                    : '')
                            ),
                        ],
                        [
                            'label' => __('Teléfono', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['workshop']['phone']['display'] ?? '')
                                . (! empty($sample_case['workshop']['phone']['source'])
                                    ? ' — ' . $sample_case['workshop']['phone']['source']
                                    : '')
                            ),
                        ],
                    ]
                ),
            ],
            [
                'label'      => __('Cliente final', 'garantias-online-360vo'),
                'type_label' => '',
                'title'      => $sample_case['customer']['name'],
                'subtitle'   => '',
                'avatar'   => [
                    'initials'   => $initials_helper($sample_case['customer']['name']),
                    'background' => '#9333ea',
                    'url'        => '',
                ],
                'phone'    => $sample_case['customer']['phone'],
                'email'    => $sample_case['customer']['email'],
                'cta'      => __('Detalles cliente final', 'garantias-online-360vo'),
                'details'  => array_merge(
                    (array) ($sample_case['customer']['details'] ?? []),
                    [
                        [
                            'label' => __('Correo electrónico', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['customer']['email']['display'] ?? '')
                                . (! empty($sample_case['customer']['email']['source'])
                                    ? ' — ' . $sample_case['customer']['email']['source']
                                    : '')
                            ),
                        ],
                        [
                            'label' => __('Teléfono de contacto', 'garantias-online-360vo'),
                            'value' => trim(
                                ($sample_case['customer']['phone']['display'] ?? '')
                                . (! empty($sample_case['customer']['phone']['source'])
                                    ? ' — ' . $sample_case['customer']['phone']['source']
                                    : '')
                            ),
                        ],
                    ]
                ),
            ],
        ];

        $notes_count     = isset($sample_notes) && is_countable($sample_notes) ? count($sample_notes) : 0;
        $documents_count = isset($sample_documents) && is_countable($sample_documents) ? count($sample_documents) : 0;

        $opened_datetime      = null;
        $last_update_datetime = null;
        $days_open_label      = '';

        if ($opened_label !== '') {
            $opened_datetime = \DateTime::createFromFormat('d/m/Y', $opened_label) ?: null;
        }

        if ($last_update_label !== '') {
            $last_update_datetime = \DateTime::createFromFormat('d/m/Y', $last_update_label) ?: null;
        }

        if ($opened_datetime instanceof \DateTime && $last_update_datetime instanceof \DateTime) {
            $diff_days = max($opened_datetime->diff($last_update_datetime)->days, 0);
            if ($diff_days === 0) {
                $days_open_label = __('Menos de 24h', 'garantias-online-360vo');
            } else {
                $days_open_label = sprintf(
                    _n('%s día', '%s días', $diff_days, 'garantias-online-360vo'),
                    number_format_i18n($diff_days)
                );
            }
        }

        $insight_metrics = [];

        if ($days_open_label !== '') {
            $insight_metrics[__('Tiempo en curso', 'garantias-online-360vo')] = $days_open_label;
        }

        if ($last_update_label !== '') {
            $insight_metrics[__('Último movimiento', 'garantias-online-360vo')] = $last_update_label;
        }

        $insight_metrics[__('Notas registradas', 'garantias-online-360vo')] = sprintf(
            _n('%s nota', '%s notas', $notes_count, 'garantias-online-360vo'),
            number_format_i18n($notes_count)
        );

        $insight_metrics[__('Documentos adjuntos', 'garantias-online-360vo')] = sprintf(
            _n('%s documento', '%s documentos', $documents_count, 'garantias-online-360vo'),
            number_format_i18n($documents_count)
        );

        $insight_metrics[__('Peritaje', 'garantias-online-360vo')] = $sample_case['peritaje_required']
            ? __('Requerido', 'garantias-online-360vo')
            : __('No requerido', 'garantias-online-360vo');
        ?>

        <div class="averia-detail__columns">
            <aside class="averia-detail__column averia-detail__column--context" aria-label="<?php esc_attr_e('Contexto del expediente', 'garantias-online-360vo'); ?>">
                <section class="averia-card averia-card--context">
                    <header class="averia-card__header averia-card__header--context">
                        <p class="averia-card__eyebrow"><?php esc_html_e('Garantía vinculada', 'garantias-online-360vo'); ?></p>
                        <h3 class="averia-card__title averia-card__title--compact"><?php echo esc_html($sample_case['policy']); ?></h3>
                    </header>
                    <table class="averia-card__table" aria-label="<?php esc_attr_e('Datos de la garantía vinculada', 'garantias-online-360vo'); ?>">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></th>
                                <td><?php echo esc_html($sample_case['type']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></th>
                                <td><?php echo $sample_case['peritaje_required'] ? esc_html__('Requerido', 'garantias-online-360vo') : esc_html__('No requerido', 'garantias-online-360vo'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Límites por avería', 'garantias-online-360vo'); ?></th>
                                <td><?php echo esc_html($sample_case['limit_per_claim'] ?? '—'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Límites por contrato', 'garantias-online-360vo'); ?></th>
                                <td><?php echo esc_html($sample_case['limit_per_contract'] ?? '—'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="averia-card averia-card--vehicle">
                    <header class="averia-card__header">
                        <p class="averia-card__eyebrow"><?php esc_html_e('Ficha del vehículo', 'garantias-online-360vo'); ?></p>
                        <h3 class="averia-card__subtitle"><?php echo esc_html($sample_case['vehicle_name']); ?></h3>
                        <p class="averia-card__description"><?php esc_html_e('Datos clave para el seguimiento del vehículo implicado.', 'garantias-online-360vo'); ?></p>
                    </header>
                    <dl class="averia-meta-list averia-meta-list--grid averia-meta-list--vehicle">
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Matrícula', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html(($sample_case['license_plate'] ?? '') !== '' ? $sample_case['license_plate'] : $license_plate); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Antigüedad', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['vehicle_age']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Kilómetros contratación', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['kilometers_start']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Kilómetros entrada taller', 'garantias-online-360vo'); ?></dt>
                            <dd>
                                <?php
                                echo esc_html($sample_case['kilometers_now']);
                                if (! empty($sample_case['kilometers_delta'])) {
                                    echo ' (' . esc_html($sample_case['kilometers_delta']) . ')';
                                }
                                ?>
                            </dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Propietario', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['owner']); ?></dd>
                        </div>
                        <div class="averia-meta-list__item">
                            <dt><?php esc_html_e('Vendedor', 'garantias-online-360vo'); ?></dt>
                            <dd><?php echo esc_html($sample_case['vendor']); ?></dd>
                        </div>
                    </dl>
                    <div class="averia-card__footer">
                        <a class="averia-card__link" href="<?php echo esc_url($sample_case['vehicle_details']); ?>">
                            <?php esc_html_e('Ver ficha completa del vehículo', 'garantias-online-360vo'); ?>
                        </a>
                    </div>
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

            <aside class="averia-detail__column averia-detail__column--support" aria-label="<?php esc_attr_e('Resumen operativo del expediente', 'garantias-online-360vo'); ?>">
                <details class="averia-summary-card averia-summary-card--contacts averia-collapsible" open>
                    <summary class="averia-collapsible__summary">
                        <h2><?php esc_html_e('Contactos clave', 'garantias-online-360vo'); ?></h2>
                        <span class="averia-collapsible__icon" aria-hidden="true"></span>
                    </summary>
                    <div class="averia-collapsible__content">
                        <div class="averia-contacts">
                            <?php foreach ($contact_cards as $contact) :
                                $avatar          = $contact['avatar'] ?? [];
                                $phone           = $contact['phone'] ?? [];
                                $email           = $contact['email'] ?? [];
                                $type_label      = $contact['type_label'] ?? '';
                                $details         = array_filter($contact['details'] ?? [], static function ($entry) {
                                    return is_array($entry) && ($entry['value'] ?? '') !== '';
                                });
                                $avatar_style    = '';
                                $avatar_has_img  = ! empty($avatar['url']);
                                if (! empty($avatar['background'])) {
                                    $avatar_style .= 'background-color:' . esc_attr($avatar['background']) . ';';
                                }
                                if ($avatar_has_img) {
                                    $avatar_style .= 'background-image:url(' . esc_url($avatar['url']) . ');';
                                }
                                ?>
                                <article
                                    class="averia-contact-card"
                                    data-contact-label="<?php echo esc_attr($contact['label']); ?>"
                                    data-contact-title="<?php echo esc_attr($contact['title']); ?>"
                                    data-contact-subtitle="<?php echo esc_attr($contact['subtitle']); ?>"
                                >
                                    <header class="averia-contact-card__header">
                                        <div
                                            class="averia-contact-card__avatar"
                                            data-has-image="<?php echo $avatar_has_img ? 'true' : 'false'; ?>"
                                            <?php if ($avatar_style !== '') : ?>style="<?php echo esc_attr($avatar_style); ?>"<?php endif; ?>
                                        >
                                            <?php if (! $avatar_has_img && ! empty($avatar['initials'])) : ?>
                                                <span aria-hidden="true"><?php echo esc_html($avatar['initials']); ?></span>
                                            <?php endif; ?>
                                            <span class="averia-contact-card__avatar-sr"><?php echo esc_html($contact['label'] . ' · ' . $contact['title']); ?></span>
                                        </div>
                                        <div class="averia-contact-card__identity">
                                            <div class="averia-contact-card__label-row">
                                                <p class="averia-contact-card__label"><?php echo esc_html($contact['label']); ?></p>
                                                <?php if ($type_label !== '') : ?>
                                                    <p class="averia-contact-card__type"><?php echo esc_html($type_label); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <h3 class="averia-contact-card__title"><?php echo esc_html($contact['title']); ?></h3>
                                            <?php if (! empty($contact['subtitle'])) : ?>
                                                <p class="averia-contact-card__subtitle"><?php echo esc_html($contact['subtitle']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <button
                                            type="button"
                                            class="averia-contact-card__details-trigger"
                                            data-contact-details
                                            aria-haspopup="dialog"
                                            aria-expanded="false"
                                        >
                                            <?php echo Svg::icon('info', 'averia-contact-card__details-icon'); ?>
                                            <span><?php echo esc_html($contact['cta']); ?></span>
                                        </button>
                                    </header>
                                    <div class="averia-contact-card__actions">
                                        <?php if (! empty($phone['href']) && ! empty($phone['display'])) : ?>
                                            <a class="averia-contact-card__action" href="<?php echo esc_url($phone['href']); ?>">
                                                <?php echo Svg::icon('phone', 'averia-contact-card__action-icon'); ?>
                                                <span><?php echo esc_html($phone['display']); ?></span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (! empty($email['href']) && ! empty($email['display'])) : ?>
                                            <a class="averia-contact-card__action" href="<?php echo esc_url($email['href']); ?>">
                                                <?php echo Svg::icon('email', 'averia-contact-card__action-icon'); ?>
                                                <span><?php echo esc_html($email['display']); ?></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $has_contact_links = (is_array($phone) && ! empty($phone['href']) && ! empty($phone['display']))
                                        || (is_array($email) && ! empty($email['href']) && ! empty($email['display']));
                                    $has_details = ! empty($details);
                                    if ($has_contact_links || $has_details) :
                                        ?>
                                        <div class="averia-contact-card__details" hidden>
                                            <?php if ($has_contact_links) : ?>
                                                <div class="averia-contact-card__details-actions" role="group">
                                                    <?php if (! empty($phone['href']) && ! empty($phone['display'])) : ?>
                                                        <a href="<?php echo esc_url($phone['href']); ?>">
                                                            <?php echo Svg::icon('phone', 'averia-contact-card__action-icon'); ?>
                                                            <span class="averia-contact-card__details-text">
                                                                <span><?php echo esc_html($phone['display']); ?></span>
                                                                <?php if (! empty($phone['source'])) : ?>
                                                                    <span class="averia-contact-card__details-hint"><?php echo esc_html($phone['source']); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (! empty($email['href']) && ! empty($email['display'])) : ?>
                                                        <a href="<?php echo esc_url($email['href']); ?>">
                                                            <?php echo Svg::icon('email', 'averia-contact-card__action-icon'); ?>
                                                            <span class="averia-contact-card__details-text">
                                                                <span><?php echo esc_html($email['display']); ?></span>
                                                                <?php if (! empty($email['source'])) : ?>
                                                                    <span class="averia-contact-card__details-hint"><?php echo esc_html($email['source']); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($has_details) : ?>
                                                <dl class="averia-contact-card__details-list">
                                                    <?php foreach ($details as $detail) : ?>
                                                        <div class="averia-contact-card__details-item">
                                                            <dt><?php echo esc_html($detail['label'] ?? ''); ?></dt>
                                                            <dd><?php echo esc_html($detail['value'] ?? ''); ?></dd>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </dl>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>

                <section class="averia-summary-card averia-summary-card--insights">
                    <h2><?php esc_html_e('Indicadores clave', 'garantias-online-360vo'); ?></h2>
                    <dl class="averia-insights">
                        <?php foreach ($insight_metrics as $metric_label => $metric_value) :
                            $metric_value = trim((string) $metric_value);
                            if ($metric_value === '') {
                                continue;
                            }
                            ?>
                            <div class="averia-insights__item">
                                <dt><?php echo esc_html($metric_label); ?></dt>
                                <dd><?php echo esc_html($metric_value); ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </section>
            </aside>
        </div>

        <div class="averia-modal" data-averia-modal hidden aria-hidden="true">
            <div class="averia-modal__overlay" data-averia-modal-dismiss></div>
            <div class="averia-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="averia-modal-title" aria-describedby="averia-modal-body">
                <button
                    type="button"
                    class="averia-modal__close"
                    data-averia-modal-close
                    data-averia-modal-focus
                    aria-label="<?php esc_attr_e('Cerrar detalles del contacto', 'garantias-online-360vo'); ?>"
                >
                    <?php echo Svg::icon('close', 'averia-modal__close-icon'); ?>
                </button>
                <div class="averia-modal__header">
                    <span class="averia-modal__tag" data-averia-modal-tag hidden></span>
                    <h2 class="averia-modal__title" id="averia-modal-title" data-averia-modal-title></h2>
                    <p class="averia-modal__subtitle" data-averia-modal-subtitle hidden></p>
                </div>
                <div class="averia-modal__body" id="averia-modal-body" data-averia-modal-content></div>
            </div>
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
