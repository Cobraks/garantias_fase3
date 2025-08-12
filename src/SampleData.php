<?php

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class SampleData
{
    const SUBMENU_SLUG = 'go-sample-data';
    const CAPABILITY  = 'manage_options';

    /** Hook setup */
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_submenu']);
        add_action('admin_post_go_generate_sample', [__CLASS__, 'handle_generate']);
        add_action('admin_post_go_delete_sample',   [__CLASS__, 'handle_delete']);
    }

    /** Añade opción en el menú de Garantías */
    public static function add_submenu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . GuaranteeCPT::POST_TYPE,
            __('Datos de prueba', 'garantias-online-360vo'),
            __('Datos de prueba', 'garantias-online-360vo'),
            self::CAPABILITY,
            self::SUBMENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /** Render admin page con dos botones */
    public static function render_page(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos.', 'garantias-online-360vo'));
        }
?>
        <div class="wrap">
            <h1><?php esc_html_e('Datos de prueba', 'garantias-online-360vo'); ?></h1>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="display:inline-block;margin-right:1rem;">
                <input type="hidden" name="action" value="go_generate_sample">
                <?php submit_button(__('Generar 20 garantías', 'garantias-online-360vo'), 'primary', '', false); ?>
            </form>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" style="display:inline-block;">
                <input type="hidden" name="action" value="go_delete_sample">
                <?php submit_button(__('Borrar todas las garantías', 'garantias-online-360vo'), 'secondary', '', false); ?>
            </form>
        </div>
<?php
    }

    /** Genera 20 posts de prueba */
    public static function handle_generate(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos.', 'garantias-online-360vo'));
        }

        // Cargar modalidades disponibles con sus datos
        $mods_raw = get_posts([
            'post_type'      => ModalidadesGarantiasCPT::POST_TYPE,
            'posts_per_page' => -1,
        ]);
        $mods = [];
        foreach ($mods_raw as $m) {
            $mid = $m->ID;
            $meses = get_field(
                'detalles_modalidad_condiciones_generales_y_tarifas_condiciones_modalidad_meses_disponibles',
                $mid
            );
            $tarifas = get_field('detalles_modalidad_condiciones_generales_y_tarifas_tarifas', $mid);
            if (empty($tarifas)) {
                continue;
            }
            $canales_raw = get_field(
                'detalles_modalidad_condiciones_generales_y_tarifas_condiciones_modalidad_canal_venta',
                $mid
            );
            $canales = [];
            if (is_array($canales_raw)) {
                foreach ($canales_raw as $c) {
                    $canales[] = is_array($c) ? ($c['value'] ?? '') : $c;
                }
            }
            // Solo permitimos profesional o particular para evitar campos condicionales complejos
            $canales = array_values(array_intersect($canales, ['profesional', 'particular']));
            if (empty($canales)) {
                $canales = ['profesional', 'particular'];
            }
            $mods[] = [
                'id'            => $mid,
                'tipo_garantia' => get_field('detalles_modalidad_tipo_garantia', $mid),
                'nivel'         => get_field('detalles_modalidad_nivel_garantia', $mid),
                'tipos_vehiculo'=> get_field('detalles_modalidad_tipo_vehiculo', $mid) ?: [],
                'meses'         => $meses ?: [12],
                'tarifas'       => $tarifas,
                'canales'       => $canales,
            ];
        }
        if (empty($mods)) {
            wp_safe_redirect(admin_url('edit.php?post_type=' . GuaranteeCPT::POST_TYPE . '&page=' . self::SUBMENU_SLUG));
            exit;
        }

        // Documentos de prueba
        $docs_path = plugin_dir_path(GARANTIAS360VO__FILE__) . 'assets/docs/';
        $doc_ids = [
            'certificado'      => self::upload_media($docs_path . 'contrato-ejemplo.pdf'),
            'factura_proforma' => self::upload_media($docs_path . 'factura-ejemplo.pdf'),
            'factura'          => self::upload_media($docs_path . 'factura-ejemplo.pdf'),
        ];

        // IDs de usuarios profesionales
        $pros = [18, 2];

        // Rango de fechas para matriculación random
        $min_matricula = new \DateTime('2019-01-01');
        $max_matricula = new \DateTime('now');

        // Lista de marca y modelo realistas
        $carros = [
            ['Ford', 'Fiesta'],
            ['Seat', 'León'],
            ['BMW', 'Serie 3'],
            ['Audi', 'A4'],
            ['Mercedes', 'Clase C'],
            ['Renault', 'Clio'],
            ['Volkswagen', 'Golf'],
            ['Toyota', 'Corolla'],
            ['Peugeot', '208'],
            ['Citroën', 'C3'],
        ];

        for ($i = 1; $i <= 20; $i++) {
            // Crear post de garantía
            $post_id = wp_insert_post([
                'post_type'   => GuaranteeCPT::POST_TYPE,
                'post_title'  => 'Garantía ' . wp_generate_password(4, false, false),
                'post_status' => 'publish',
            ]);
            if (! $post_id || is_wp_error($post_id)) {
                continue;
            }

            // Modalidad y tarifa aleatoria
            $mod    = $mods[array_rand($mods)];
            $tarifa = $mod['tarifas'][array_rand($mod['tarifas'])];
            $months = (int) ($tarifa['duracion_meses'] ?? 12);
            $base_price = (float) ($tarifa['precio'] ?? 0);
            $canal  = $mod['canales'][array_rand($mod['canales'])];

            // Fecha de inicio aleatoria últimos 6 meses
            $start = new \DateTime();
            $start->sub(new \DateInterval('P' . rand(0, 180) . 'D'));
            $end = clone $start;
            $end->add(new \DateInterval('P' . $months . 'M'));
            $end->sub(new \DateInterval('P1D'));

            // Calcular meses restantes
            $today           = new \DateTime('now');
            $meses_restantes = max(0, (int) floor($today->diff($end)->days / 30));

            // Fecha de primera matriculación random entre 2019 y hoy
            $randTs = rand($min_matricula->getTimestamp(), $max_matricula->getTimestamp());
            $matric = (new \DateTime())->setTimestamp($randTs);

            // Estado de la garantía
            update_post_meta($post_id, 'estado_garantia_estado_contratacion', (rand(0, 10) < 8 ? 'activada' : 'pendiente_pago'));
            update_post_meta($post_id, 'estado_garantia_inicio', $start->format('Y-m-d'));
            update_post_meta($post_id, 'estado_garantia_finalizacion', $end->format('Y-m-d'));
            update_post_meta($post_id, 'estado_garantia_meses_restantes', $meses_restantes);
            update_post_meta($post_id, 'estado_garantia_uuid', wp_generate_uuid4());

            // Garantía contratada
            update_post_meta($post_id, 'garantia_contratada_garantia', $mod['id']);
            if ($mod['tipo_garantia']) {
                update_post_meta($post_id, 'garantia_contratada_tipo_garantia', $mod['tipo_garantia']);
                wp_set_object_terms($post_id, $mod['tipo_garantia'], 'tipo_garantia');
            }
            if ($mod['nivel']) {
                update_post_meta($post_id, 'garantia_contratada_nivel_garantia', $mod['nivel']);
                wp_set_object_terms($post_id, $mod['nivel'], 'nivel_garantia');
            }
            update_post_meta($post_id, 'garantia_contratada_meses_contratados', $months);
            $rows = [];
            if (rand(0, 1)) {
                $rows[] = [
                    'tipo'       => 'descuento',
                    'porcentaje' => 10,
                    'razon'      => 'Promoción',
                ];
            }
            $final_price = $base_price;
            foreach ($rows as $r) {
                $pct = (float) ($r['porcentaje'] ?? 0);
                if ($r['tipo'] === 'descuento') {
                    $final_price -= ($base_price * $pct / 100);
                } else {
                    $final_price += ($base_price * $pct / 100);
                }
            }
            update_post_meta($post_id, 'garantia_contratada_precio', $final_price);
            $metodos = ['domiciliacion_bancaria', 'transferencia'];
            $metodo  = $metodos[array_rand($metodos)];
            update_post_meta($post_id, 'garantia_contratada_metodo_pago', $metodo);
            update_post_meta($post_id, 'garantia_contratada_canal_venta', $canal);
            if ($canal === 'profesional') {
                update_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', $pros[array_rand($pros)]);
            }
            update_post_meta($post_id, 'garantia_contratada_documentacion_certificado_garantia', $doc_ids['certificado']);
            update_post_meta($post_id, 'garantia_contratada_documentacion_factura_proforma', $doc_ids['factura_proforma']);
            update_post_meta($post_id, 'garantia_contratada_documentacion_factura', $doc_ids['factura']);

            update_post_meta($post_id, 'descuentos_y_recargos_precio_base', $base_price);
            update_post_meta($post_id, 'descuentos_y_recargos_listado_descuentos_recargos', $rows);

            // Datos del vehículo
            if (rand(0, 1)) {
                $mat = sprintf('%04d%s', rand(0, 9999), substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 3));
            } else {
                $mat = 'M' . rand(1000, 9999) . substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 2);
            }
            [$marca, $modelo] = $carros[array_rand($carros)];
            $veh_tipo = null;
            if (! empty($mod['tipos_vehiculo'])) {
                $veh_tipo = $mod['tipos_vehiculo'][array_rand($mod['tipos_vehiculo'])];
            } else {
                $all_types = get_terms(['taxonomy' => 'tipo_vehiculo', 'fields' => 'ids', 'hide_empty' => false]);
                if (! empty($all_types)) {
                    $veh_tipo = $all_types[array_rand($all_types)];
                }
            }
            update_post_meta($post_id, 'datos_vehiculo_matricula', strtoupper($mat));
            update_post_meta($post_id, 'datos_vehiculo_marca', $marca);
            update_post_meta($post_id, 'datos_vehiculo_modelo', $modelo);
            if ($veh_tipo) {
                update_post_meta($post_id, 'datos_vehiculo_tipo_vehiculo', $veh_tipo);
                wp_set_object_terms($post_id, $veh_tipo, 'tipo_vehiculo');
            }
            update_post_meta($post_id, 'datos_vehiculo_primera_matriculacion', $matric->format('Y-m-d'));
            update_post_meta($post_id, 'datos_vehiculo_kilometros', rand(10000, 150000));
            update_post_meta($post_id, 'datos_vehiculo_precio_venta', rand(5000, 30000));
            update_post_meta($post_id, 'datos_vehiculo_numero_bastidor', strtoupper(wp_generate_password(17, false, false)));
            $combustibles = ['diesel', 'gasolina', 'electrico', 'hibrido', 'gpl_gnc'];
            $combustible  = $combustibles[array_rand($combustibles)];
            update_post_meta($post_id, 'datos_vehiculo_combustible', $combustible);
            $cambios = ['manual', 'manual_pilotado', 'automatico'];
            $cambio  = $cambios[array_rand($cambios)];
            update_post_meta($post_id, 'datos_vehiculo_cambio', $cambio);
            update_post_meta($post_id, 'datos_vehiculo_potencia', rand(75, 300));
            if (in_array($combustible, ['electrico', 'hibrido', 'gpl_gnc'], true)) {
                update_post_meta($post_id, 'datos_vehiculo_potencia_kw', rand(50, 200));
                update_post_meta($post_id, 'datos_vehiculo_doble_motor', rand(0, 1));
            } else {
                update_post_meta($post_id, 'datos_vehiculo_cilindrada', rand(1000, 3000));
            }
            $term = $veh_tipo ? get_term($veh_tipo, 'tipo_vehiculo') : null;
            $tracciones = ['4x4', 'delantera', 'trasera'];
            if ($term && false !== stripos($term->slug ?? $term->name, 'camion')) {
                $traccion_camion = ['1_eje', '2_ejes', '3_ejes'];
                update_post_meta($post_id, 'datos_vehiculo_traccion_camion', $traccion_camion[array_rand($traccion_camion)]);
                $mmas = ['entre_35_y_60', 'entre_60_y_160', 'mas_de_160'];
                update_post_meta($post_id, 'datos_vehiculo_mma', $mmas[array_rand($mmas)]);
            } else {
                update_post_meta($post_id, 'datos_vehiculo_traccion', $tracciones[array_rand($tracciones)]);
            }

            // Datos del cliente
            update_post_meta($post_id, 'datos_cliente_nombre_y_apellidos', 'Cliente Ejemplo ' . $i);
            update_post_meta($post_id, 'datos_cliente_dni', rand(10000000, 99999999) . chr(rand(65, 90)));
            update_post_meta($post_id, 'datos_cliente_telefono', '6' . rand(60000000, 79999999));
            update_post_meta($post_id, 'datos_cliente_email', 'cliente' . $i . '@ejemplo.com');
            $dirs = ['C/ Mayor, 1', 'Av. Libertad, 23', 'P.º del Prado, 45'];
            update_post_meta($post_id, 'datos_cliente_direccion', $dirs[array_rand($dirs)]);
            $locs = ['Madrid', 'Barcelona', 'Valencia'];
            update_post_meta($post_id, 'datos_cliente_localidad', $locs[array_rand($locs)]);
            update_post_meta($post_id, 'datos_cliente_provincia', $locs[array_rand($locs)]);
            update_post_meta($post_id, 'datos_cliente_codigo_postal', rand(10000, 52999));

            // Historial y logs
            $log_time = current_time('d/m/Y g:i a');
            update_post_meta($post_id, 'historial_y_logs_logs', [
                [
                    'evento'       => 'creada',
                    'fecha_y_hora' => $log_time,
                    'detalles'     => 'Creación automática',
                ],
            ]);

            // Estado de la avería
            update_post_meta($post_id, 'estado_averia_estado', 'abierta');
            update_post_meta($post_id, 'estado_averia_fecha_apertura', $start->format('Y-m-d'));
            update_post_meta($post_id, 'estado_averia_tipo_averia', 'otro');

            // Información de la avería
            update_post_meta($post_id, 'informacion_averia_descripcion_averia', 'Sin incidencias');
            update_post_meta($post_id, 'informacion_averia_importes_resolucion_presupuesto_recibido', 0);
            update_post_meta($post_id, 'informacion_averia_importes_resolucion_importe_autorizado', 0);
            update_post_meta($post_id, 'informacion_averia_importes_resolucion_resolucion', '');

            // Taller
            update_post_meta($post_id, 'taller_taller_encargado', 'otro');
            update_post_meta($post_id, 'taller_responsable', 'Responsable ' . $i);
            update_post_meta($post_id, 'taller_telefono_taller', rand(900000000, 999999999));
            update_post_meta($post_id, 'taller_correo_taller', 'taller' . $i . '@ejemplo.com');
            update_post_meta($post_id, 'taller_direccion_taller', 'C/ Taller ' . $i);

            // Historiales, notas
            update_post_meta($post_id, 'historiales_notas_historial_comunicacion', 'Historial de comunicación de prueba');
            update_post_meta($post_id, 'historiales_notas_notas_internas', 'Notas internas de prueba');
            update_post_meta($post_id, 'historiales_notas_resumen', 'Resumen de prueba');
        }

        wp_safe_redirect(admin_url('edit.php?post_type=' . GuaranteeCPT::POST_TYPE . '&page=' . self::SUBMENU_SLUG . '&status=generated'));
        exit;
    }

    /** Borra todas las garantías */
    public static function handle_delete(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(__('No tienes permisos.', 'garantias-online-360vo'));
        }
        $all = get_posts([
            'post_type'   => GuaranteeCPT::POST_TYPE,
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);
        foreach ($all as $id) {
            wp_delete_post($id, true);
        }
        wp_safe_redirect(admin_url('edit.php?post_type=' . GuaranteeCPT::POST_TYPE . '&page=' . self::SUBMENU_SLUG . '&status=deleted'));
        exit;
    }

    /**
     * Crea una attachment en la biblioteca de medios a partir de un archivo local.
     */
    private static function upload_media(string $path): ?int
    {
        if (! file_exists($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }
        $filename = basename($path);
        $upload   = wp_upload_bits($filename, null, $contents);
        if (! empty($upload['error'])) {
            return null;
        }
        $filetype = wp_check_filetype($filename, null);
        $attachment = [
            'post_mime_type' => $filetype['type'] ?? 'application/octet-stream',
            'post_title'     => sanitize_file_name($filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (is_wp_error($attach_id)) {
            return null;
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
        return $attach_id;
    }
}

