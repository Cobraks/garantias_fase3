<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;





// -> REVISAR
// Solo muestra a admin/comercial, el resto se maneja por backend o autoselección en JS
$current_user = wp_get_current_user();
$is_admin = user_can($current_user, 'manage_options');
$is_comercial = in_array('go_comercial', $current_user->roles, true);
// <- REVISAR

error_log('[add-guarantee] template loaded for user ' . $current_user->ID);



// Obtener los términos de la taxonomía "tipo_vehiculo"
$tipos_vehiculo = get_terms([
    'taxonomy' => 'tipo_vehiculo',
    'hide_empty' => false,
]);


//Función para obtener las opciones de los selects del CPT Garantía
function get_acf_group_subfield_choices($group_field_name, $sub_field_name)
{
    if (!function_exists('acf_get_field_groups')) {
        return [];
    }
    $groups = acf_get_field_groups();
    foreach ($groups as $group) {
        $fields = acf_get_fields($group['key']);
        foreach ($fields as $field) {
            if ($field['type'] === 'group' && $field['name'] === $group_field_name) {
                foreach ($field['sub_fields'] as $sub_field) {
                    if ($sub_field['name'] === $sub_field_name) {
                        return $sub_field['choices'];
                    }
                }
            }
        }
    }
    return [];
}

$combustible_choices = get_acf_group_subfield_choices('datos_vehiculo', 'combustible');
$cambio_choices      = get_acf_group_subfield_choices('datos_vehiculo', 'cambio');
$traccion_choices    = get_acf_group_subfield_choices('datos_vehiculo', 'traccion');
$traccion_camion_choices = get_acf_group_subfield_choices('datos_vehiculo', 'traccion_camion');



$is_add_guarantee = true;
TemplateLoader::load_part('header', compact('is_add_guarantee')); ?>
<!-- FORMULARIO -->
<div class="form-container" style="view-transition-name: garantias-table">
    <div class="tabs">
        <div class="tabs__connector">
            <div class="connector connector-1"></div>
            <div class="connector connector-2"></div>
            <div class="connector connector-3"></div>
        </div>
        <div class="tabs__link active" data-tab="vehiculo">
            <div class="tabs__circle">1</div>
            <div class="tabs__title">Datos del vehículo</div>
        </div>
        <div class="tabs__link" data-tab="comprador-vendedor">
            <div class="tabs__circle">2</div>
            <div class="tabs__title">Datos del cliente</div>
        </div>
        <div class="tabs__link" data-tab="garantia">
            <div class="tabs__circle">3</div>
            <div class="tabs__title">Seleccionar garantía</div>
        </div>
        <div class="tabs__link" data-tab="finalizar">
            <div class="tabs__circle">4</div>
            <div class="tabs__title">Finalizar contratación</div>
        </div>
    </div>
    <!-- Contenido del Formulario -->
    <form id="form-garantia" class="form">
        <fieldset id="datos-vehiculo" class="form__tab-content form__tab-content--active">
            <legend style="display:none" class="form__legend">Datos del Vehículo</legend>
            <fieldset class="form__sub-fieldset">
                <legend class="form__sub-legend">Información general del vehículo</legend>
                <div class="form__wrapper-inputs">
                    <div class="form__input-container">
                        <select id="tipo_vehiculo" class="form__select" aria-label="Selecciona el tipo de vehículo" required>
                            <option value="" disabled selected>Selecciona tipo vehículo</option>
                            <?php foreach ($tipos_vehiculo as $tipo): ?>
                                <option value="<?php echo esc_attr($tipo->slug); ?>">
                                    <?php echo esc_html($tipo->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="tipo_vehiculo" class="form__placeholder form__placeholder--select">Tipo de vehículo</label>
                    </div>

                    <div class="form__input-container">
                        <input id="marca" class="form__input" type="text" placeholder=" " required />
                        <label for="marca" class="form__placeholder">Marca</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input"><?php echo Svg::icon('clear'); ?></span>
                    </div>
                    <div class="form__input-container">
                        <input id="modelo" class="form__input" type="text" placeholder=" " required />
                        <label for="modelo" class="form__placeholder">Modelo</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input"><?php echo Svg::icon('clear'); ?></span>
                    </div>
                    <div class="form__input-container form__input-container--corto form__input-container--suffix">
                        <input id="kilometros" class="form__input" type="text" placeholder=" " required />
                        <label for="kilometros" class="form__placeholder">Kilómetros</label>
                        <span class="form__suffix">
                            <span class="form__suffix-label">km</span>
                        </span>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="fecha_primera_matriculacion" class="form__input" type="date" placeholder=" " min="1980-01-01" required />
                        <label for="fecha_primera_matriculacion" class="form__placeholder">Fecha primera matriculación</label>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="matricula" class="form__input" type="text" placeholder=" " required />
                        <label for="matricula" class="form__placeholder">Matrícula</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                        <p class="form__supporting-text" style="display:none;">*Obligatorio</p>
                    </div>
                    <div class="form__input-container">
                        <input id="numero_bastidor" class="form__input" type="text" placeholder=" " required />
                        <label for="numero_bastidor" class="form__placeholder">Número de bastidor</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto form__input-container--suffix">
                        <input id="precio_venta" class="form__input" type="text" placeholder=" " required />
                        <label for="precio_venta" class="form__placeholder">Precio de venta</label>
                        <span class="form__suffix">€</span>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                </div>
            </fieldset>
            <fieldset class="form__sub-fieldset form__sub-fieldset--last">
                <legend class="form__sub-legend">Detalles técnicos</legend>
                <div class="form__wrapper-inputs">
                    <div class="form__input-container form__input-container--corto">
                        <select id="combustible" class="form__select" aria-label="Selecciona el tipo de combustible" required>
                            <option value="" disabled selected>Combustible</option>
                            <?php foreach ($combustible_choices as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="combustible" class="form__placeholder form__placeholder--select">Combustible</label>
                    </div>

                    <div class="form__input-container">
                        <select id="cambio" class="form__select" aria-label="Selecciona el tipo de cambio" required>
                            <option value="" disabled selected>Cambio</option>
                            <?php foreach ($cambio_choices as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="cambio" class="form__placeholder form__placeholder--select">Cambio</label>
                    </div>
                    <div class="form__input-container form__input-container--traccion">
                        <select id="traccion" class="form__select" aria-label="Tracción" required>
                            <option value="" disabled selected>Tracción</option>
                            <?php foreach ($traccion_choices as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="traccion" class="form__placeholder form__placeholder--select">Tracción</label>
                    </div>
                    <div class="form__input-container form__input-container--traccion-camion" style="display:none;">
                        <select id="traccion_camion" class="form__select" aria-label="Tracción camión" required>
                            <option value="" disabled selected>Tracción (ejes)</option>
                            <?php foreach ($traccion_camion_choices as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="traccion_camion" class="form__placeholder form__placeholder--select">Tracción (ejes)</label>
                    </div>
                    <div class="form__input-container form__input-container--corto form__input-container--suffix">
                        <input id="potencia" class="form__input" type="text" placeholder=" " required />
                        <label for="potencia" class="form__placeholder">Potencia (CV)</label>
                        <span class="form__suffix">CV</span>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto form__input-container--suffix">
                        <input id="cilindrada" class="form__input" type="text" placeholder=" " required />
                        <label for="cilindrada" class="form__placeholder">Cilindrada</label>
                        <span class="form__suffix">CC</span>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>


                    <div class="form__input-container form__input-container--doble_motor" style="display:none;">
                        <select id="doble_motor" class="form__select" aria-label="Doble motor" required>
                            <option value="" disabled selected>Doble Motor</option>
                            <option value="doble_motor_si">Tiene doble motor</option>
                            <option value="doble_motor_no">No tiene doble motor</option>
                        </select>
                        <label for="doble_motor" class="form__placeholder form__placeholder--select">Doble Motor</label>
                    </div>





                </div>
            </fieldset>
        </fieldset>
        <fieldset id="datos-comprador-vendedor" class="form__tab-content">
            <fieldset class="form__sub-fieldset">
                <!-- <fieldset class="form__nested-fieldset"> -->
                <legend class="form__nested-legend">Datos del cliente</legend>
                <div class="form__wrapper-inputs form__wrapper-inputs--large">
                    <div class="form__input-container">
                        <input id="nombre_apellidos" class="form__input" type="text" placeholder=" " required />
                        <label for="nombre_apellidos" class="form__placeholder">Nombre y apellidos</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="dni" class="form__input" type="text" placeholder=" " required />
                        <label for="dni" class="form__placeholder">DNI / NIE</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="telefono" class="form__input" type="text" placeholder=" " required />
                        <label for="telefono" class="form__placeholder">Teléfono</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container">
                        <input id="correo" class="form__input" type="text" placeholder=" " required />
                        <label for="correo" class="form__placeholder">Correo electrónico</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container ">
                        <input id="direccion" class="form__input" type="text" placeholder=" " required />
                        <label for="direccion" class="form__placeholder">Dirección</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="localidad" class="form__input" type="text" placeholder=" " required />
                        <label for="localidad" class="form__placeholder">Localidad</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                    <div class="form__input-container">
                        <select id="provincia" class="form__select" required>
                            <?php /* Hacer condicional por si es de otra ciudad, poner por defecto selected de esa ciudad*/ ?>
                            <option value="Álava">Álava</option>
                            <option value="Albacete">Albacete</option>
                            <option value="Alicante">Alicante</option>
                            <option value="Almería">Almería</option>
                            <option value="Asturias">Asturias</option>
                            <option value="Ávila">Ávila</option>
                            <option value="Badajoz">Badajoz</option>
                            <option value="Baleares">Baleares</option>
                            <option value="Barcelona">Barcelona</option>
                            <option value="Burgos">Burgos</option>
                            <option value="Cáceres">Cáceres</option>
                            <option value="Cádiz">Cádiz</option>
                            <option value="Cantabria">Cantabria</option>
                            <option value="Castellón">Castellón</option>
                            <option value="Ceuta">Ceuta</option>
                            <option value="Ciudad Real">Ciudad Real</option>
                            <option value="Córdoba">Córdoba</option>
                            <option value="Cuenca">Cuenca</option>
                            <option value="Girona">Girona</option>
                            <option value="Granada">Granada</option>
                            <option value="Guadalajara">Guadalajara</option>
                            <option value="Guipúzcoa">Guipúzcoa</option>
                            <option value="Huelva">Huelva</option>
                            <option value="Huesca">Huesca</option>
                            <option value="Jaén">Jaén</option>
                            <option value="La Rioja">La Rioja</option>
                            <option value="Las Palmas">Las Palmas</option>
                            <option value="León">León</option>
                            <option value="Lleida">Lleida</option>
                            <option value="Lugo">Lugo</option>
                            <option value="Madrid" selected>Madrid</option>
                            <option value="Málaga">Málaga</option>
                            <option value="Melilla">Melilla</option>
                            <option value="Murcia">Murcia</option>
                            <option value="Navarra">Navarra</option>
                            <option value="Ourense">Ourense</option>
                            <option value="Palencia">Palencia</option>
                            <option value="Pontevedra">Pontevedra</option>
                            <option value="Salamanca">Salamanca</option>
                            <option value="Santa Cruz de Tenerife">Santa Cruz de Tenerife</option>
                            <option value="Segovia">Segovia</option>
                            <option value="Sevilla">Sevilla</option>
                            <option value="Soria">Soria</option>
                            <option value="Tarragona">Tarragona</option>
                            <option value="Teruel">Teruel</option>
                            <option value="Toledo">Toledo</option>
                            <option value="Valencia">Valencia</option>
                            <option value="Valladolid">Valladolid</option>
                            <option value="Vizcaya">Vizcaya</option>
                            <option value="Zamora">Zamora</option>
                            <option value="Zaragoza">Zaragoza</option>
                        </select>
                        <label for="provincia" class="form__placeholder--select">Provincia*</label>
                    </div>
                    <div class="form__input-container form__input-container--corto">
                        <input id="codigo_postal" class="form__input" type="text" placeholder=" " required />
                        <label for="codigo_postal" class="form__placeholder">Código Postal</label>
                        <span class="form__clear-btn" role="button" aria-label="Clear input">
                            <?php echo Svg::icon('clear'); ?>
                        </span>
                    </div>
                </div>
                <!-- </fieldset> -->
            </fieldset>
        </fieldset>
        <fieldset id="seleccionar-garantia" class="form__tab-content">
            <legend style="display:none" class="form__legend">Seleccionar garantía</legend>
            <div class="form__wrapper-inputs">
                <div class="form__input-container form__input-container--corto">
                    <input id="fecha_inicio_garantia" class="form__input" type="date" placeholder=" " min="1980-01-01" required />
                    <label for="fecha_inicio_garantia" class="form__placeholder">Fecha inicio garantía</label>
                </div>
                <div class="form__input-container form__input-container--corto">
                    <select id="duracion" class="form__select" aria-label="Selecciona la duración de la garantía" required>
                        <!-- <option value="6_meses">6 meses</option>
                        <option value="12_meses" selected>12 meses</option>
                        <option value="24_meses">24 meses</option>
                        <option value="36_meses">36 meses</option> -->
                    </select>
                    <label for="duracion" class="form__placeholder form__placeholder--select">Duración</label>
                </div>

                <?php if ($is_admin): ?>
                    <div class="form__input-container form__input-container--corto">
                        <select id="canal-venta"
                            class="form__select"
                            name="canal_venta"
                            aria-label="Selecciona canal de venta"
                            required>
                            <option value="" disabled selected>Selecciona canal de venta</option>
                            <option value="go_profesional">Profesional</option>
                            <option value="go_gestoria">Gestoría</option>
                            <option value="go_particular">Particular</option>
                        </select>
                        <label for="canal-venta" class="form__placeholder form__placeholder--select">
                            Canal de venta
                        </label>
                    </div>
                    <div class="form__input-container form__input-container--corto" id="wrap-select-usuario" style="display:none;">
                        <select id="usuario-rol"
                            class="form__select"
                            name="usuario_rol"
                            aria-label="Selecciona vendedor">
                        </select>
                        <label for="usuario-rol" class="form__placeholder form__placeholder--select">
                            Vendedor
                        </label>
                    </div>
                <?php elseif ($is_comercial): ?>
                    <div class="form__input-container form__input-container--corto" id="wrap-select-usuario">
                        <select id="usuario-rol"
                            class="form__select"
                            name="usuario_rol"
                            aria-label="Selecciona profesional asignado">
                        </select>
                        <label for="usuario-rol" class="form__placeholder form__placeholder--select">
                            Profesional asignado
                        </label>
                    </div>
                <?php endif; ?>
                <div class="form__ofertas">
                    <!-- <ul class="ofertas__list">
                        <li class="ofertas__item">Nuevo cliente: -10%</li>
                        <li class="ofertas__item">Oferta Confort: -10%</li>
                    </ul> -->
                    <div class="ofertas__iva checkbox-wrapper-14">
                        <input id="check-iva" type="checkbox" class="switch" checked>
                        <label for="check-iva">Precios con IVA</label>
                    </div>
                    <?php if ($is_admin) : ?>
                    <div class="ofertas__desglose checkbox-wrapper-14">
                        <input id="check-desglose" type="checkbox" class="switch">
                        <label for="check-desglose">Desglose</label>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
            <div class="form__plans" id="formPlans"></div>

            <?php /*
            $test = get_field('ofertas_y_descuentos', 'user_2');
            echo '
            <pre>'; print_r($test); echo '</pre>'; */
            ?>
        </fieldset>
        <fieldset id="finalizar" class="form__tab-content">
            <!-- Contenido de la pestaña Finalizar -->
            <legend style="display:none" class="form__legend">Pago</legend>
            <div id="final-summary" class="form__contrato-prices"></div>

            <div class="form__wrapper-inputs form__wrapper-inputs--contratacion">

                <p class="mensaje_falta_sepa" style="display:none;">
                    <span class="mensaje_falta_sepa__icon"><?php echo Svg::icon('info'); ?></span>
                    <span class=" mensaje_falta_sepa__text">Para domiciliación bancaria, rellena y firma el SEPA en tu área de usuario</span>
                    <span onclick="this.parentElement.style.display='none'"
                        class="mensaje_falta_sepa__close"><?php  echo Svg::icon('close'); 
                                                            ?></span>
                </p>
                <div class="form__input-container">
                    <select id="metodo_pago" class="form__select" aria-label="Selecciona la duración de la garantía" required>
                        <option value="transferencia">Transferencia bancaria</option>
                        <!-- <option value="domiciliacion">Domiciliación bancaria</option> -->
                    </select>
                    <label for="metodo_pago" class="form__placeholder form__placeholder--select">Método de pago</label>
                </div>
                <div class="form__input-container form__input-container--acceptance">
                    <input id="aceptar_terminos" class="form__checkbox" type="checkbox" required />
                    <label for="aceptar_terminos" class="form__checkbox-label">He leído y acepto la <a href="">Política de Privacidad</a> y los <a href="">términos y condiciones</a></label>
                </div>
            </div>
        </fieldset>
    </form>
    <!-- Botones de Navegación -->
    <div class="nav-buttons">
        <button id="form_prev_btn" type="button" class="btn btn-secondary">Anterior</button>
        <button id="form_next_btn" type="button" class="btn btn-primary"><span class="btn__text">Siguiente</span></button>
    </div>
    <?php \GarantiasOnline360VO\TemplateLoader::load_part('form-success'); ?>
</div> <!-- /.form-container -->
<!-- SUMARIO -->
<aside class="summary-container" style="view-transition-name: resume-derecha">
    <div class="summary-section summary-section--header">
        <h3>Resumen del Contrato</h3>
        <p id="summary-canal-venta" class="summary-header__canal">
            <strong>Canal de venta: </strong>
            <span class="summary-header__canal-value" data-summary-canal></span>
        </p>
        <p id="summary-vendedor" class="summary-header__vendedor">
            <strong>Vendedor: </strong>
            <span class="summary-header__vendedor-value" data-summary-vendedor></span>
        </p>
    </div>

    <div class="summary-section summary-section--vehiculo">
        <h4>Vehículo</h4>
        <ul>
            <li class="summary__item">
                <span data-summary-field="tipo_vehiculo"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="marca"></span>
                <span data-summary-field="modelo"></span>
            </li>

            <li class="summary__item">
                <span data-summary-field="kilometros"></span>
                <span class="summary__item-sufix">km</span>
            </li>
            <li class="summary__item">
                <span data-summary-field="fecha_primera_matriculacion"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="matricula"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="numero_bastidor"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="precio_venta"></span>
                <span class="summary__item-sufix">€</span>
            </li>
            <li class="summary__item">
                <span data-summary-field="combustible"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="cambio"></span>
            </li>
            <li class="summary__item" id="summary-item-traccion" style="display:none;">
                <span>Tracción: <span data-summary-field="traccion"></span></span>
            </li>
            <li class="summary__item" id="summary-item-traccion-camion" style="display:none;">
                <span>Tracción camión: <span data-summary-field="traccion_camion"></span></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="potencia"></span>
                <span class="summary__item-sufix" id="summary-potencia-unit">CV</span>
            </li>
            <li class="summary__item">
                <span data-summary-field="cilindrada"></span>
                <span class="summary__item-sufix">CC</span>
            </li>
            <li class="summary__item" id="summary-item-doble_motor" style="display:none;">
                <span><span data-summary-field="doble_motor"></span></span>
            </li>

        </ul>
        <button class="summary-button">Editar datos del vehículo</button>
    </div>
    <div class="summary-section summary-section--cliente">
        <h4>Cliente</h4>
        <ul>
            <li class="summary__item">
                <span data-summary-field="nombre_apellidos"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="dni"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="telefono"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="correo"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="direccion"></span>
            </li>
        </ul>
        <button class="summary-button">Editar datos del cliente</button>
    </div>
    <div class="summary-section summary-section--garantia">
        <h4>Garantía</h4>
        <ul>
            <li class="summary__item">
                <span data-summary-field="modalidad"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="duracion"></span>
            </li>
            <li class="summary__item">
                <span data-summary-field="plan_seleccionado"></span>
            </li>
            <li class="summary__item">
                <span class="summary__item-prefix">Inicio garantía: </span>
                <span data-summary-field="fecha_inicio_garantia"></span>
            </li>
            <li class="summary__item">
                <span class="summary__item-prefix">Vencimiento del contrato: </span>
                <span data-summary-field="vencimiento_contrato"></span>
            </li>
        </ul>
        <button class="summary-button">Editar datos garantía</button>
    </div>
</aside>
<?php
// Cargar fragmento de footer
TemplateLoader::load_part('footer', compact('is_add_guarantee'));
