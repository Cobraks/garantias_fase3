<?php
if (! defined('ABSPATH')) {
    exit;
}

$is_breakdowns_page = true;
$is_auth_page       = false;
$is_dashboard_page  = false;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_breakdowns_page', 'is_auth_page', 'is_dashboard_page')
);
?>

<header class="averias-page__header">
    <h1 class="averias-page__title"><?php esc_html_e('Averías', 'garantias-online-360vo'); ?></h1>
    <p class="averias-page__subtitle"><?php esc_html_e('Resumen de averías gestionadas recientemente.', 'garantias-online-360vo'); ?></p>
</header>

<div class="guarantees-container guarantees-container--averias">
    <section class="guarantees-list guarantees-list--averias">
        <table class="guarantees-table" style="view-transition-name: averias-table">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Vehículo', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Estado avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Fecha de apertura', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Presupuesto recibido', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Importe autorizado', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Taller responsable', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Peritaje V/F', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--actions"><?php esc_html_e('Acciones', 'garantias-online-360vo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr class="guarantees-table__row">
                    <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vehiculo">
                            <div class="vehiculo__mat">1234 LKF</div>
                            <div class="vehiculo__marca_modelo">SEAT León · 1.5 TSI</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__estado">
                            <span class="guarantees-list__badge guarantees-list__badge--sin-finalizar"><?php esc_html_e('Pendiente de diagnóstico', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Fecha de apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-02-12">12/02/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Motor', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Presupuesto recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        2.450,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Importe autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.800,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">AutoPremium Madrid</div>
                            <div class="vendedor__type">Profesional Gold</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller responsable', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">Talleres Ruiz</div>
                            <div class="vendedor__type">Responsable: Marta Ruiz</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje V/F', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__peritaje">
                            <span class="guarantees-list__badge guarantees-list__badge--activada"><?php esc_html_e('Verificado', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Acciones', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--actions">
                        <a href="#" class="guarantees-table__action-btn"><?php esc_html_e('Ver expediente', 'garantias-online-360vo'); ?></a>
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
                        <div class="guarantees-table__estado">
                            <span class="guarantees-list__badge guarantees-list__badge--pendiente-pago"><?php esc_html_e('En aprobación', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Fecha de apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-01-28">28/01/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Transmisión', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Presupuesto recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        3.280,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Importe autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        2.950,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">Mobility Cars BCN</div>
                            <div class="vendedor__type">Profesional Premium</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller responsable', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">ServicePoint Diésel</div>
                            <div class="vendedor__type">Responsable: Álvaro Peña</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje V/F', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__peritaje">
                            <span class="guarantees-list__badge guarantees-list__badge--validacion-pendiente"><?php esc_html_e('En validación', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Acciones', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--actions">
                        <a href="#" class="guarantees-table__action-btn"><?php esc_html_e('Ver expediente', 'garantias-online-360vo'); ?></a>
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
                        <div class="guarantees-table__estado">
                            <span class="guarantees-list__badge guarantees-list__badge--activada"><?php esc_html_e('Finalizada', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Fecha de apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2024-12-19">19/12/2024</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Sistema eléctrico', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Presupuesto recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.150,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Importe autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        1.150,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">EcoDrive Canarias</div>
                            <div class="vendedor__type">Profesional Estándar</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller responsable', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">ElectroAuto Las Palmas</div>
                            <div class="vendedor__type">Responsable: Noelia Martín</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje V/F', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__peritaje">
                            <span class="guarantees-list__badge guarantees-list__badge--activada"><?php esc_html_e('Verificado', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Acciones', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--actions">
                        <a href="#" class="guarantees-table__action-btn"><?php esc_html_e('Ver expediente', 'garantias-online-360vo'); ?></a>
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
                        <div class="guarantees-table__estado">
                            <span class="guarantees-list__badge guarantees-list__badge--expira-pronto"><?php esc_html_e('Pendiente de documentación', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Fecha de apertura', 'garantias-online-360vo'); ?>">
                        <time datetime="2025-02-03">03/02/2025</time>
                    </td>
                    <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Sistema de refrigeración', 'garantias-online-360vo'); ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Presupuesto recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        980,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Importe autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                        750,00&nbsp;€
                    </td>
                    <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">RedCar Sevilla</div>
                            <div class="vendedor__type">Profesional Silver</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Taller responsable', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__vendedor">
                            <div class="vendedor__name">CoolTech Garage</div>
                            <div class="vendedor__type">Responsable: Sergio Vidal</div>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Peritaje V/F', 'garantias-online-360vo'); ?>">
                        <div class="guarantees-table__peritaje">
                            <span class="guarantees-list__badge guarantees-list__badge--expirada"><?php esc_html_e('Faltan documentos', 'garantias-online-360vo'); ?></span>
                        </div>
                    </td>
                    <td data-label="<?php esc_attr_e('Acciones', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--actions">
                        <a href="#" class="guarantees-table__action-btn"><?php esc_html_e('Ver expediente', 'garantias-online-360vo'); ?></a>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</div>

<style>
    .averias-page__header {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-1);
        margin-bottom: var(--spacing-3);
    }

    .averias-page__title {
        font-size: clamp(1.75rem, 2.4vw, 2.25rem);
        margin: 0;
    }

    .averias-page__subtitle {
        margin: 0;
        color: var(--text-muted);
        font-size: 0.95rem;
    }

    .guarantees-container--averias {
        grid-template-columns: 1fr;
    }

    .guarantees-container--averias .guarantees-list {
        grid-column: 1 / -1;
    }

    .guarantees-list--averias {
        background: var(--surface);
    }

    .guarantees-table__header--amount,
    .guarantees-table__cell--amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .guarantees-table__header--actions,
    .guarantees-table__cell--actions {
        text-align: right;
        white-space: nowrap;
    }

    .guarantees-table__cell--actions {
        padding-right: clamp(1rem, 2vw, 1.5rem);
    }

    .guarantees-table__action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.55rem 1.25rem;
        border-radius: 999px;
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .guarantees-table__action-btn:hover,
    .guarantees-table__action-btn:focus-visible {
        background: var(--primary-color);
        color: var(--text-inverse);
    }

    .guarantees-table__peritaje {
        display: flex;
        align-items: center;
        justify-content: flex-start;
    }

    @media (max-width: 1024px) {
        .guarantees-table__cell--actions {
            text-align: left;
            padding-right: 0;
        }

        .guarantees-table__header--actions {
            text-align: left;
        }
    }
</style>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page')
);
?>
