<?php
if (! defined('ABSPATH')) {
    exit;
}

// “Mis garantías”
$is_list_page = true;
\GarantiasOnline360VO\TemplateLoader::load_part('header', compact('is_list_page'));

use GarantiasOnline360VO\Svg;
?>


<!-- 1. FILTROS (full-width bajo header) -->
<div class="guarantees-list__filters" style="view-transition-name: filtros">
    <div class="guarantees-list__search-container">
        <span class="guarantees-list__search-icon" aria-hidden="true">
            <?php echo Svg::icon('search'); ?>
        </span>
        <input
            type="text"
            class="guarantees-list__search"
            placeholder="<?php esc_attr_e('Buscar vehículo o matrícula…', 'garantias-online-360vo'); ?>"
            aria-label="<?php esc_attr_e('Buscar vehículo o matrícula', 'garantias-online-360vo'); ?>">
    </div>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Estado', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?></option>
        <option value="Activa"><?php esc_html_e('Activa', 'garantias-online-360vo'); ?></option>
        <option value="Pendiente"><?php esc_html_e('Pendiente de pago', 'garantias-online-360vo'); ?></option>
        <option value="Expirada"><?php esc_html_e('Expirada', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Plan', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Todos los planes', 'garantias-online-360vo'); ?></option>
        <option value="Essential"><?php esc_html_e('Essential', 'garantias-online-360vo'); ?></option>
        <option value="Essential Plus"><?php esc_html_e('Essential Plus', 'garantias-online-360vo'); ?></option>
        <option value="Exclusive"><?php esc_html_e('Exclusive', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></option>
        <option value="Particular"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
        <option value="Profesional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
        <option value="Gestoría"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
    </select>

    <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Concesionario', 'garantias-online-360vo'); ?>">
        <option value=""><?php esc_html_e('Concesionario', 'garantias-online-360vo'); ?></option>
        <option value="A"><?php esc_html_e('Concesionario A', 'garantias-online-360vo'); ?></option>
        <option value="B"><?php esc_html_e('Concesionario B', 'garantias-online-360vo'); ?></option>
        <option value="C"><?php esc_html_e('Concesionario C', 'garantias-online-360vo'); ?></option>
    </select>
</div>

<div class="guarantees-container">
    <!-- 2. LISTA: tabla semántica con columna “Garantía” al final y “Profesional” en vendedor -->
    <section class="guarantees-list">
        <table class="guarantees-table" style="view-transition-name: garantias-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Vehículo',  'garantias-online-360vo'); ?></th>
                    <th><?php esc_html_e('Validez',   'garantias-online-360vo'); ?></th>
                    <th><?php esc_html_e('Vendedor',  'garantias-online-360vo'); ?></th>
                    <th><?php esc_html_e('Garantía',  'garantias-online-360vo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Datos de ejemplo (12 filas)
                $examples = [
                    ['plan' => 'Essential',      'precio' => '200€', 'desde' => '01/01/25', 'hasta' => '31/12/25', 'marca' => 'Ford Fiesta',       'mat' => '1234BCD', 'est' => 'Activa',    'vend' => 'A'],
                    ['plan' => 'Essential Plus', 'precio' => '250€', 'desde' => '10/01/25', 'hasta' => '09/01/26', 'marca' => 'Seat León',         'mat' => 'M1234BC', 'est' => 'Activa',    'vend' => 'B'],
                    ['plan' => 'Exclusive',      'precio' => '320€', 'desde' => '05/03/25', 'hasta' => '04/03/26', 'marca' => 'BMW Serie 3',       'mat' => '4321-XYZ', 'est' => 'Pendiente', 'vend' => 'C'],
                    ['plan' => 'Essential',      'precio' => '200€', 'desde' => '12/02/25', 'hasta' => '11/02/26', 'marca' => 'Audi A4',           'mat' => 'AB-123-CD', 'est' => 'Expirada',  'vend' => 'A'],
                    ['plan' => 'Essential Plus', 'precio' => '260€', 'desde' => '20/02/25', 'hasta' => '19/02/26', 'marca' => 'VW Golf',           'mat' => '9876-ZYX', 'est' => 'Activa',    'vend' => 'B'],
                    ['plan' => 'Exclusive',      'precio' => '330€', 'desde' => '15/04/25', 'hasta' => '14/04/26', 'marca' => 'Mercedes C',        'mat' => 'W-4567-WQ', 'est' => 'Activa',    'vend' => 'C'],
                    ['plan' => 'Essential',      'precio' => '210€', 'desde' => '01/05/25', 'hasta' => '30/04/26', 'marca' => 'Renault Clio',      'mat' => 'CL-4321',  'est' => 'Pendiente', 'vend' => 'A'],
                    ['plan' => 'Essential Plus', 'precio' => '255€', 'desde' => '10/06/25', 'hasta' => '09/06/26', 'marca' => 'Peugeot 208',       'mat' => 'PE-7890',  'est' => 'Expirada',  'vend' => 'B'],
                    ['plan' => 'Exclusive',      'precio' => '340€', 'desde' => '20/07/25', 'hasta' => '19/07/26', 'marca' => 'Toyota Corolla',    'mat' => 'TO-5678',  'est' => 'Activa',    'vend' => 'C'],
                    ['plan' => 'Essential',      'precio' => '205€', 'desde' => '05/08/25', 'hasta' => '04/08/26', 'marca' => 'Opel Astra',        'mat' => 'OP-3456',  'est' => 'Activa',    'vend' => 'A'],
                    ['plan' => 'Essential Plus', 'precio' => '265€', 'desde' => '15/09/25', 'hasta' => '14/09/26', 'marca' => 'Hyundai i30',       'mat' => 'HY-2345',  'est' => 'Pendiente', 'vend' => 'B'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],
                    ['plan' => 'Exclusive',      'precio' => '350€', 'desde' => '25/10/25', 'hasta' => '24/10/26', 'marca' => 'Kia Ceed',          'mat' => 'KI-1234',  'est' => 'Expirada',  'vend' => 'C'],


                ];
                // Mapear código A/B/C a nombre
                $vendors = ['A' => 'Concesionario A', 'B' => 'Concesionario B', 'C' => 'Concesionario C'];
                foreach ($examples as $row) : ?>
                    <tr
                        class="guarantees-table__row"
                        tabindex="0"
                        data-matricula="<?php echo esc_attr($row['mat']); ?>">
                        <!-- Vehículo -->
                        <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                            <div class="guarantees-table__vehiculo">
                                <strong><?php echo esc_html($row['marca']); ?></strong>
                                <div class="vehiculo__mat"><?php echo esc_html($row['mat']); ?></div>
                            </div>
                        </td>

                        <!-- Validez -->
                        <td data-label="<?php esc_attr_e('Validez', 'garantias-online-360vo'); ?>">
                            <div class="guarantees-table__period">
                                <div>
                                    <strong><?php esc_html_e('Desde:', 'garantias-online-360vo'); ?></strong>
                                    <time><?php echo esc_html($row['desde']); ?></time>
                                </div>
                                <div>
                                    <strong><?php esc_html_e('Hasta:', 'garantias-online-360vo'); ?></strong>
                                    <time><?php echo esc_html($row['hasta']); ?></time>
                                </div>
                            </div>
                        </td>

                        <!-- Vendedor -->
                        <td data-label="<?php esc_attr_e('Vendedor', 'garantias-online-360vo'); ?>">
                            <div class="guarantees-table__vendedor">
                                <div class="vendedor__name">
                                    <?php echo esc_html($vendors[$row['vend']]); ?>
                                </div>
                                <div class="vendedor__type">
                                    <?php esc_html_e('Profesional', 'garantias-online-360vo'); ?>
                                </div>
                            </div>
                        </td>

                        <!-- Garantía -->
                        <td data-label="<?php esc_attr_e('Garantía', 'garantias-online-360vo'); ?>">
                            <div class="guarantees-table__plan">
                                <span class="plan__name"><?php echo esc_html($row['plan']); ?></span>
                                <span class="plan__price"><?php echo esc_html($row['precio']); ?></span>
                            </div>
                            <?php
                            $status_value = sanitize_title($row['est']);
                            $status_labels = [
                                'activa'            => __('Activa', 'garantias-online-360vo'),
                                'pendiente'         => __('Pendiente', 'garantias-online-360vo'),
                                'pendiente-de-pago' => __('Pendiente de pago', 'garantias-online-360vo'),
                                'expirada'          => __('Expirada', 'garantias-online-360vo'),
                            ];
                            $status_label = $status_labels[$status_value] ?? $row['est'];
                            ?>
                            <span class="guarantees-list__badge guarantees-list__badge--<?php echo esc_attr($status_value); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>


    <!-- 3. DETALLE: mostrar TODOS los campos del formulario -->
    <aside class="guarantee-detail" style="view-transition-name: resume-derecha">
        <div class="guarantee-detail__inner">
            <!-- <h2 class="guarantee-detail__title"><?php // esc_html_e('Detalle de garantía', 'garantias-online-360vo'); 
                                                        ?></h2> -->
            <div class="guarantee-detail__header">
                <h2>Garantía 1234BCD</h2>
                <h3 class="guarantee-detail__plan-title">Essential Plus 12 meses</h3>
                <div>

                    <p>25/01/25 — 24/01/26 <span class="guarantee-detail__plan-duration">(6 meses restantes)</span></p>

                </div>
                <div class="guarantee-detail__badge">ACTIVA</div>
            </div>
            <div class="guarantee-detail__btn-container">
                <button
                    type=" button"
                    class="guarantee-detail__btn guarantee-detail__btn--report"
                    aria-label="<?php esc_attr_e('Abrir expediente para esta garantía', 'garantias-online-360vo'); ?>">
                    <?php echo Svg::icon('warning', 'guarantee-detail__btn-icon'); ?>
                    <span class="guarantee-detail__btn-text">
                        <?php esc_html_e('Abrir expediente', 'garantias-online-360vo'); ?>
                    </span>
                </button>

                <button
                    type="button"
                    class="guarantee-detail__btn guarantee-detail__btn--fav"
                    aria-label="<?php esc_attr_e('Guardar en favoritos', 'garantias-online-360vo'); ?>">
                    <?php echo Svg::icon('heart', 'guarantee-detail__btn-icon'); ?>
                </button>
                <button
                    type="button"
                    class="guarantee-detail__btn guarantee-detail__btn--share"
                    aria-label="<?php esc_attr_e('Compartir', 'garantias-online-360vo'); ?>">
                    <?php echo Svg::icon('share', 'guarantee-detail__btn-icon'); ?>
                </button>
            </div>
            <section class="detail__section detail__section--fast-actions">
                <h3 class="detail__section-title">
                    <?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?>
                </h3>
                <p>Profesional, Escarpa Motor</p>
                <ul class="fast-actions">
                    <li class="fast-actions__item">
                        <a href="#" class="fast-actions__link">
                            <?php echo Svg::icon('phone', 'fast-actions__icon'); ?>
                            <span class="fast-actions__label">
                                <?php esc_html_e('Escarpa Motor', 'garantias-online-360vo'); ?>
                            </span>
                        </a>
                    </li>
                    <li class="fast-actions__item">
                        <a href="#" class="fast-actions__link">
                            <?php echo Svg::icon('email', 'fast-actions__icon'); ?>
                            <span class="fast-actions__label">
                                <?php esc_html_e('Escarpa Motor', 'garantias-online-360vo'); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </section>

            <!-- Sección: Vehículo -->
            <section class="detail__section">
                <h3><?php esc_html_e('Datos del vehículo', 'garantias-online-360vo'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Marca/Modelo:',    'garantias-online-360vo'); ?></strong> Seat León</li>
                    <li><strong><?php esc_html_e('Tipo:',            'garantias-online-360vo'); ?></strong> 4x4</li>
                    <li><strong><?php esc_html_e('Kilómetros:',      'garantias-online-360vo'); ?></strong> 42 000 km</li>
                    <li><strong><?php esc_html_e('1ª Matriculación:', 'garantias-online-360vo'); ?></strong> 2015-06-10</li>
                    <li><strong><?php esc_html_e('Matrícula:',        'garantias-online-360vo'); ?></strong> M-1234-BC</li>
                    <li><strong><?php esc_html_e('Nº Bastidor:',     'garantias-online-360vo'); ?></strong> VF1ABC123XYZ45678</li>
                    <li><strong><?php esc_html_e('Precio venta:',    'garantias-online-360vo'); ?></strong> 15 000 €</li>
                </ul>
            </section>

            <!-- Sección: Técnico -->
            <section class="detail__section">
                <h3><?php esc_html_e('Detalles técnicos', 'garantias-online-360vo'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Combustible:', 'garantias-online-360vo'); ?></strong> Diésel</li>
                    <li><strong><?php esc_html_e('Cambio:',      'garantias-online-360vo'); ?></strong> Automático</li>
                    <li><strong><?php esc_html_e('Potencia:',    'garantias-online-360vo'); ?></strong> 120 cv</li>
                    <li><strong><?php esc_html_e('Cilindrada:',  'garantias-online-360vo'); ?></strong> 1 600 cc</li>
                </ul>
            </section>

            <section class="detail__section detail__section--docs">
                <h3 class="detail__section-title">
                    <?php esc_html_e('Documentación', 'garantias-online-360vo'); ?>
                </h3>
                <ul class="detail__docs-list">
                    <?php
                    $docs = [
                        'contrato'     => ['label' => esc_html__('Contrato', 'garantias-online-360vo'),    'file' => 'assets/docs/contrato-ejemplo.pdf'],
                        'condicionado' => ['label' => esc_html__('Condicionado', 'garantias-online-360vo'), 'file' => 'assets/docs/condicionado-ejemplo.pdf'],
                        'cobertura' => ['label' => esc_html__('Cobertura', 'garantias-online-360vo'), 'file' => 'assets/docs/cobertura-ejemplo.pdf'],
                        'factura' => ['label' => esc_html__('Factura', 'garantias-online-360vo'), 'file' => 'assets/docs/factura-ejemplo.pdf'],

                    ];
                    foreach ($docs as $slug => $info) : ?>
                        <li class="detail__docs-item">
                            <button
                                type="button"
                                class="detail__docs-btn"
                                data-doc-url="<?php echo esc_url(plugins_url($info['file'], GARANTIAS360VO__FILE__)); ?>"
                                aria-label="<?php printf(esc_attr__('Ver documento %s', 'garantias-online-360vo'), $info['label']); ?>">
                                <?php echo Svg::icon('pdf', 'detail__docs-icon'); ?>
                                <span class="detail__docs-label"><?php echo $info['label']; ?></span>
                            </button>
                        </li>

                    <?php endforeach; ?>

                </ul>
            </section>




            <!-- Sección: Comprador -->
            <section class="detail__section">
                <h3><?php esc_html_e('Datos del comprador', 'garantias-online-360vo'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Nombre:',      'garantias-online-360vo'); ?></strong> Juan Pérez</li>
                    <li><strong><?php esc_html_e('DNI/NIE:',     'garantias-online-360vo'); ?></strong> 12345678Z</li>
                    <li><strong><?php esc_html_e('Teléfono:',    'garantias-online-360vo'); ?></strong> 600 123 456</li>
                    <li><strong><?php esc_html_e('Email:',       'garantias-online-360vo'); ?></strong> juan@ejemplo.com</li>
                    <li><strong><?php esc_html_e('Dirección:',   'garantias-online-360vo'); ?></strong> C/ Mayor, 1, Madrid</li>
                </ul>
                <ul class="fast-actions">
                    <li class="fast-actions__item">
                        <a href="#" class="fast-actions__link">
                            <?php echo Svg::icon('phone', 'fast-actions__icon'); ?>
                            <span class="fast-actions__label">
                                <?php esc_html_e('Cliente', 'garantias-online-360vo'); ?>
                            </span>
                        </a>
                    </li>
                    <li class="fast-actions__item">
                        <a href="#" class="fast-actions__link">
                            <?php echo Svg::icon('email', 'fast-actions__icon'); ?>
                            <span class="fast-actions__label">
                                <?php esc_html_e('Cliente', 'garantias-online-360vo'); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </section>

        </div>
    </aside>
</div>
<div class="pdf-modal" role="dialog" aria-modal="true" aria-labelledby="pdf-modal-title">
    <div class="pdf-modal__content">
        <button class="pdf-modal__close" aria-label="<?php esc_attr_e('Cerrar previsualización', 'garantias-online-360vo'); ?>">&times;</button>
        <div class="pdf-modal__header">
            <div class="pdf-modal__title-container">
                <h2 id="pdf-modal-title"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h2>
                <h3 class="pdf-modal-subttitle">Garantía 2345CDD</h3>
            </div>
            <ul class="pdf-modal__docs-list detail__docs-list">
                <!-- Se rellenará por JS clonando los enlaces de .detail__docs-link -->
            </ul>
        </div>

        <iframe class="pdf-modal__iframe" src="" title="<?php esc_attr_e('Vista previa de documento', 'garantias-online-360vo'); ?>"></iframe>
        <a class="pdf-modal__download" href="#" download>
            <?php esc_html_e('Descargar PDF', 'garantias-online-360vo'); ?>
        </a>
    </div>
</div>


<?php
// Cargar fragmento de footer

// Cargar fragmento de footer, y le pasamos is_list_page
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_list_page')
);
?>

<script>
    (function() {
        const list = document.querySelector('.guarantees-list');
        const detail = document.querySelector('.guarantee-detail');
        const onScroll = () => {
            if (list.scrollTop > 10 || detail.scrollTop > 10) {
                document.body.classList.add('scrolled');
            } else {
                document.body.classList.remove('scrolled');
            }
        };
        list.addEventListener('scroll', onScroll);
        detail.addEventListener('scroll', onScroll);
    })();
</script>