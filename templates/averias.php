<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\GuaranteeCPT;
use GarantiasOnline360VO\Support\UserProfileResolver;
use GarantiasOnline360VO\Svg;

$is_breakdowns_page = true;
$is_auth_page       = false;
$is_dashboard_page  = false;

\GarantiasOnline360VO\TemplateLoader::load_part(
    'header',
    compact('is_breakdowns_page', 'is_auth_page', 'is_dashboard_page')
);
?>

<?php
$averias_sort_presets = [
    [
        'key'        => 'apertura_desc',
        'label'      => __('Fecha de apertura más reciente', 'garantias-online-360vo'),
        'order_by'   => 'apertura',
        'order'      => 'desc',
        'is_default' => true,
    ],
    [
        'key'      => 'apertura_asc',
        'label'    => __('Fecha de apertura más antigua', 'garantias-online-360vo'),
        'order_by' => 'apertura',
        'order'    => 'asc',
    ],
    [
        'key'      => 'importe_desc',
        'label'    => __('Mayor importe autorizado', 'garantias-online-360vo'),
        'order_by' => 'importe_autorizado',
        'order'    => 'desc',
    ],
    [
        'key'      => 'importe_asc',
        'label'    => __('Menor importe autorizado', 'garantias-online-360vo'),
        'order_by' => 'importe_autorizado',
        'order'    => 'asc',
    ],
];

$averias_default_sort = current(array_filter($averias_sort_presets, static fn($preset) => ! empty($preset['is_default'])));
$averias_default_sort_key = is_array($averias_default_sort) && ! empty($averias_default_sort['key'])
    ? $averias_default_sort['key']
    : 'apertura_desc';
$averias_default_sort_label = is_array($averias_default_sort) && ! empty($averias_default_sort['label'])
    ? $averias_default_sort['label']
    : __('Fecha de apertura más reciente', 'garantias-online-360vo');

$averias_entries     = [];
$averias_load_error  = '';
$current_user        = wp_get_current_user();
$current_user_id     = is_object($current_user) ? (int) $current_user->ID : 0;
$current_user_roles  = is_object($current_user) ? (array) $current_user->roles : [];
$can_view_all_breakdowns = current_user_can('manage_options')
    || in_array('go_garantias', $current_user_roles, true)
    || in_array('go_comercial', $current_user_roles, true)
    || in_array('go_director_comercial', $current_user_roles, true);

$averia_state_class_map = [
    'notificacion_averia' => 'notificacion',
    'abierta'             => 'abierta',
    'pendiente_taller'    => 'pendiente-taller',
    'espera_info'         => 'espera',
    'cerrada'             => 'cerrada',
];

$averia_state_label_map = [
    'notificacion_averia' => __('Notificación de avería', 'garantias-online-360vo'),
    'abierta'             => __('Abierta', 'garantias-online-360vo'),
    'pendiente_taller'    => __('Pendiente de taller', 'garantias-online-360vo'),
    'espera_info'         => __('En espera de información', 'garantias-online-360vo'),
    'cerrada'             => __('Cerrada', 'garantias-online-360vo'),
];

$averia_type_label_map = [
    'motor'                     => __('Motor', 'garantias-online-360vo'),
    'caja_cambios'              => __('Caja de cambios y transmisión', 'garantias-online-360vo'),
    'sistema_electrico'         => __('Sistema eléctrico y electrónico', 'garantias-online-360vo'),
    'sistema_refrigeracion'     => __('Sistema de refrigeración', 'garantias-online-360vo'),
    'sistema_combustible'       => __('Sistema de combustible', 'garantias-online-360vo'),
    'sistema_escape'            => __('Sistema de escape', 'garantias-online-360vo'),
    'sistema_direccion'         => __('Sistema de dirección', 'garantias-online-360vo'),
    'sistema_suspension'        => __('Sistema de suspensión', 'garantias-online-360vo'),
    'sistema_frenado'           => __('Sistema de frenado', 'garantias-online-360vo'),
    'sistema_aire_acondicionado'=> __('Sistema de aire acondicionado', 'garantias-online-360vo'),
    'otro'                      => __('Otro', 'garantias-online-360vo'),
];

$can_use_acf = function_exists('get_field');

if ($can_use_acf && post_type_exists(GuaranteeCPT::POST_TYPE)) {
    $averia_ids = get_posts([
        'post_type'      => GuaranteeCPT::POST_TYPE,
        'post_status'    => ['publish', 'pending', 'draft', 'private'],
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ]);

    foreach ($averia_ids as $averia_id) {
        $averia_id = (int) $averia_id;
        if ($averia_id <= 0) {
            continue;
        }

        $post_author = (int) get_post_field('post_author', $averia_id);
        $vendor_meta = get_post_meta($averia_id, 'garantia_contratada_concesionario_empresa_profesional', true);
        $vendor_id   = is_array($vendor_meta) && isset($vendor_meta['ID'])
            ? (int) $vendor_meta['ID']
            : (int) $vendor_meta;

        $can_view_entry = $can_view_all_breakdowns;

        if (! $can_view_entry) {
            if (($current_user_id > 0 && $current_user_id === $post_author)
                || ($current_user_id > 0 && $vendor_id > 0 && $current_user_id === $vendor_id)
            ) {
                $can_view_entry = true;
            } elseif ($vendor_id > 0) {
                $assigned_commercials = get_field('ajustes_usuarios_comercial_asignado', 'user_' . $vendor_id);
                if (is_array($assigned_commercials) && $current_user_id > 0) {
                    foreach ($assigned_commercials as $assigned) {
                        $assigned_id = is_array($assigned) && isset($assigned['ID'])
                            ? (int) $assigned['ID']
                            : (int) $assigned;
                        if ($assigned_id > 0 && $assigned_id === $current_user_id) {
                            $can_view_entry = true;
                            break;
                        }
                    }
                }
            }
        }

        if (! $can_view_entry) {
            continue;
        }

        $estado_group = get_field('estado_averia', $averia_id);
        if (! is_array($estado_group)) {
            $estado_group = [];
        }

        $estado_field = $estado_group['estado'] ?? '';
        $estado_value = '';
        $estado_label = '';

        if (is_array($estado_field)) {
            $estado_value = isset($estado_field['value']) ? (string) $estado_field['value'] : '';
            $estado_label = isset($estado_field['label']) ? (string) $estado_field['label'] : '';
        } elseif (is_string($estado_field)) {
            $estado_value = $estado_field;
        }

        $estado_value = sanitize_key($estado_value);

        if ($estado_value === '' || $estado_value === 'sin_estado') {
            continue;
        }

        if ($estado_label === '' && isset($averia_state_label_map[$estado_value])) {
            $estado_label = $averia_state_label_map[$estado_value];
        } elseif ($estado_label === '') {
            $estado_label = ucwords(str_replace(['_', '-'], ' ', $estado_value));
        }

        $estado_label = trim((string) $estado_label);
        $estado_class = $averia_state_class_map[$estado_value] ?? sanitize_html_class($estado_value ?: 'estado');

        $fecha_apertura      = $estado_group['fecha_apertura'] ?? '';
        $apertura_display    = '';
        $apertura_iso        = '';
        $apertura_timestamp  = 0;

        if (is_string($fecha_apertura) && $fecha_apertura !== '') {
            $fecha_apertura = trim($fecha_apertura);
            $fecha_obj = DateTimeImmutable::createFromFormat('d/m/Y', $fecha_apertura);
            if ($fecha_obj instanceof DateTimeImmutable) {
                $apertura_display   = $fecha_obj->format('d/m/Y');
                $apertura_iso       = $fecha_obj->format('Y-m-d');
                $apertura_timestamp = (int) $fecha_obj->format('U');
            } else {
                $apertura_display = $fecha_apertura;
                $apertura_iso     = $fecha_apertura;
            }
        }

        $tipo_field = $estado_group['tipo_averia'] ?? '';
        $tipo_value = '';
        $tipo_label = '';

        if (is_array($tipo_field)) {
            $tipo_value = isset($tipo_field['value']) ? (string) $tipo_field['value'] : '';
            $tipo_label = isset($tipo_field['label']) ? (string) $tipo_field['label'] : '';
        } elseif (is_string($tipo_field)) {
            $tipo_value = $tipo_field;
        }

        $tipo_value = sanitize_key($tipo_value);

        if ($tipo_value === 'otro') {
            $tipo_otro = get_field('tipo_averia_otro', $averia_id);
            if (! is_string($tipo_otro) || trim($tipo_otro) === '') {
                $tipo_otro = get_post_meta($averia_id, 'estado_averia_tipo_averia_otro', true);
            }
            if (is_string($tipo_otro)) {
                $tipo_otro = trim($tipo_otro);
                if ($tipo_otro !== '') {
                    $tipo_label = $tipo_otro;
                }
            }
        }

        if ($tipo_label === '' && isset($averia_type_label_map[$tipo_value])) {
            $tipo_label = $averia_type_label_map[$tipo_value];
        } elseif ($tipo_label === '' && $tipo_value !== '') {
            $tipo_label = ucwords(str_replace(['_', '-'], ' ', $tipo_value));
        }

        $tipo_label = trim((string) $tipo_label);

        $info_group = get_field('informacion_averia', $averia_id);
        if (! is_array($info_group)) {
            $info_group = [];
        }

        $importes_group = isset($info_group['importes_resolucion']) && is_array($info_group['importes_resolucion'])
            ? $info_group['importes_resolucion']
            : [];

        $presupuesto        = $importes_group['presupuesto_recibido'] ?? '';
        $importe_autorizado = $importes_group['importe_autorizado'] ?? '';

        $presupuesto_float        = is_numeric($presupuesto) ? (float) $presupuesto : null;
        $importe_autorizado_float = is_numeric($importe_autorizado) ? (float) $importe_autorizado : null;

        $presupuesto_formatted = $presupuesto_float !== null
            ? number_format_i18n($presupuesto_float, 2)
            : '';
        $importe_autorizado_formatted = $importe_autorizado_float !== null
            ? number_format_i18n($importe_autorizado_float, 2)
            : '';

        $requiere_peritaje = ! empty($info_group['requiere_peritaje']);

        $matricula = get_post_meta($averia_id, 'datos_vehiculo_matricula', true);
        $marca     = get_post_meta($averia_id, 'datos_vehiculo_marca', true);
        $modelo    = get_post_meta($averia_id, 'datos_vehiculo_modelo', true);

        $matricula = is_string($matricula) ? sanitize_text_field($matricula) : '';
        if ($matricula !== '') {
            $matricula = function_exists('mb_strtoupper')
                ? mb_strtoupper($matricula, 'UTF-8')
                : strtoupper($matricula);
        }
        $marca  = is_string($marca) ? sanitize_text_field($marca) : '';
        $modelo = is_string($modelo) ? sanitize_text_field($modelo) : '';

        $vehiculo_label = '';
        if ($marca !== '' && $modelo !== '') {
            $vehiculo_label = sprintf('%s · %s', $marca, $modelo);
        } elseif ($marca !== '') {
            $vehiculo_label = $marca;
        } elseif ($modelo !== '') {
            $vehiculo_label = $modelo;
        }

        $vendor_labels = [
            'company_name'       => '',
            'personal_full_name' => '',
            'company'            => ['type' => ['label' => '', 'value' => '']],
        ];

        if ($vendor_id > 0 && class_exists(UserProfileResolver::class)) {
            $vendor_labels = UserProfileResolver::get_vendor_labels($vendor_id);
        }

        $cliente_nombre = $vendor_labels['company_name'] ?? '';
        if ($cliente_nombre === '') {
            $cliente_nombre = $vendor_labels['personal_full_name'] ?? '';
        }
        $cliente_nombre = is_string($cliente_nombre) ? trim($cliente_nombre) : '';

        $cliente_tipo_label = '';
        $cliente_tipo_value = '';
        if (isset($vendor_labels['company']['type'])) {
            $cliente_tipo_value = sanitize_key((string) ($vendor_labels['company']['type']['value'] ?? ''));
            $cliente_tipo_label = isset($vendor_labels['company']['type']['label'])
                ? trim((string) $vendor_labels['company']['type']['label'])
                : '';
        }
        if ($cliente_tipo_label === '' && $cliente_tipo_value !== '') {
            $cliente_tipo_label = ucwords(str_replace(['_', '-'], ' ', $cliente_tipo_value));
        }

        $taller_group = get_field('taller', $averia_id);
        if (! is_array($taller_group)) {
            $taller_group = [];
        }

        $taller_encargado_field = $taller_group['taller_encargado'] ?? '';
        if (is_array($taller_encargado_field)) {
            $taller_encargado_field = isset($taller_encargado_field['value'])
                ? (string) $taller_encargado_field['value']
                : '';
        } elseif (! is_string($taller_encargado_field)) {
            $taller_encargado_field = '';
        }

        $taller_encargado = sanitize_key($taller_encargado_field);

        $taller_nombre       = '';
        $taller_responsable  = '';

        if ($taller_encargado === 'taller_asociado' && $vendor_id > 0) {
            $servicios_group = get_field('servicios', 'user_' . $vendor_id);
            if (! is_array($servicios_group)) {
                $servicios_group = [];
            }

            $perfil_taller = $servicios_group['taller'] ?? [];
            if (! is_array($perfil_taller)) {
                $perfil_taller = [];
            }

            $taller_nombre = isset($perfil_taller['nombre_taller'])
                ? trim((string) $perfil_taller['nombre_taller'])
                : '';
            if ($taller_nombre === '') {
                $taller_nombre = trim((string) ($vendor_labels['company_name'] ?? ''));
            }

            $taller_responsable = isset($perfil_taller['persona_contacto_taller'])
                ? trim((string) $perfil_taller['persona_contacto_taller'])
                : '';
            if ($taller_responsable === '') {
                $taller_responsable = trim((string) ($vendor_labels['personal_full_name'] ?? ''));
            }
        } else {
            $taller_nombre = isset($taller_group['nombre_taller'])
                ? trim((string) $taller_group['nombre_taller'])
                : '';
            if ($taller_nombre === '') {
                $taller_nombre = isset($taller_group['razon_social_taller'])
                    ? trim((string) $taller_group['razon_social_taller'])
                    : '';
            }

            $taller_responsable = isset($taller_group['responsable'])
                ? trim((string) $taller_group['responsable'])
                : '';
        }

        $averias_entries[] = [
            'id'                   => $averia_id,
            'estado_value'         => $estado_value,
            'estado_label'         => $estado_label !== '' ? $estado_label : ($averia_state_label_map[$estado_value] ?? ''),
            'estado_class'         => $estado_class,
            'apertura_display'     => $apertura_display,
            'apertura_iso'         => $apertura_iso,
            'apertura_timestamp'   => $apertura_timestamp,
            'tipo_value'           => $tipo_value,
            'tipo_label'           => $tipo_label !== '' ? $tipo_label : ($averia_type_label_map[$tipo_value] ?? ''),
            'presupuesto_display'  => $presupuesto_formatted,
            'presupuesto_raw'      => $presupuesto_float !== null ? $presupuesto_float : '',
            'importe_aut_display'  => $importe_autorizado_formatted,
            'importe_aut_raw'      => $importe_autorizado_float !== null ? $importe_autorizado_float : '',
            'requiere_peritaje'    => $requiere_peritaje ? true : false,
            'matricula'            => $matricula,
            'matricula_display'    => $matricula !== '' ? $matricula : '—',
            'vehiculo_label'       => $vehiculo_label !== '' ? $vehiculo_label : '—',
            'cliente_nombre'       => $cliente_nombre !== '' ? $cliente_nombre : '—',
            'cliente_tipo_label'   => $cliente_tipo_label !== '' ? $cliente_tipo_label : '—',
            'cliente_tipo_value'   => $cliente_tipo_value,
            'taller_nombre'        => $taller_nombre !== '' ? $taller_nombre : '—',
            'taller_responsable'   => $taller_responsable !== '' ? $taller_responsable : '—',
            'taller_encargado'     => $taller_encargado,
            'vendor_id'            => $vendor_id,
        ];
    }

    usort($averias_entries, static function (array $a, array $b): int {
        $by_apertura = ($b['apertura_timestamp'] ?? 0) <=> ($a['apertura_timestamp'] ?? 0);
        if ($by_apertura !== 0) {
            return $by_apertura;
        }

        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });
} else {
    $averias_load_error = __('No se ha podido cargar la información de averías en este momento.', 'garantias-online-360vo');
}
?>

<div class="guarantees-list__filters guarantees-list__filters--averias" style="view-transition-name: filtros-averias">
    <div class="guarantees-list__filters-row">
        <div class="guarantees-list__search-container">
            <span class="guarantees-list__search-icon" aria-hidden="true">
                <?php echo Svg::icon('search'); ?>
            </span>
            <input
                type="text"
                class="guarantees-list__search"
                placeholder="<?php esc_attr_e('Buscar vehículo o matrícula…', 'garantias-online-360vo'); ?>"
                aria-label="<?php esc_attr_e('Buscar vehículo o matrícula', 'garantias-online-360vo'); ?>"
                disabled>
            <span class="guarantees-list__close-icon" aria-hidden="true">
                <?php echo Svg::icon('cerrar'); ?>
            </span>
        </div>

        <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Estado de la avería', 'garantias-online-360vo'); ?>" disabled>
            <option value="">
                <?php esc_html_e('Todos los estados', 'garantias-online-360vo'); ?>
            </option>
            <option value="notificacion"><?php esc_html_e('Notificación de avería', 'garantias-online-360vo'); ?></option>
            <option value="abierta"><?php esc_html_e('Abierta', 'garantias-online-360vo'); ?></option>
            <option value="pendiente_taller"><?php esc_html_e('Pendiente de taller', 'garantias-online-360vo'); ?></option>
            <option value="espera_informacion"><?php esc_html_e('En espera de información', 'garantias-online-360vo'); ?></option>
            <option value="cerrada"><?php esc_html_e('Cerrada', 'garantias-online-360vo'); ?></option>
        </select>

        <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>" disabled>
            <option value="">
                <?php esc_html_e('Todos los tipos de avería', 'garantias-online-360vo'); ?>
            </option>
            <option value="motor"><?php esc_html_e('Motor', 'garantias-online-360vo'); ?></option>
            <option value="transmision"><?php esc_html_e('Transmisión', 'garantias-online-360vo'); ?></option>
            <option value="electrico"><?php esc_html_e('Sistema eléctrico', 'garantias-online-360vo'); ?></option>
            <option value="refrigeracion"><?php esc_html_e('Sistema de refrigeración', 'garantias-online-360vo'); ?></option>
            <option value="climatizacion"><?php esc_html_e('Climatización', 'garantias-online-360vo'); ?></option>
        </select>

        <div class="guarantees-list__filter-wrapper guarantees-list__filter-wrapper--clients">
            <select class="guarantees-list__filter" aria-label="<?php esc_attr_e('Clientes', 'garantias-online-360vo'); ?>" disabled>
                <option value="">
                    <?php esc_html_e('Todos los clientes', 'garantias-online-360vo'); ?>
                </option>
                <option value="motorsfera_valencia"><?php esc_html_e('MotorSfera Valencia', 'garantias-online-360vo'); ?></option>
                <option value="autopremium_madrid"><?php esc_html_e('AutoPremium Madrid', 'garantias-online-360vo'); ?></option>
                <option value="mobility_cars_bcn"><?php esc_html_e('Mobility Cars BCN', 'garantias-online-360vo'); ?></option>
                <option value="ecodrive_canarias"><?php esc_html_e('EcoDrive Canarias', 'garantias-online-360vo'); ?></option>
                <option value="redcar_sevilla"><?php esc_html_e('RedCar Sevilla', 'garantias-online-360vo'); ?></option>
            </select>
        </div>

        <div class="guarantees-list__filters-actions">
            <button
                type="button"
                class="guarantees-list__reset-btn"
                data-reset-filters
                hidden>
                <?php echo Svg::icon('filter_reset', 'guarantees-list__reset-icon'); ?>
                <span class="guarantees-list__reset-label">
                    <?php esc_html_e('Reiniciar filtros', 'garantias-online-360vo'); ?>
                </span>
            </button>
            <div class="guarantees-list__order" data-order-root>
                <button
                    type="button"
                    class="guarantees-list__order-btn"
                    data-order-toggle
                    data-default-sort="<?php echo esc_attr($averias_default_sort_key); ?>"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="averias-order-menu">
                    <?php echo Svg::icon('sort_lines', 'guarantees-list__order-icon'); ?>
                    <span class="guarantees-list__order-current" data-order-label>
                        <?php echo esc_html($averias_default_sort_label); ?>
                    </span>
                    <span class="guarantees-list__order-caret" aria-hidden="true">
                        <?php echo Svg::icon('arrow_drop_down'); ?>
                    </span>
                </button>
                <div
                    class="guarantees-list__order-menu"
                    id="averias-order-menu"
                    role="menu"
                    data-order-menu
                    hidden>
                    <?php foreach ($averias_sort_presets as $preset) : ?>
                        <button
                            type="button"
                            class="guarantees-list__order-option<?php echo ! empty($preset['is_default']) ? ' is-active' : ''; ?>"
                            role="menuitemradio"
                            aria-checked="<?php echo ! empty($preset['is_default']) ? 'true' : 'false'; ?>"
                            data-sort-key="<?php echo esc_attr($preset['key']); ?>"
                            data-order-by="<?php echo esc_attr($preset['order_by']); ?>"
                            data-order-direction="<?php echo esc_attr($preset['order']); ?>"
                            <?php echo ! empty($preset['is_default']) ? 'data-default="true"' : ''; ?>>
                            <?php echo esc_html($preset['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="guarantees-container guarantees-container--averias">
    <section class="guarantees-list guarantees-list--averias">
        <table class="guarantees-table guarantees-table--averias" style="view-transition-name: averias-table">
            <colgroup>
                <col class="guarantees-table__col guarantees-table__col--vehiculo" data-min-width="220" data-max-width="380" data-default-width="260">
                <col class="guarantees-table__col guarantees-table__col--estado" data-min-width="160" data-max-width="320" data-default-width="200">
                <col class="guarantees-table__col guarantees-table__col--fecha" data-min-width="160" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--tipo" data-min-width="180" data-max-width="340" data-default-width="210">
                <col class="guarantees-table__col guarantees-table__col--importe" data-min-width="150" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--importe" data-min-width="150" data-max-width="260" data-default-width="190">
                <col class="guarantees-table__col guarantees-table__col--cliente" data-min-width="210" data-max-width="360" data-default-width="240">
                <col class="guarantees-table__col guarantees-table__col--taller" data-min-width="210" data-max-width="360" data-default-width="240">
                <col class="guarantees-table__col guarantees-table__col--peritaje" data-min-width="140" data-max-width="200" data-default-width="150">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Vehículo', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Estado avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Apertura', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Tipo de avería', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Ppto. Recibido', 'garantias-online-360vo'); ?></th>
                    <th scope="col" class="guarantees-table__header--amount"><?php esc_html_e('Imp. Autorizado', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Cliente', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Taller', 'garantias-online-360vo'); ?></th>
                    <th scope="col"><?php esc_html_e('Peritaje', 'garantias-online-360vo'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($averias_load_error !== '') : ?>
                    <tr class="guarantees-table__empty-row">
                        <td colspan="9">
                            <div class="guarantees-table__empty-message">
                                <p><?php echo esc_html($averias_load_error); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php elseif (empty($averias_entries)) : ?>
                    <tr class="guarantees-table__empty-row">
                        <td colspan="9">
                            <div class="guarantees-table__empty-message">
                                <p><?php esc_html_e('Todavía no hay averías registradas.', 'garantias-online-360vo'); ?></p>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($averias_entries as $entry) : ?>
                        <tr
                            class="guarantees-table__row"
                            data-estado="<?php echo esc_attr($entry['estado_value']); ?>"
                            data-tipo="<?php echo esc_attr($entry['tipo_value']); ?>"
                            data-peritaje="<?php echo $entry['requiere_peritaje'] ? '1' : '0'; ?>"
                            data-apertura-ts="<?php echo esc_attr((string) ($entry['apertura_timestamp'] ?? 0)); ?>"
                            data-importe-autorizado="<?php echo esc_attr($entry['importe_aut_raw']); ?>"
                            data-presupuesto="<?php echo esc_attr($entry['presupuesto_raw']); ?>"
                            data-matricula="<?php echo esc_attr($entry['matricula']); ?>"
                            data-vendor-id="<?php echo esc_attr((string) $entry['vendor_id']); ?>"
                            data-cliente-tipo="<?php echo esc_attr($entry['cliente_tipo_value']); ?>"
                            data-taller-encargado="<?php echo esc_attr($entry['taller_encargado']); ?>"
                        >
                            <td data-label="<?php esc_attr_e('Vehículo', 'garantias-online-360vo'); ?>">
                                <div class="guarantees-table__vehiculo">
                                    <div class="vehiculo__mat"><?php echo esc_html($entry['matricula_display']); ?></div>
                                    <div class="vehiculo__marca_modelo"><?php echo esc_html($entry['vehiculo_label']); ?></div>
                                </div>
                            </td>
                            <td data-label="<?php esc_attr_e('Estado avería', 'garantias-online-360vo'); ?>">
                                <span class="averias-badge averias-badge--<?php echo esc_attr($entry['estado_class']); ?>">
                                    <?php echo esc_html($entry['estado_label']); ?>
                                </span>
                            </td>
                            <td data-label="<?php esc_attr_e('Apertura', 'garantias-online-360vo'); ?>">
                                <?php if (! empty($entry['apertura_display'])) : ?>
                                    <time datetime="<?php echo esc_attr($entry['apertura_iso'] !== '' ? $entry['apertura_iso'] : $entry['apertura_display']); ?>">
                                        <?php echo esc_html($entry['apertura_display']); ?>
                                    </time>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e('Tipo de avería', 'garantias-online-360vo'); ?>">
                                <?php echo esc_html($entry['tipo_label'] !== '' ? $entry['tipo_label'] : '—'); ?>
                            </td>
                            <td data-label="<?php esc_attr_e('Ppto. Recibido', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                                <?php if ($entry['presupuesto_display'] !== '') : ?>
                                    <span><?php echo esc_html($entry['presupuesto_display']); ?><span aria-hidden="true">&nbsp;€</span></span>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e('Imp. Autorizado', 'garantias-online-360vo'); ?>" class="guarantees-table__cell guarantees-table__cell--amount">
                                <?php if ($entry['importe_aut_display'] !== '') : ?>
                                    <span><?php echo esc_html($entry['importe_aut_display']); ?><span aria-hidden="true">&nbsp;€</span></span>
                                <?php else : ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e('Cliente', 'garantias-online-360vo'); ?>">
                                <div class="guarantees-table__vendedor">
                                    <div class="vendedor__name"><?php echo esc_html($entry['cliente_nombre']); ?></div>
                                    <div class="vendedor__type"><?php echo esc_html($entry['cliente_tipo_label']); ?></div>
                                </div>
                            </td>
                            <td data-label="<?php esc_attr_e('Taller', 'garantias-online-360vo'); ?>">
                                <div class="guarantees-table__vendedor">
                                    <div class="vendedor__name"><?php echo esc_html($entry['taller_nombre']); ?></div>
                                    <div class="vendedor__type"><?php echo esc_html($entry['taller_responsable']); ?></div>
                                </div>
                            </td>
                            <td data-label="<?php esc_attr_e('Peritaje', 'garantias-online-360vo'); ?>">
                                <?php $peritaje_id = 'averia-peritaje-' . $entry['id']; ?>
                                <label class="averias-checkbox" for="<?php echo esc_attr($peritaje_id); ?>">
                                    <input
                                        type="checkbox"
                                        id="<?php echo esc_attr($peritaje_id); ?>"
                                        disabled
                                        <?php checked(! empty($entry['requiere_peritaje'])); ?>
                                    >
                                    <span class="averias-checkbox__control" aria-hidden="true"></span>
                                    <span class="screen-reader-text">
                                        <?php echo ! empty($entry['requiere_peritaje'])
                                            ? esc_html__('Requiere peritaje', 'garantias-online-360vo')
                                            : esc_html__('No requiere peritaje', 'garantias-online-360vo'); ?>
                                    </span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<style>
    .guarantees-container--averias {
        grid-template-columns: 1fr;
    }

    .guarantees-container--averias .guarantees-list {
        grid-column: 1 / -1;
    }

    .guarantees-list--averias {
        background: var(--surface);
    }

    .guarantees-table--averias {
        width: 100%;
        table-layout: auto;
        min-width: 960px;
    }

    .guarantees-table__col {
        width: auto;
    }

    .guarantees-table__col--vehiculo {
        min-width: 220px;
    }

    .guarantees-table__col--estado,
    .guarantees-table__col--fecha,
    .guarantees-table__col--tipo,
    .guarantees-table__col--peritaje {
        min-width: 160px;
    }

    .guarantees-table__col--importe {
        min-width: 150px;
    }

    .guarantees-table__col--cliente,
    .guarantees-table__col--taller {
        min-width: 210px;
    }

    .guarantees-table__header--amount,
    .guarantees-table__cell--amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .averias-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: none;
        white-space: nowrap;
    }

    .averias-badge--abierta,
    .averias-badge--notificacion {
        background: rgba(227, 68, 68, 0.18);
        color: rgb(176, 35, 35);
    }

    .averias-badge--notificacion {
        font-variant-numeric: tabular-nums;
    }

    .averias-badge--pendiente-taller {
        background: rgba(255, 173, 66, 0.18);
        color: rgb(189, 96, 0);
    }

    .averias-badge--espera {
        background: rgba(238, 201, 55, 0.18);
        color: rgb(158, 128, 0);
    }

    .averias-badge--cerrada {
        background: rgba(52, 199, 89, 0.18);
        color: rgb(30, 130, 58);
    }

    .averias-checkbox {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: not-allowed;
    }

    .averias-checkbox input {
        position: absolute;
        inset: 0;
        opacity: 0;
        pointer-events: none;
    }

    .averias-checkbox__control {
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 0.35rem;
        border: 2px solid var(--border-strong);
        background: var(--surface);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s ease, border-color 0.2s ease;
    }

    .averias-checkbox input:checked + .averias-checkbox__control {
        background: var(--success-strong, var(--primary-color));
        border-color: var(--success-strong, var(--primary-color));
    }

    .averias-checkbox input:checked + .averias-checkbox__control::after {
        content: '';
        width: 0.45rem;
        height: 0.75rem;
        border: solid var(--text-inverse);
        border-width: 0 0.2rem 0.2rem 0;
        transform: rotate(45deg);
    }

    .averias-checkbox input:not(:checked) + .averias-checkbox__control::after {
        content: '';
        width: 0.6rem;
        height: 0.6rem;
        border-radius: 50%;
        background: var(--border-strong);
        opacity: 0.25;
    }

    .averias-checkbox input:checked + .averias-checkbox__control::after {
        opacity: 1;
    }

    .averias-checkbox .screen-reader-text {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    .guarantees-list__filters--averias {
        margin-bottom: 0;
    }

    .guarantees-list__filters--averias .guarantees-list__filters-row {
        flex-wrap: wrap;
        gap: var(--spacing-2);
    }

    @media (max-width: 1024px) {
        .guarantees-table--averias {
            min-width: 100%;
        }

        .guarantees-list__filters--averias .guarantees-list__filters-row {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

<?php
\GarantiasOnline360VO\TemplateLoader::load_part(
    'footer',
    compact('is_breakdowns_page', 'is_dashboard_page')
);
?>
