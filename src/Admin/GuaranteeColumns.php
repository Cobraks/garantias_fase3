<?php
namespace GarantiasOnline360VO\Admin;

use GarantiasOnline360VO\GuaranteeCPT;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeColumns
{
    private static function estado_label(?string $estado): string
    {
        $labels = [
            'pendiente_pago' => __('Pendiente de pago', 'garantias-online-360vo'),
            'sin_finalizar'  => __('Sin finalizar', 'garantias-online-360vo'),
            'activada'       => __('Activada', 'garantias-online-360vo'),
            'expirada'       => __('Expirada', 'garantias-online-360vo'),
            'expira_pronto'  => __('Expira pronto', 'garantias-online-360vo'),
        ];
        return $labels[$estado] ?? '-';
    }

    public static function init(): void
    {
        add_filter('manage_edit-' . GuaranteeCPT::POST_TYPE . '_columns', [__CLASS__, 'add_columns']);
        add_action('manage_' . GuaranteeCPT::POST_TYPE . '_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_filter('manage_edit-' . GuaranteeCPT::POST_TYPE . '_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_sorting']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function add_columns(array $columns): array
    {
        $new = [];
        // Preserve checkbox and title first
        if (isset($columns['cb'])) {
            $new['cb'] = $columns['cb'];
            unset($columns['cb']);
        }
        if (isset($columns['title'])) {
            $new['title'] = $columns['title'];
            unset($columns['title']);
        }

        $new['estado_contratacion'] = __('Estado', 'garantias-online-360vo');
        $new['inicio'] = __('Inicio', 'garantias-online-360vo');
        $new['finalizacion'] = __('Finalización', 'garantias-online-360vo');
        $new['meses_contratados'] = __('Meses', 'garantias-online-360vo');
        $new['vendedor'] = __('Vendedor / Canal', 'garantias-online-360vo');

        // Append remaining original columns (taxonomies, date, etc.)
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
        }
        return $new;
    }

    public static function render_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'estado_contratacion':
                $estado = get_post_meta($post_id, 'estado_garantia_estado_contratacion', true);
                echo esc_html(self::estado_label($estado));
                break;
            case 'inicio':
                echo esc_html(get_post_meta($post_id, 'estado_garantia_inicio', true) ?: '-');
                break;
            case 'finalizacion':
                echo esc_html(get_post_meta($post_id, 'estado_garantia_finalizacion', true) ?: '-');
                break;
            case 'meses_contratados':
                echo esc_html(get_post_meta($post_id, 'garantia_contratada_meses_contratados', true) ?: '-');
                break;
            case 'vendedor':
                $canal = get_post_meta($post_id, 'garantia_contratada_canal_venta', true);
                $canal_value = is_array($canal) && isset($canal['value']) ? $canal['value'] : (is_string($canal) ? $canal : '');
                $canal_label = is_array($canal) && isset($canal['label']) ? $canal['label'] : ($canal_value ? ucfirst($canal_value) : '');
                $user_id = 0;
                if ($canal_value === 'profesional') {
                    $user_id = (int) get_post_meta($post_id, 'garantia_contratada_concesionario_empresa_profesional', true);
                } elseif ($canal_value === 'gestoria') {
                    $gestoria = get_post_meta($post_id, 'garantia_contratada_gestoria', true);
                    if (is_array($gestoria)) {
                        $user_id = (int) ($gestoria['ID'] ?? $gestoria['id'] ?? 0);
                    } else {
                        $user_id = (int) $gestoria;
                    }
                }
                $vendor = '';
                if ($user_id) {
                    $user = get_userdata($user_id);
                    if ($user) {
                        $vendor = $user->display_name;
                    }
                }
                $parts = [];
                if ($vendor) {
                    $parts[] = $vendor;
                }
                if ($canal_label) {
                    $parts[] = $canal_label;
                }
                echo esc_html($parts ? implode(' / ', $parts) : '-');
                break;
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['estado_contratacion'] = 'estado_contratacion';
        $columns['inicio'] = 'inicio';
        $columns['finalizacion'] = 'finalizacion';
        $columns['meses_contratados'] = 'meses_contratados';
        return $columns;
    }

    public static function handle_sorting(\WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'inicio':
                $query->set('meta_key', 'estado_garantia_inicio');
                $query->set('orderby', 'meta_value');
                break;
            case 'finalizacion':
                $query->set('meta_key', 'estado_garantia_finalizacion');
                $query->set('orderby', 'meta_value');
                break;
            case 'estado_contratacion':
                $query->set('meta_key', 'estado_garantia_estado_contratacion');
                $query->set('orderby', 'meta_value');
                break;
            case 'meses_contratados':
                $query->set('meta_key', 'garantia_contratada_meses_contratados');
                $query->set('orderby', 'meta_value_num');
                break;
        }
    }

    public static function enqueue_assets(string $hook): void
    {
        if ($hook !== 'edit.php') {
            return;
        }
        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== GuaranteeCPT::POST_TYPE) {
            return;
        }
        wp_enqueue_script('jquery-ui-resizable');
        wp_register_style('go-guarantee-columns', false, [], null);
        wp_enqueue_style('go-guarantee-columns');
        wp_add_inline_style('go-guarantee-columns', self::style());
        wp_add_inline_script('jquery-ui-resizable', self::script());
    }

    private static function script(): string
    {
        return <<<JS
jQuery(function($){
    var table = $('.wp-list-table.widefat.fixed');
    table.find('thead th').each(function(i){
        var th = $(this);
        th.resizable({
            handles: 'e',
            minWidth: 40,
            stop: function(e, ui){
                table.find('tbody tr').each(function(){
                    $(this).find('td').eq(i).width(ui.size.width);
                });
            }
        });
    });
});
JS;
    }

    private static function style(): string
    {
        return <<<CSS
.wp-list-table.widefat.fixed{display:block;overflow-x:auto}
.wp-list-table.widefat.fixed th,.wp-list-table.widefat.fixed td{white-space:nowrap}
CSS;
    }
}
