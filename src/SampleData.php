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

        // Definición de planes y precios
        $plans = [
            'Essential'      => 200,
            'Essential Plus' => 250,
            'Exclusive'      => 300,
        ];

        // Construir mapa título => ID de modalidad
        $plan_posts = get_posts([
            'post_type'      => ModalidadesGarantiasCPT::POST_TYPE,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $plan_map = [];
        foreach ($plan_posts as $pid) {
            $p = get_post($pid);
            if ($p && isset($plans[$p->post_title])) {
                $plan_map[$p->post_title] = $pid;
            }
        }

        // Taxonomías disponibles
        $tipo_garantia_ids = get_terms(['taxonomy' => 'tipo_garantia', 'fields' => 'ids', 'hide_empty' => false]);
        $nivel_garantia_ids = get_terms(['taxonomy' => 'nivel_garantia', 'fields' => 'ids', 'hide_empty' => false]);
        $tipo_vehiculo_ids  = get_terms(['taxonomy' => 'tipo_vehiculo',  'fields' => 'ids', 'hide_empty' => false]);

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

            // Seleccionar plan aleatorio
            $names = array_keys($plans);
            $plan_name = $names[array_rand($names)];
            $price     = $plans[$plan_name];
            $plan_id   = $plan_map[$plan_name] ?? null;
            if (! $plan_id) {
                continue;
            }

            // Fecha de inicio aleatoria últimos 6 meses
            $start = new \DateTime();
            $start->sub(new \DateInterval('P' . rand(0, 180) . 'D'));
            // Duración en meses 12/24/36
            $months = [12, 24, 36][array_rand([0, 1, 2])];
            $end    = clone $start;
            $end->add(new \DateInterval('P' . $months . 'M'));
            $end->sub(new \DateInterval('P1D'));

            // Calcular meses restantes
            $today           = new \DateTime('now');
            $meses_restantes = max(0, (int) floor($today->diff($end)->days / 30));

            // Fecha de primera matriculación random entre 2019 y hoy
            $randTs = rand($min_matricula->getTimestamp(), $max_matricula->getTimestamp());
            $matric = (new \DateTime())->setTimestamp($randTs);

            // Actualizar campos ACF (fechas en formato Y-m-d para ACF date picker)
            update_field('estado_garantia_estado_contratacion', (rand(0, 10) < 8 ? 'activada' : 'pendiente_pago'), $post_id);
            update_field('estado_garantia_inicio',      $start->format('Y-m-d'), $post_id);
            update_field('estado_garantia_finalizacion', $end->format('Y-m-d'),   $post_id);
            update_field('estado_garantia_meses_restantes', $meses_restantes, $post_id);
            update_field('estado_garantia_uuid', wp_generate_uuid4(), $post_id);

            update_field('garantia_contratada_garantia', $plan_id, $post_id);
            if (! empty($tipo_garantia_ids)) {
                update_field('garantia_contratada_tipo_garantia', $tipo_garantia_ids[array_rand($tipo_garantia_ids)], $post_id);
            }
            if (! empty($nivel_garantia_ids)) {
                update_field('garantia_contratada_nivel_garantia', $nivel_garantia_ids[array_rand($nivel_garantia_ids)], $post_id);
            }
            update_field('garantia_contratada_meses_contratados', $months, $post_id);
            update_field('garantia_contratada_precio', $price, $post_id);
            $metodos = ['transferencia', 'tarjeta', 'efectivo'];
            update_field('garantia_contratada_metodo_pago', $metodos[array_rand($metodos)], $post_id);
            update_field('garantia_contratada_canal_venta', 'profesional', $post_id);
            update_field('garantia_contratada_concesionario_empresa_profesional', $pros[array_rand($pros)], $post_id);
            update_field('garantia_contratada_documentacion_certificado_garantia', $doc_ids['certificado'], $post_id);
            update_field('garantia_contratada_documentacion_factura_proforma', $doc_ids['factura_proforma'], $post_id);
            update_field('garantia_contratada_documentacion_factura', $doc_ids['factura'], $post_id);

            update_field('descuentos_y_recargos_precio_base', $price, $post_id);
            $desc_rows = [
                [
                    'concepto' => 'Descuento promocional',
                    'tipo'     => 'descuento',
                    'valor'    => 10,
                ],
            ];
            update_field('descuentos_y_recargos_listado_descuentos_recargos', $desc_rows, $post_id);

            // Datos del vehículo: matrícula formatos 1234BCD o M1234BC
            if (rand(0, 1)) {
                // formato moderno
                $mat = sprintf('%04d%s', rand(0, 9999), substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 3));
            } else {
                // antiguo español
                $mat = 'M' . rand(1000, 9999) . substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 2);
            }
            update_field('datos_vehiculo_matricula', strtoupper($mat), $post_id);

            [$marca, $modelo] = $carros[array_rand($carros)];
            update_field('datos_vehiculo_marca', $marca, $post_id);
            update_field('datos_vehiculo_modelo', $modelo, $post_id);
            if (! empty($tipo_vehiculo_ids)) {
                update_field('datos_vehiculo_tipo_vehiculo', $tipo_vehiculo_ids[array_rand($tipo_vehiculo_ids)], $post_id);
            }
            update_field('datos_vehiculo_primera_matriculacion', $matric->format('Y-m-d'), $post_id);
            update_field('datos_vehiculo_kilometros', rand(10000, 150000), $post_id);
            update_field('datos_vehiculo_precio_venta', rand(5000, 30000), $post_id);
            update_field('datos_vehiculo_numero_bastidor', strtoupper(wp_generate_password(17, false, false)), $post_id);
            $combustibles = ['diesel', 'gasolina', 'electrico', 'hibrido', 'gpl_gnc'];
            $combustible  = $combustibles[array_rand($combustibles)];
            update_field('datos_vehiculo_combustible', $combustible, $post_id);
            $cambios = ['manual', 'manual_pilotado', 'automatico'];
            $cambio  = $cambios[array_rand($cambios)];
            update_field('datos_vehiculo_cambio', $cambio, $post_id);
            update_field('datos_vehiculo_potencia', rand(75, 300), $post_id);
            if (in_array($combustible, ['electrico', 'hibrido', 'gpl_gnc'], true)) {
                update_field('datos_vehiculo_potencia_kw', rand(50, 200), $post_id);
            } else {
                update_field('datos_vehiculo_cilindrada', rand(1000, 3000), $post_id);
            }
            $tracciones = ['4x4', 'delantera', 'trasera'];
            update_field('datos_vehiculo_traccion', $tracciones[array_rand($tracciones)], $post_id);

            // Datos del cliente
            update_field('datos_cliente_nombre_y_apellidos',        'Cliente Ejemplo ' . $i, $post_id);
            update_field('datos_cliente_dni',                       rand(10000000, 99999999) . chr(rand(65, 90)), $post_id);
            update_field('datos_cliente_telefono',                  '6' . rand(60000000, 79999999), $post_id);
            update_field('datos_cliente_email',                     'cliente' . $i . '@ejemplo.com', $post_id);
            update_field('datos_cliente_direccion',                 ['C/ Mayor, 1', 'Av. Libertad, 23', 'P.º del Prado, 45'][rand(0, 2)], $post_id);
            update_field('datos_cliente_localidad',                 ['Madrid', 'Barcelona', 'Valencia'][rand(0, 2)], $post_id);
            update_field('datos_cliente_provincia',                 ['Madrid', 'Barcelona', 'Valencia'][rand(0, 2)], $post_id);
            update_field('datos_cliente_codigo_postal',             rand(10000, 52999), $post_id);
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

