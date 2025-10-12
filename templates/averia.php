<?php
if (! defined('ABSPATH')) {
    exit;
}

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

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php esc_html_e('Avería de garantía — Gestión 360VO', 'garantias-online-360vo'); ?></title>
    <meta name="description" content="<?php esc_attr_e('Gestión de averías de garantías de vehículos. Interfaz clara, moderna y eficiente para usuarios profesionales.', 'garantias-online-360vo'); ?>" />
    <style>
        :root {
            color-scheme: dark;
            --bg: #0f1216;
            --panel: #161b23;
            --panel-alt: #1b212c;
            --panel-soft: #1f2632;
            --border: #273042;
            --border-soft: rgba(255, 255, 255, 0.05);
            --text: #e9eff8;
            --text-muted: #9fb0c6;
            --accent: #4ea2ff;
            --accent-strong: #71b8ff;
            --success: #33c27f;
            --warning: #f5b642;
            --danger: #f06262;
            --surface-gradient: linear-gradient(140deg, rgba(79, 157, 255, 0.08), rgba(36, 210, 190, 0.05));
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
            --shadow-sm: 0 12px 28px rgba(0, 0, 0, 0.22);
            --space-xs: 8px;
            --space-sm: 12px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --space-xxl: 48px;
            --tap: 48px;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--bg);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
        }

        .breakdown-app {
            width: min(1260px, 100%);
            margin: 0 auto;
            padding: var(--space-xl) var(--space-lg) var(--space-xxl);
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }

        .breakdown-app__header {
            background: var(--panel);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: var(--space-lg);
            box-shadow: var(--shadow);
            display: grid;
            gap: var(--space-md);
        }

        .breakdown-app__meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
            align-items: center;
            justify-content: space-between;
        }

        .breakdown-app__title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .breakdown-app__badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--panel-alt);
            font-weight: 600;
            color: var(--text-muted);
        }

        .breakdown-app__badge::before {
            content: '';
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent);
        }

        .breakdown-app__badge--warning::before { background: var(--warning); }
        .breakdown-app__badge--success::before { background: var(--success); }
        .breakdown-app__badge--danger::before { background: var(--danger); }

        .breakdown-app__actions {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-sm);
        }

        .go-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: var(--tap);
            padding: 0 20px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            font-weight: 600;
            background: var(--panel-alt);
            color: var(--text);
            cursor: pointer;
            transition: background 0.12s ease, transform 0.1s ease;
        }

        .go-btn:hover { background: var(--panel-soft); }
        .go-btn:active { transform: translateY(1px); }

        .go-btn--primary { background: var(--accent); color: #041326; }
        .go-btn--outline { background: transparent; border-color: var(--border); color: var(--text-muted); }
        .go-btn--success { background: var(--success); color: #05140c; }
        .go-btn--danger { background: var(--danger); color: #140808; }

        .breakdown-tabs {
            display: flex;
            gap: var(--space-sm);
            overflow-x: auto;
            padding: 4px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 999px;
            border: 1px solid var(--border-soft);
        }

        .breakdown-tabs__item {
            flex: 0 0 auto;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.12s ease;
        }

        .breakdown-tabs__item[aria-selected="true"] {
            background: rgba(78, 162, 255, 0.15);
            color: var(--text);
            border-color: rgba(78, 162, 255, 0.35);
        }

        .breakdown-layout {
            display: grid;
            gap: var(--space-lg);
            grid-template-columns: 280px minmax(0, 1fr) 280px;
        }

        .breakdown-layout__main {
            display: grid;
            gap: var(--space-lg);
        }

        .breakdown-panel {
            background: var(--panel);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
            display: grid;
            gap: var(--space-md);
        }

        .breakdown-tab {
            display: none;
            gap: var(--space-md);
        }

        .breakdown-tab[aria-hidden="false"] {
            display: grid;
        }

        .breakdown-panel__title {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.2px;
        }

        .breakdown-stats {
            display: grid;
            gap: var(--space-sm);
        }

        .breakdown-stats__item {
            background: var(--panel-alt);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
            padding: var(--space-sm) var(--space-md);
        }

        .breakdown-stats__label {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .breakdown-stats__value {
            font-size: 16px;
            font-weight: 600;
        }

        .breakdown-history {
            display: grid;
            gap: var(--space-md);
        }

        .breakdown-history__event {
            border-left: 3px solid rgba(78, 162, 255, 0.4);
            padding: var(--space-sm) var(--space-md);
            background: var(--panel-alt);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
        }

        .breakdown-history__meta {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .breakdown-history__description {
            margin: 0;
            font-size: 15px;
            line-height: 1.55;
        }

        .breakdown-docs {
            display: grid;
            gap: var(--space-sm);
        }

        .breakdown-docs__item {
            display: grid;
            gap: 4px;
            padding: var(--space-sm) var(--space-md);
            background: var(--panel-alt);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
        }

        .breakdown-notes {
            display: grid;
            gap: var(--space-sm);
        }

        .breakdown-note {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-soft);
            background: rgba(255, 255, 255, 0.02);
        }

        .breakdown-note strong {
            display: block;
            margin-bottom: 4px;
        }

        .breakdown-empty {
            margin: auto;
            max-width: 520px;
            text-align: center;
            display: grid;
            gap: var(--space-md);
        }

        .breakdown-empty__title {
            font-size: 24px;
            margin: 0;
        }

        .breakdown-empty__message {
            font-size: 16px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        @media (max-width: 1100px) {
            .breakdown-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .breakdown-layout__main {
                order: 2;
            }
        }

        @media (max-width: 720px) {
            .breakdown-app {
                padding: var(--space-lg) var(--space-md) var(--space-xl);
            }

            .breakdown-app__title {
                font-size: 24px;
            }

            .go-btn {
                width: 100%;
            }

            .breakdown-app__actions {
                width: 100%;
            }

            .breakdown-tabs {
                border-radius: 12px;
            }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }
    </style>
</head>
<body>
<?php if ($view_mode !== 'case') : ?>
    <div class="breakdown-app">
        <section class="breakdown-panel breakdown-empty" role="status">
            <h1 class="breakdown-empty__title">
                <?php echo esc_html($license_plate ?: __('Expediente no disponible', 'garantias-online-360vo')); ?>
            </h1>
            <p class="breakdown-empty__message">
                <?php echo esc_html($empty_messages[$view_mode] ?? ''); ?>
            </p>
            <div class="breakdown-app__actions" aria-label="Acciones alternativas">
                <button type="button" class="go-btn go-btn--outline"><?php esc_html_e('Volver al listado de averías', 'garantias-online-360vo'); ?></button>
                <button type="button" class="go-btn go-btn--primary"><?php esc_html_e('Abrir nuevo expediente', 'garantias-online-360vo'); ?></button>
            </div>
        </section>
    </div>
<?php else : ?>
    <div class="breakdown-app" data-license-plate="<?php echo esc_attr($license_plate); ?>">
        <header class="breakdown-app__header">
            <div class="breakdown-app__meta">
                <div>
                    <div class="breakdown-app__title">
                        <?php echo esc_html($license_plate ?: '1234 ABC'); ?>
                    </div>
                    <div class="breakdown-app__subtitle" aria-live="polite">
                        <?php echo esc_html($sample_case['summary']); ?>
                    </div>
                </div>
                <span class="breakdown-app__badge breakdown-app__badge--<?php echo esc_attr($sample_case['status_variant']); ?>">
                    <?php echo esc_html($sample_case['status']); ?>
                </span>
            </div>
            <div class="breakdown-app__actions" aria-label="Acciones principales">
                <button type="button" class="go-btn go-btn--outline"><?php esc_html_e('Volver a averías', 'garantias-online-360vo'); ?></button>
                <button type="button" class="go-btn go-btn--primary"><?php esc_html_e('Guardar cambios', 'garantias-online-360vo'); ?></button>
                <button type="button" class="go-btn go-btn--success"><?php esc_html_e('Enviar actualización', 'garantias-online-360vo'); ?></button>
                <button type="button" class="go-btn go-btn--danger"><?php esc_html_e('Cerrar expediente', 'garantias-online-360vo'); ?></button>
            </div>
            <nav class="breakdown-tabs" role="tablist" aria-label="Secciones del expediente">
                <button type="button" class="breakdown-tabs__item" role="tab" aria-selected="true" aria-controls="tab-historial" id="tab-historial-trigger"><?php esc_html_e('Historial', 'garantias-online-360vo'); ?></button>
                <button type="button" class="breakdown-tabs__item" role="tab" aria-selected="false" aria-controls="tab-resumen" id="tab-resumen-trigger"><?php esc_html_e('Resumen', 'garantias-online-360vo'); ?></button>
                <button type="button" class="breakdown-tabs__item" role="tab" aria-selected="false" aria-controls="tab-taller" id="tab-taller-trigger"><?php esc_html_e('Taller', 'garantias-online-360vo'); ?></button>
                <button type="button" class="breakdown-tabs__item" role="tab" aria-selected="false" aria-controls="tab-importes" id="tab-importes-trigger"><?php esc_html_e('Importes', 'garantias-online-360vo'); ?></button>
                <button type="button" class="breakdown-tabs__item" role="tab" aria-selected="false" aria-controls="tab-documentos" id="tab-documentos-trigger"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></button>
            </nav>
        </header>

        <div class="breakdown-layout">
            <aside class="breakdown-panel breakdown-layout__sidebar" aria-labelledby="summary-heading">
                <h2 class="breakdown-panel__title" id="summary-heading"><?php esc_html_e('Datos principales', 'garantias-online-360vo'); ?></h2>
                <div class="breakdown-stats">
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Expediente', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['reference']); ?></span>
                    </div>
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Apertura', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['opened']); ?></span>
                    </div>
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['type']); ?></span>
                    </div>
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Cobertura', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['cover_element']); ?></span>
                    </div>
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Km contratación', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['kilometers_start']); ?></span>
                    </div>
                    <div class="breakdown-stats__item">
                        <span class="breakdown-stats__label"><?php esc_html_e('Km entrada taller', 'garantias-online-360vo'); ?></span>
                        <span class="breakdown-stats__value"><?php echo esc_html($sample_case['kilometers_now']); ?></span>
                    </div>
                </div>
                <div class="breakdown-note">
                    <strong><?php esc_html_e('Cliente y póliza', 'garantias-online-360vo'); ?></strong>
                    <p><?php echo esc_html($sample_case['owner']); ?> · <?php echo esc_html($sample_case['policy']); ?></p>
                    <p><?php echo esc_html($sample_case['vendor_manager']); ?> — <?php echo esc_html($sample_case['vendor']); ?></p>
                </div>
                <div class="breakdown-note">
                    <strong><?php esc_html_e('Peritaje requerido', 'garantias-online-360vo'); ?></strong>
                    <p><?php echo $sample_case['peritaje'] ? esc_html__('Sí, pendiente de informe final.', 'garantias-online-360vo') : esc_html__('No requerido actualmente.', 'garantias-online-360vo'); ?></p>
                </div>
            </aside>

            <section class="breakdown-layout__main" aria-live="polite">
                <div class="breakdown-panel breakdown-tab" id="tab-historial" role="tabpanel" aria-labelledby="tab-historial-trigger" aria-hidden="false">
                    <h2 class="breakdown-panel__title"><?php esc_html_e('Historial de comunicación', 'garantias-online-360vo'); ?></h2>
                    <div class="breakdown-history">
                        <?php foreach ($sample_history as $event) : ?>
                            <article class="breakdown-history__event">
                                <div class="breakdown-history__meta">
                                    <span><?php echo esc_html($event['date']); ?></span>
                                    <span>· <?php echo esc_html($event['type']); ?></span>
                                    <span>· <?php echo esc_html($event['actor']); ?></span>
                                </div>
                                <p class="breakdown-history__description"><?php echo esc_html($event['description']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Añadir nueva entrada', 'garantias-online-360vo'); ?></strong>
                        <p><?php esc_html_e('Formulario pendiente de conectar. Aquí se introducirán comunicaciones, llamadas y acuerdos con el taller o cliente.', 'garantias-online-360vo'); ?></p>
                    </div>
                </div>

                <div class="breakdown-panel breakdown-tab" id="tab-resumen" role="tabpanel" aria-labelledby="tab-resumen-trigger" aria-hidden="true">
                    <h2 class="breakdown-panel__title"><?php esc_html_e('Detalle de la garantía', 'garantias-online-360vo'); ?></h2>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Descripción de la avería', 'garantias-online-360vo'); ?></strong>
                        <p><?php echo esc_html($sample_case['summary']); ?></p>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Equipo comercial', 'garantias-online-360vo'); ?></strong>
                        <p><?php echo esc_html($sample_case['vendor_manager']); ?> — <?php echo esc_html($sample_case['vendor']); ?></p>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Cobertura y póliza', 'garantias-online-360vo'); ?></strong>
                        <p><?php echo esc_html($sample_case['policy']); ?> · <?php echo esc_html($sample_case['cover_element']); ?></p>
                    </div>
                </div>

                <div class="breakdown-panel breakdown-tab" id="tab-taller" role="tabpanel" aria-labelledby="tab-taller-trigger" aria-hidden="true">
                    <h2 class="breakdown-panel__title"><?php esc_html_e('Taller asignado', 'garantias-online-360vo'); ?></h2>
                    <div class="breakdown-stats">
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Taller', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['name']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Contacto', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['contact']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['phone']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Correo', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['email']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['address']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Tipo de taller', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['workshop']['type']); ?></span>
                        </div>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Datos fiscales', 'garantias-online-360vo'); ?></strong>
                        <p><?php echo esc_html($sample_case['workshop']['fiscal_name']); ?> · <?php echo esc_html($sample_case['workshop']['tax_id']); ?></p>
                    </div>
                </div>

                <div class="breakdown-panel breakdown-tab" id="tab-importes" role="tabpanel" aria-labelledby="tab-importes-trigger" aria-hidden="true">
                    <h2 class="breakdown-panel__title"><?php esc_html_e('Importes y resolución', 'garantias-online-360vo'); ?></h2>
                    <div class="breakdown-stats">
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Presupuesto recibido', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['financials']['budget']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Importe autorizado', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['financials']['authorized']); ?></span>
                        </div>
                        <div class="breakdown-stats__item">
                            <span class="breakdown-stats__label"><?php esc_html_e('Resolución', 'garantias-online-360vo'); ?></span>
                            <span class="breakdown-stats__value"><?php echo esc_html($sample_case['financials']['resolution']); ?></span>
                        </div>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Próximos pasos', 'garantias-online-360vo'); ?></strong>
                        <p><?php esc_html_e('Definir condiciones de autorización, generar resolución formal y comunicar a las partes implicadas.', 'garantias-online-360vo'); ?></p>
                    </div>
                </div>

                <div class="breakdown-panel breakdown-tab" id="tab-documentos" role="tabpanel" aria-labelledby="tab-documentos-trigger" aria-hidden="true">
                    <h2 class="breakdown-panel__title"><?php esc_html_e('Documentación vinculada', 'garantias-online-360vo'); ?></h2>
                    <div class="breakdown-docs">
                        <?php foreach ($sample_documents as $doc) : ?>
                            <article class="breakdown-docs__item">
                                <strong><?php echo esc_html($doc['label']); ?></strong>
                                <span><?php echo esc_html($doc['type']); ?> · <?php echo esc_html($doc['size']); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="breakdown-note">
                        <strong><?php esc_html_e('Carga de archivos', 'garantias-online-360vo'); ?></strong>
                        <p><?php esc_html_e('Se habilitará un componente de subida múltiple con categorías y notas individuales.', 'garantias-online-360vo'); ?></p>
                    </div>
                </div>
            </section>

            <aside class="breakdown-panel breakdown-layout__sidebar" role="complementary" aria-labelledby="notes-heading">
                <h2 class="breakdown-panel__title" id="notes-heading"><?php esc_html_e('Notas internas', 'garantias-online-360vo'); ?></h2>
                <div class="breakdown-notes">
                    <?php foreach ($sample_notes as $note) : ?>
                        <div class="breakdown-note">
                            <strong><?php echo esc_html($note['title']); ?></strong>
                            <p><?php echo esc_html($note['body']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="breakdown-app__actions">
                    <button type="button" class="go-btn go-btn--primary"><?php esc_html_e('Añadir nota', 'garantias-online-360vo'); ?></button>
                    <button type="button" class="go-btn go-btn--outline"><?php esc_html_e('Compartir resumen', 'garantias-online-360vo'); ?></button>
                </div>
            </aside>
        </div>
    </div>
    <script>
        (function () {
            const tabTriggers = document.querySelectorAll('.breakdown-tabs__item');
            const panels = document.querySelectorAll('.breakdown-tab');

            if (!tabTriggers.length || !panels.length) {
                return;
            }

            function activateTab(targetId) {
                tabTriggers.forEach((trigger) => {
                    const isActive = trigger.getAttribute('aria-controls') === targetId;
                    trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach((panel) => {
                    const isTarget = panel.id === targetId;
                    panel.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    panel.setAttribute('tabindex', isTarget ? '0' : '-1');
                    if (isTarget) {
                        panel.focus({ preventScroll: true });
                    }
                });
            }

            tabTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    const targetId = trigger.getAttribute('aria-controls');
                    activateTab(targetId);
                });
            });

            // Inicializa mostrando el historial como eje principal.
            activateTab('tab-historial');
        })();
    </script>
<?php endif; ?>
</body>
</html>
