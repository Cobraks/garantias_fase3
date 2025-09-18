<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\Svg;
// Flags para CSS/JS condicional
$is_auth_page      = ! is_user_logged_in();
$is_dashboard_page = is_user_logged_in();

// Cargamos header
\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_auth_page', 'is_dashboard_page')
);

// Obtener el mes y año actual para mostrar en los módulos
$current_month = date('F');
$current_year = date('Y');
$spanish_months = [
    'January' => 'Enero',
    'February' => 'Febrero',
    'March' => 'Marzo',
    'April' => 'Abril',
    'May' => 'Mayo',
    'June' => 'Junio',
    'July' => 'Julio',
    'August' => 'Agosto',
    'September' => 'Septiembre',
    'October' => 'Octubre',
    'November' => 'Noviembre',
    'December' => 'Diciembre'
];
$current_month_spanish = $spanish_months[$current_month] ?? $current_month;
?>

<?php if (is_user_logged_in()) : ?>
    <div class="dashboard">


        <!-- Mensaje de contexto -->
        <div class="dashboard__context-message" style="view-transition-name: black-message">
            Mostrando datos de <strong>todas las garantías</strong> del mes de <strong><?php echo $current_month_spanish; ?></strong>
        </div>

        <!-- Sección Principal de Módulos -->
        <div class="dashboard__grid">
            <div class="dashboard__module dashboard__module--first">
                <!-- Perfil de Usuario -->
                <div class="dashboard__module dashboard__profile">
                    <div class="dashboard__profile-avatar">
                        <img src="<?php echo esc_url(get_avatar_url(get_current_user_id())); ?>"
                            alt="Avatar de usuario"
                            width="200"
                            height="200">
                    </div>
                    <div class="dashboard__profile-info">
                        <h2 class="dashboard__profile-name">Nombre del Administrador</h2>
                        <p class="dashboard__profile-role">Administrador</p>

                    </div>
                    <a href="#" class="dashboard__profile-settings">
                        <svg xmlns="http://www.w3.org/2000/svg" height="18" viewBox="0 -960 960 960" width="18">
                            <path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-119-50q-11 8-23 15t-24 12L590-80H370Zm112-260q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Z" />
                        </svg>
                        Ajustes de cuenta
                    </a>
                </div>
                <!-- Tipos de Garantía (ex Distribución de Estados) -->
                <div class="dashboard__module dashboard__warranty-types">
                    <div class="dashboard__module-header">
                        <h3 class="dashboard__module-title">Tipos de Garantía - <?php echo $current_month_spanish; ?></h3>
                        <div class="dashboard__warranty-total">Total: 12</div>
                    </div>
                    <div class="dashboard__warranty-grid">
                        <div class="dashboard__warranty-chart"></div>
                        <div class="dashboard__warranty-legend">
                            <div class="dashboard__warranty-item">
                                <div class="dashboard__warranty-color" style="background-color: var(--chart-color-1);"></div>
                                <div class="dashboard__warranty-info">
                                    <div class="dashboard__warranty-name">Essential</div>
                                    <div class="dashboard__warranty-value">7 (60%)</div>
                                </div>
                            </div>
                            <div class="dashboard__warranty-item">
                                <div class="dashboard__warranty-color" style="background-color: var(--chart-color-2);"></div>
                                <div class="dashboard__warranty-info">
                                    <div class="dashboard__warranty-name">Essential Plus</div>
                                    <div class="dashboard__warranty-value">3 (25%)</div>
                                </div>
                            </div>
                            <div class="dashboard__warranty-item">
                                <div class="dashboard__warranty-color" style="background-color: var(--chart-color-3);"></div>
                                <div class="dashboard__warranty-info">
                                    <div class="dashboard__warranty-name">Exclusive</div>
                                    <div class="dashboard__warranty-value">2 (15%)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Contenedor para KPIs de Garantías y Averías (apilados verticalmente) -->
            <div class="dashboard__kpi-stack">
                <!-- KPIs de Garantías (solo mes actual) -->
                <div class="dashboard__module dashboard__kpi" style="view-transition-name: garantias-table">
                    <div class="dashboard__kpi-header">
                        <h3 class="dashboard__module-title"> <?php echo Svg::icon('shield'); ?> Garantías - <?php echo $current_month_spanish; ?></h3>
                    </div>
                    <div class="dashboard__kpi-grid">
                        <div class="dashboard__kpi-item dashboard__kpi-item--new">
                            <div class="dashboard__kpi-value">12</div>
                            <div class="dashboard__kpi-label">Nuevas</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                        <div class="dashboard__kpi-item dashboard__kpi-item--active">
                            <div class="dashboard__kpi-value">8</div>
                            <div class="dashboard__kpi-label">Activas</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                        <div class="dashboard__kpi-item dashboard__kpi-item--pending">
                            <div class="dashboard__kpi-value">4</div>
                            <div class="dashboard__kpi-label">Pendientes</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                    </div>
                </div>

                <!-- KPIs de Averías (solo mes actual) -->
                <div class="dashboard__module dashboard__kpi dashboard__kpi--averias">
                    <div class="dashboard__kpi-header">
                        <h3 class="dashboard__module-title"><?php echo Svg::icon('warning', 'top-bar__icon'); ?> Averías - <?php echo $current_month_spanish; ?></h3>
                    </div>
                    <div class="dashboard__kpi-grid">
                        <div class="dashboard__kpi-item dashboard__kpi-item--open">
                            <div class="dashboard__kpi-value">5</div>
                            <div class="dashboard__kpi-label">Abiertas</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                        <div class="dashboard__kpi-item dashboard__kpi-item--waiting">
                            <div class="dashboard__kpi-value">2</div>
                            <div class="dashboard__kpi-label">Pendiente de taller</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                        <div class="dashboard__kpi-item dashboard__kpi-item--waiting">
                            <div class="dashboard__kpi-value">3</div>
                            <div class="dashboard__kpi-label">En espera de info</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                        <div class="dashboard__kpi-item dashboard__kpi-item--closed">
                            <div class="dashboard__kpi-value">17</div>
                            <div class="dashboard__kpi-label">Cerradas</div>
                            <div class="dashboard__kpi-sparkline"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas de Expiración (más prominente) -->
            <div class="dashboard__module dashboard__alerts" style="view-transition-name: white-message">
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title"><?php echo Svg::icon('shield_off'); ?>Alertas de Expiración</h3>
                    <span class="dashboard__alerts-count">12</span>
                </div>
                <div class="dashboard__alerts-content">
                    <p>3 garantías expiran en los próximos 30 días</p>
                    <ul class="dashboard__alerts-list">
                        <li class="dashboard__alerts-item">
                            <div class="dashboard__alerts-vehicle">ABC-1234 (Toyota Corolla)</div>
                            <div class="dashboard__alerts-date">15/07/2025</div>
                        </li>
                        <li class="dashboard__alerts-item">
                            <div class="dashboard__alerts-vehicle">XYZ-9876 (Seat León)</div>
                            <div class="dashboard__alerts-date">18/07/2025</div>
                        </li>
                        <li class="dashboard__alerts-item">
                            <div class="dashboard__alerts-vehicle">MNO-4567 (Ford Focus)</div>
                            <div class="dashboard__alerts-date">22/07/2025</div>
                        </li>
                    </ul>
                    <button class="dashboard__action-button">Ver todas las alertas</button>
                </div>
            </div>

            <!-- Gráfico de Tendencia -->
            <div class="dashboard__module dashboard__trend">
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title">Tendencia Mensual</h3>
                    <div class="dashboard__module-actions">
                        <button class="dashboard__action-button">12 meses</button>
                        <button class="dashboard__action-button">6 meses</button>
                        <button class="dashboard__action-button">3 meses</button>
                    </div>
                </div>
                <div class="dashboard__trend-chart">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <!-- Ganancias y Pérdidas (estilo mejorado) -->
            <div class="dashboard__module dashboard__finance">
                <div class="dashboard__finance-item dashboard__finance-item--gain">
                    <div class="dashboard__finance-header">
                        <h3 class="dashboard__module-title">Ganancias - <?php echo $current_month_spanish; ?></h3>
                        <span class="dashboard__finance-badge">+12% ↑</span>
                    </div>
                    <div class="dashboard__finance-value">24.850€</div>
                </div>
                <div class="dashboard__finance-item dashboard__finance-item--loss">
                    <div class="dashboard__finance-header">
                        <h3 class="dashboard__module-title">Pérdidas - <?php echo $current_month_spanish; ?></h3>
                        <span class="dashboard__finance-badge">-5% ↓</span>
                    </div>
                    <div class="dashboard__finance-value">3.420€</div>
                </div>
                <!-- Nuevo módulo: Resumen de Canales de Venta -->
                <div class="dashboard__module dashboard__channels">
                    <div class="dashboard__module-header">
                        <h3 class="dashboard__module-title">Canales de Venta</h3>
                        <button class="dashboard__action-button">Ver detalle</button>
                    </div>
                    <div class="dashboard__channels-grid">
                        <div class="dashboard__channel-item">
                            <div class="dashboard__channel-name">Concesionarios</div>
                            <div class="dashboard__channel-bar">
                                <div class="dashboard__channel-bar-fill" style="width: 65%;"></div>
                            </div>
                            <div class="dashboard__channel-value">65%</div>
                        </div>
                        <div class="dashboard__channel-item">
                            <div class="dashboard__channel-name">Talleres</div>
                            <div class="dashboard__channel-bar">
                                <div class="dashboard__channel-bar-fill" style="width: 25%;"></div>
                            </div>
                            <div class="dashboard__channel-value">25%</div>
                        </div>
                        <div class="dashboard__channel-item">
                            <div class="dashboard__channel-name">Online</div>
                            <div class="dashboard__channel-bar">
                                <div class="dashboard__channel-bar-fill" style="width: 8%;"></div>
                            </div>
                            <div class="dashboard__channel-value">8%</div>
                        </div>
                        <div class="dashboard__channel-item">
                            <div class="dashboard__channel-name">Otros</div>
                            <div class="dashboard__channel-bar">
                                <div class="dashboard__channel-bar-fill" style="width: 2%;"></div>
                            </div>
                            <div class="dashboard__channel-value">2%</div>
                        </div>
                    </div>
                </div>
            </div>






            <!-- Documentos Generales -->
            <div class="dashboard__module dashboard__documents">
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title">Documentos Generales</h3>
                </div>
                <div class="dashboard__doc-filters">
                    <select class="dashboard__doc-filter">
                        <option value="all">Todos los tipos</option>
                        <option value="terms">Condicionado</option>
                        <option value="coverage">Coberturas</option>
                    </select>
                    <select class="dashboard__doc-filter">
                        <option value="all">Todas las duraciones</option>
                        <option value="6">6 meses</option>
                        <option value="12">12 meses</option>
                        <option value="24">24 meses</option>
                        <option value="36">36 meses</option>
                    </select>
                </div>
                <ul class="dashboard__documents-list">
                    <li class="dashboard__documents-item">
                        <div class="dashboard__documents-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                                <path d="M240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z" />
                            </svg>
                        </div>
                        <div class="dashboard__documents-info">
                            <div class="dashboard__documents-name">Condicionado General - Essential</div>
                            <div class="dashboard__documents-details">6, 12, 24, 36 meses</div>
                        </div>
                    </li>
                    <li class="dashboard__documents-item">
                        <div class="dashboard__documents-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                                <path d="M240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z" />
                            </svg>
                        </div>
                        <div class="dashboard__documents-info">
                            <div class="dashboard__documents-name">Coberturas - Essential Plus</div>
                            <div class="dashboard__documents-details">12, 24, 36 meses</div>
                        </div>
                    </li>
                    <li class="dashboard__documents-item">
                        <div class="dashboard__documents-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24">
                                <path d="M240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z" />
                            </svg>
                        </div>
                        <div class="dashboard__documents-info">
                            <div class="dashboard__documents-name">Coberturas - Exclusive</div>
                            <div class="dashboard__documents-details">24, 36 meses</div>
                        </div>
                    </li>
                </ul>
                <a href="#" class="dashboard__documents-link">Ver todos los documentos</a>
            </div>

            <!-- Garantías por Concesionario (ex Profesional) -->
            <div class="dashboard__module dashboard__dealer-warranties">
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title">Garantías por Concesionario</h3>
                    <button class="dashboard__action-button">Exportar lista</button>
                </div>
                <div class="dashboard__dealer-table">
                    <div class="dashboard__dealer-row dashboard__dealer-header">
                        <div>Concesionario</div>
                        <div># Garantías</div>
                        <div>Pendientes</div>
                        <div>Vencen este mes</div>
                        <div></div>
                    </div>
                    <div class="dashboard__dealer-row">
                        <div class="dashboard__dealer-name">Auto Premium Madrid</div>
                        <div class="dashboard__dealer-count">142</div>
                        <div class="dashboard__dealer-pending">18</div>
                        <div class="dashboard__dealer-expiring">7</div>
                        <div class="dashboard__dealer-action">
                            <button class="dashboard__action-button">Ver detalle</button>
                        </div>
                    </div>
                    <div class="dashboard__dealer-row">
                        <div class="dashboard__dealer-name">Coches Veloces Barcelona</div>
                        <div class="dashboard__dealer-count">118</div>
                        <div class="dashboard__dealer-pending">12</div>
                        <div class="dashboard__dealer-expiring">5</div>
                        <div class="dashboard__dealer-action">
                            <button class="dashboard__action-button">Ver detalle</button>
                        </div>
                    </div>
                    <div class="dashboard__dealer-row">
                        <div class="dashboard__dealer-name">Motor Total Valencia</div>
                        <div class="dashboard__dealer-count">96</div>
                        <div class="dashboard__dealer-pending">8</div>
                        <div class="dashboard__dealer-expiring">3</div>
                        <div class="dashboard__dealer-action">
                            <button class="dashboard__action-button">Ver detalle</button>
                        </div>
                    </div>
                    <div class="dashboard__dealer-row">
                        <div class="dashboard__dealer-name">Autosur Sevilla</div>
                        <div class="dashboard__dealer-count">84</div>
                        <div class="dashboard__dealer-pending">6</div>
                        <div class="dashboard__dealer-expiring">2</div>
                        <div class="dashboard__dealer-action">
                            <button class="dashboard__action-button">Ver detalle</button>
                        </div>
                    </div>
                </div>
                <!-- Paginación -->
                <div class="dashboard__dealer-pagination">
                    <button class="dashboard__action-button">Anterior</button>
                    <span class="dashboard__pagination-current">Página 1 de 3</span>
                    <button class="dashboard__action-button">Siguiente</button>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="dashboard__module dashboard__quick-actions">
                <h3 class="dashboard__module-title">Acciones Rápidas</h3>
                <div class="dashboard__actions-grid">
                    <button class="dashboard__action-button dashboard__action-button--large">
                        Nueva Garantía
                    </button>
                    <button class="dashboard__action-button dashboard__action-button--large">
                        Registrar Avería
                    </button>
                    <button class="dashboard__action-button dashboard__action-button--large">
                        Gestionar Usuarios
                    </button>
                    <button class="dashboard__action-button dashboard__action-button--large">
                        Configuración
                    </button>
                </div>
            </div>

            <!-- Actividad Reciente -->
            <div class="dashboard__module dashboard__activity" data-activity-module>
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title"><?php esc_html_e('Actividad Reciente', 'garantias-online-360vo'); ?></h3>
                    <div class="activity__header-actions">
                        <button type="button" class="dashboard__action-button" data-activity-refresh>
                            <?php esc_html_e('Actualizar', 'garantias-online-360vo'); ?>
                        </button>
                    </div>
                </div>

                <form class="activity__filters" data-activity-filters novalidate>
                    <div class="activity__filters-row">
                        <label class="activity__field">
                            <span class="activity__label"><?php esc_html_e('Buscar', 'garantias-online-360vo'); ?></span>
                            <input type="search" id="activity-search" class="activity__input" placeholder="<?php esc_attr_e('Buscar por usuario, evento o texto…', 'garantias-online-360vo'); ?>" data-activity-search>
                        </label>
                        <label class="activity__field">
                            <span class="activity__label"><?php esc_html_e('Evento', 'garantias-online-360vo'); ?></span>
                            <select id="activity-event" class="activity__select" data-activity-event data-placeholder="<?php esc_attr_e('Todos los eventos', 'garantias-online-360vo'); ?>">
                                <option value=""><?php esc_html_e('Todos', 'garantias-online-360vo'); ?></option>
                            </select>
                        </label>
                    </div>
                    <div class="activity__filters-row activity__filters-row--pills">
                        <div class="activity__field activity__field--pills">
                            <span class="activity__label"><?php esc_html_e('Tipo de evento', 'garantias-online-360vo'); ?></span>
                            <div class="activity__pill-group" data-activity-category-pills></div>
                        </div>
                    </div>
                    <div class="activity__filters-row">
                        <label class="activity__field">
                            <span class="activity__label"><?php esc_html_e('Desde', 'garantias-online-360vo'); ?></span>
                            <input type="date" class="activity__input" data-activity-date-from>
                        </label>
                        <label class="activity__field">
                            <span class="activity__label"><?php esc_html_e('Hasta', 'garantias-online-360vo'); ?></span>
                            <input type="date" class="activity__input" data-activity-date-to>
                        </label>
                    </div>
                </form>

                <div class="activity__table-wrapper">
                    <div class="activity__loading" data-activity-loading hidden>
                        <span class="activity__spinner" aria-hidden="true"></span>
                        <span><?php esc_html_e('Cargando actividad…', 'garantias-online-360vo'); ?></span>
                    </div>
                    <div class="activity__empty" data-activity-empty hidden>
                        <p><?php esc_html_e('No hay eventos que coincidan con los filtros seleccionados.', 'garantias-online-360vo'); ?></p>
                    </div>
                    <table class="activity-table" data-activity-table>
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Fecha', 'garantias-online-360vo'); ?></th>
                                <th scope="col"><?php esc_html_e('Tipo de evento', 'garantias-online-360vo'); ?></th>
                                <th scope="col"><?php esc_html_e('Evento', 'garantias-online-360vo'); ?></th>
                                <th scope="col"><?php esc_html_e('Usuario', 'garantias-online-360vo'); ?></th>
                                <th scope="col"><?php esc_html_e('Acciones', 'garantias-online-360vo'); ?></th>
                            </tr>
                        </thead>
                        <tbody data-activity-body></tbody>
                    </table>
                </div>

                <div class="activity__footer">
                    <div class="activity__pagination" data-activity-pagination>
                        <button type="button" class="dashboard__action-button" data-activity-prev disabled>
                            <?php esc_html_e('Anterior', 'garantias-online-360vo'); ?>
                        </button>
                        <span class="activity__page-indicator" data-activity-pageinfo></span>
                        <button type="button" class="dashboard__action-button" data-activity-next disabled>
                            <?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?>
                        </button>
                    </div>
                    <div class="activity__per-page">
                        <label for="activity-per-page" class="activity__label"><?php esc_html_e('Resultados por página', 'garantias-online-360vo'); ?></label>
                        <select id="activity-per-page" class="activity__select" data-activity-per-page>
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Módulo Extra: Últimas Renovaciones -->
            <div class="dashboard__module dashboard__renewals">
                <div class="dashboard__module-header">
                    <h3 class="dashboard__module-title">Últimas Renovaciones</h3>
                </div>
                <ul class="dashboard__renewals-list">
                    <li class="dashboard__renewals-item">
                        <div class="dashboard__renewals-client">Cliente: Antonio García</div>
                        <div class="dashboard__renewals-details">Garantía #4589 - Renovada hoy</div>
                    </li>
                    <li class="dashboard__renewals-item">
                        <div class="dashboard__renewals-client">Cliente: Marta Sánchez</div>
                        <div class="dashboard__renewals-details">Garantía #3217 - Renovada ayer</div>
                    </li>
                    <li class="dashboard__renewals-item">
                        <div class="dashboard__renewals-client">Cliente: Roberto Jiménez</div>
                        <div class="dashboard__renewals-details">Garantía #1245 - Renovada hace 2 días</div>
                    </li>
                </ul>
            </div>
        </div>
    </div> <!-- /.dashboard -->

<?php else : ?>
    <?php
    // Si no está logueado, mostrar formulario de login
    \GarantiasOnline360VO\TemplateLoader::load('login');
    ?>
<?php endif; ?>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('trendChart');

        // Verificamos si el elemento existe
        if (!ctx) {
            console.error('No se encontró el elemento trendChart');
            return;
        }

        // Datos de ejemplo
        const trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Garantías activas',
                    data: [65, 59, 80, 81, 76, 55, 40],
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Nuevas garantías',
                    data: [28, 48, 40, 19, 46, 27, 20],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // ¡Importante!
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        console.log('Gráfico de tendencia inicializado correctamente');
    });
</script>
<?php

// Footer y scripts (incluye dashboard.min.js cuando $is_dashboard_page)
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_dashboard_page')
);
