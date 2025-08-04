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
            'post_type'      => WarrantyPlanCPT::POST_TYPE,
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

        // URL de ejemplo para contrato PDF
        $contract_url = 'https://www.gifted-blackwell.31-170-100-104.plesk.page/wp-content/uploads/2025/05/Pepito-Perez-Febrero-2025-copia-3.pdf';
        // IDs de usuarios profesionales
        $pros = [18, 2];

        // Rango de fechas para matriculación random
        $min_matricula = new \DateTime('2019-01-01');
        $max_matricula = new \DateTime('now');

        // Lista de marca y modelo realistas
        $carros = [
            'Ford Fiesta',
            'Seat León',
            'BMW Serie 3',
            'Audi A4',
            'Mercedes Clase C',
            'Renault Clio',
            'Volkswagen Golf',
            'Toyota Corolla',
            'Peugeot 208',
            'Citroën C3'
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

            // Generar UUID
            update_field('estado_garantia_uuid', wp_generate_uuid4(), $post_id);

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

            // Fecha de primera matriculación random entre 2019 y hoy
            $randTs = rand($min_matricula->getTimestamp(), $max_matricula->getTimestamp());
            $matric = (new \DateTime())->setTimestamp($randTs);

            // Actualizar campos ACF (fechas en formato Y-m-d para ACF date picker)
            update_field('estado_garantia_inicio',      $start->format('Y-m-d'), $post_id);
            update_field('estado_garantia_finalizacion', $end->format('Y-m-d'),   $post_id);
            update_field('estado_garantia_estado_contratacion', (rand(0, 10) < 8 ? 'activada' : 'pendiente'), $post_id);

            update_field('garantia_contratada_garantia',    $plan_id,   $post_id);
            update_field('garantia_contratada_precio',      $price,     $post_id);
            update_field('garantia_contratada_canal_venta', 'profesional', $post_id);
            update_field('garantia_contratada_concesionario_empresa_profesional', $pros[array_rand($pros)], $post_id);

            // Asignar PDF de contrato
            $att_id = attachment_url_to_postid($contract_url);
            if ($att_id) {
                update_field('garantia_contratada_documentacion_contrato', $att_id, $post_id);
            }

            // Datos del vehículo: matrícula formatos 1234BCD o M1234BC
            if (rand(0, 1)) {
                // formato moderno
                $mat = sprintf('%04d%s', rand(0, 9999), substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 3));
            } else {
                // antiguo español
                $mat = 'M' . rand(1000, 9999) . substr(str_shuffle('BCDFGHJKLMNPQRSTVWXYZ'), 0, 2);
            }
            update_field('datos_vehiculo_matricula', strtoupper($mat), $post_id);

            // Marca y modelo realista
            update_field('datos_vehiculo_marca_modelo', $carros[array_rand($carros)], $post_id);
            // Tipo de vehículo
            $tipos = ['Turismo', 'Furgoneta', 'Camión'];
            update_field('datos_vehiculo_tipo_vehiculo', $tipos[array_rand($tipos)], $post_id);
            update_field('datos_vehiculo_primera_matriculacion',    $matric->format('Y-m-d'), $post_id);
            update_field('datos_vehiculo_kilometros',               rand(10000, 150000), $post_id);
            update_field('datos_vehiculo_precio_venta',             rand(5000, 30000),   $post_id);
            update_field('datos_vehiculo_numero_bastidor',          strtoupper(wp_generate_password(17, false, false)), $post_id);
            update_field('datos_vehiculo_combustible',              ['Gasolina', 'Diésel', 'Híbrido'][rand(0, 2)], $post_id);
            update_field('datos_vehiculo_cambio',                   ['Manual', 'Automático'][rand(0, 1)], $post_id);
            update_field('datos_vehiculo_potencia',                 rand(75, 300) . ' cv', $post_id);
            update_field('datos_vehiculo_Cilindrada',               rand(1, 3) . '000 cc', $post_id);

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
}

