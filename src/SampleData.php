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
        // Obtener modalidades de garantía disponibles
        $modalidades = get_posts([
            'post_type'      => ModalidadesGarantiasCPT::POST_TYPE,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        // Usuarios para campos de relación
        $profesionales = get_users([
            'role'   => 'go_profesional',
            'fields' => 'ID',
        ]);
        $gestorias = get_users([
            'role'   => 'go_gestoria',
            'fields' => 'ID',
        ]);

        // Asegurar términos en taxonomías
        $tipo_garantia_terms = get_terms([
            'taxonomy'   => 'tipo_garantia',
            'hide_empty' => false,
        ]);
        if (empty($tipo_garantia_terms)) {
            wp_insert_term('Básica', 'tipo_garantia');
            wp_insert_term('Premium', 'tipo_garantia');
            $tipo_garantia_terms = get_terms([
                'taxonomy'   => 'tipo_garantia',
                'hide_empty' => false,
            ]);
        }

        $nivel_garantia_terms = get_terms([
            'taxonomy'   => 'nivel_garantia',
            'hide_empty' => false,
        ]);
        if (empty($nivel_garantia_terms)) {
            wp_insert_term('Nivel 1', 'nivel_garantia');
            wp_insert_term('Nivel 2', 'nivel_garantia');
            $nivel_garantia_terms = get_terms([
                'taxonomy'   => 'nivel_garantia',
                'hide_empty' => false,
            ]);
        }

        $tipo_vehiculo_terms = get_terms([
            'taxonomy'   => 'tipo_vehiculo',
            'hide_empty' => false,
        ]);
        if (empty($tipo_vehiculo_terms)) {
            wp_insert_term('Turismo', 'tipo_vehiculo');
            wp_insert_term('Furgoneta', 'tipo_vehiculo');
            $tipo_vehiculo_terms = get_terms([
                'taxonomy'   => 'tipo_vehiculo',
                'hide_empty' => false,
            ]);
        }

        // Opciones para campos select
        $estados_contratacion = ['draft', 'pendiente_pago', 'activada', 'expira_pronto', 'expirada'];
        $meses_contratados    = [6, 12, 24, 36];
        $metodos_pago         = ['domiciliacion_bancaria', 'transferencia'];
        $canales_venta        = ['profesional', 'particular', 'gestoria'];
        $combustibles         = ['diesel', 'gasolina', 'electrico', 'hibrido', 'gpl_gnc'];
        $cambios              = ['manual', 'manual_pilotado', 'automatico'];
        $tracciones           = ['4x4', 'delantera', 'trasera'];
        $tracciones_camion    = ['1_eje', '2_ejes', '3_ejes'];
        $mmas                 = ['entre_35_y_60', 'entre_60_y_160', 'mas_de_160'];
        $estados_averia       = ['abierta', 'pendiente_taller', 'espera_info', 'cerrada'];
        $tipos_averia         = ['motor', 'caja_cambios', 'sistema_electrico', 'sistema_refrigeracion', 'sistema_combustible', 'sistema_escape', 'sistema_direccion', 'sistema_suspension', 'sistema_frenado', 'sistema_aire_acondicionado', 'otro'];

        // Marcas y modelos de ejemplo
        $vehiculos = [
            ['marca' => 'Ford',       'modelo' => 'Fiesta'],
            ['marca' => 'Seat',       'modelo' => 'León'],
            ['marca' => 'BMW',        'modelo' => 'Serie 3'],
            ['marca' => 'Audi',       'modelo' => 'A4'],
            ['marca' => 'Mercedes',   'modelo' => 'Clase C'],
            ['marca' => 'Renault',    'modelo' => 'Clio'],
            ['marca' => 'Volkswagen', 'modelo' => 'Golf'],
            ['marca' => 'Toyota',     'modelo' => 'Corolla'],
            ['marca' => 'Peugeot',    'modelo' => '208'],
            ['marca' => 'Citroën',    'modelo' => 'C3'],
        ];

        for ($i = 1; $i <= 20; $i++) {
            $status  = $estados_contratacion[array_rand($estados_contratacion)];
            $post_id = wp_insert_post([
                'post_type'   => GuaranteeCPT::POST_TYPE,
                'post_title'  => 'Garantía ' . wp_generate_password(4, false, false),
                'post_status' => $status,
            ]);
            if (! $post_id || is_wp_error($post_id)) {
                continue;
            }

            // Fechas
            $start  = new \DateTime();
            $start->sub(new \DateInterval('P' . rand(0, 180) . 'D'));
            $meses  = $meses_contratados[array_rand($meses_contratados)];
            $end    = (clone $start)->add(new \DateInterval('P' . $meses . 'M'));
            $now    = new \DateTime();
            $diff   = $now->diff($end);
            $restan = max(0, $diff->m + ($diff->y * 12));

            $estado_garantia = [
                'inicio'          => $start->format('Y-m-d'),
                'finalizacion'    => $end->format('Y-m-d'),
                'meses_restantes' => $restan,
                'uuid'            => wp_generate_uuid4(),
            ];

            $modalidad_id = $modalidades ? $modalidades[array_rand($modalidades)] : 0;
            $garantia_contratada = [
                'garantia'                         => $modalidad_id,
                'tipo_garantia'                    => $tipo_garantia_terms[array_rand($tipo_garantia_terms)]->term_id,
                'nivel_garantia'                   => $nivel_garantia_terms[array_rand($nivel_garantia_terms)]->term_id,
                'meses_contratados'                => $meses,
                'precio'                           => rand(200, 1000),
                'metodo_pago'                      => $metodos_pago[array_rand($metodos_pago)],
                'canal_venta'                      => $canales_venta[array_rand($canales_venta)],
                'concesionario_empresa_profesional'=> $profesionales ? $profesionales[array_rand($profesionales)] : 0,
                'gestoria'                         => $gestorias ? $gestorias[array_rand($gestorias)] : 0,
                'descuentos_y_recargos'            => [
                    'precio_base'              => rand(200, 1000),
                    'listado_descuentos_recargos' => [
                        [
                            'tipo'       => 'descuento',
                            'porcentaje' => rand(1, 20),
                            'razon'      => 'Descuento de ejemplo',
                        ],
                        [
                            'tipo'       => 'recargo',
                            'porcentaje' => rand(1, 15),
                            'razon'      => 'Recargo de ejemplo',
                        ],
                    ],
                ],
            ];

            // Vehículo
            $veh = $vehiculos[array_rand($vehiculos)];
            $mat = sprintf('%04d%s', rand(0, 9999), substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 3));
            $matricula = strtoupper($mat);

            $datos_vehiculo = [
                'tipo_vehiculo'    => $tipo_vehiculo_terms[array_rand($tipo_vehiculo_terms)]->term_id,
                'marca'            => $veh['marca'],
                'modelo'           => $veh['modelo'],
                'marca_modelo'     => $veh['marca'] . ' ' . $veh['modelo'],
                'matricula'        => $matricula,
                'primera_matriculacion' => (new \DateTime('2015-01-01'))
                    ->add(new \DateInterval('P' . rand(0, 365 * 8) . 'D'))
                    ->format('Y-m-d'),
                'kilometros'       => rand(10000, 200000),
                'precio_venta'     => rand(5000, 30000),
                'combustible'      => $combustibles[array_rand($combustibles)],
                'cambio'           => $cambios[array_rand($cambios)],
                'potencia'         => rand(60, 300),
                'potencia_kw'      => rand(40, 250),
                'cilindrada'       => rand(1000, 3000),
                'numero_bastidor'  => strtoupper(wp_generate_password(17, false, false)),
                'traccion'         => $tracciones[array_rand($tracciones)],
                'traccion_camion'  => $tracciones_camion[array_rand($tracciones_camion)],
                'mma'              => $mmas[array_rand($mmas)],
                'doble_motor'      => rand(0, 1),
            ];

            // Cliente
            $datos_cliente = [
                'nombre_y_apellidos' => 'Cliente Ejemplo ' . $i,
                'dni'                 => rand(10000000, 99999999) . chr(rand(65, 90)),
                'telefono'            => '6' . rand(00000000, 99999999),
                'email'               => 'cliente' . $i . '@ejemplo.com',
                'direccion'           => 'Calle Falsa ' . rand(1, 99),
                'localidad'           => 'Ciudad ' . $i,
                'provincia'           => 'Provincia ' . $i,
                'codigo_postal'       => rand(10000, 52999),
            ];

            // Avería
            $estado_averia = [
                'estado'         => $estados_averia[array_rand($estados_averia)],
                'fecha_apertura' => (new \DateTime())->sub(new \DateInterval('P' . rand(0, 30) . 'D'))->format('Y-m-d'),
                'tipo_averia'    => $tipos_averia[array_rand($tipos_averia)],
            ];

            $informacion_averia = [
                'descripcion_averia' => 'Descripción ficticia de la avería.',
                'importes_resolucion' => [
                    'presupuesto_recibido' => rand(100, 1000),
                    'importe_autorizado'   => rand(100, 1000),
                    'resolucion'           => 'Resolución ficticia.',
                ],
            ];

            // Taller
            $taller = [
                'taller_encargado' => ['taller_asociado', 'otro'][rand(0, 1)],
                'responsable'     => 'Responsable ' . $i,
                'telefono_taller' => rand(900000000, 999999999),
                'correo_taller'   => 'taller' . $i . '@mail.com',
                'direccion_taller'=> 'Dirección taller ' . $i,
            ];

            // Historiales y notas
            $historiales_notas = [
                'historial_comunicacion' => 'Historial de comunicación de prueba.',
                'notas_internas'         => 'Notas internas de ejemplo.',
                'resumen'                => 'Resumen de la garantía de prueba.',
            ];

            // Guardar campos
            update_field('estado_garantia', $estado_garantia, $post_id);
            update_field('garantia_contratada', $garantia_contratada, $post_id);
            update_field('datos_vehiculo', $datos_vehiculo, $post_id);
            update_field('datos_cliente', $datos_cliente, $post_id);
            update_field('estado_averia', $estado_averia, $post_id);
            update_field('informacion_averia', $informacion_averia, $post_id);
            update_field('taller', $taller, $post_id);
            update_field('historiales_notas', $historiales_notas, $post_id);
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
}

