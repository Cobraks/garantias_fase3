<?php
namespace GarantiasOnline360VO\Pdf;

use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

class Generator
{
    public static function generate(int $post_id)
    {
        require_once dirname(__DIR__, 2) . '/lib/fpdf/fpdf.php';
        require_once dirname(__DIR__, 2) . '/lib/fpdi/src/autoload.php';

        error_log('[GO] Generator start for post ' . $post_id);

        $modalidad = get_field('garantia_contratada_garantia', $post_id);
        $modalidad_id = is_object($modalidad) ? $modalidad->ID : (int) $modalidad;
        error_log('[GO] Modalidad ID: ' . $modalidad_id);

        $certificado = self::resolve_file(get_field('detalles_modalidad_documentos_certificado_garantia', $modalidad_id));
        $condicionado = self::resolve_file(get_field('detalles_modalidad_documentos_condicionado_garantia', $modalidad_id));
        $coberturas = self::resolve_file(get_field('detalles_modalidad_documentos_coberturas', $modalidad_id));

        if (! $certificado || ! file_exists($certificado)) {
            error_log('[GO] Plantilla de certificado no encontrada');
            return new WP_Error('no_template', __('Plantilla PDF no encontrada', 'garantias-online-360vo'));
        }

        // Rellenar campos del certificado base
        $filled_cert = self::fill_template($certificado, self::collect_fields($post_id));

        $pdf = new \setasign\Fpdi\Fpdi();
        self::append_pdf($pdf, $filled_cert, self::seller_stamp($post_id));
        if ($condicionado) {
            self::append_pdf($pdf, $condicionado);
        }
        if ($coberturas) {
            self::append_pdf($pdf, $coberturas);
        }

        $hash = Storage::save($post_id, $pdf);
        error_log('[GO] Documento guardado con hash: ' . $hash);
        @unlink($filled_cert);
        return $hash;
    }

    private static function resolve_file($field): string
    {
        $path = '';
        if (is_array($field)) {
            if (!empty($field['ID'])) {
                $path = get_attached_file($field['ID']);
            } elseif (!empty($field['url'])) {
                $upload_dir = wp_upload_dir();
                $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $field['url']);
            }
        }
        return $path;
    }

    private static function append_pdf(\setasign\Fpdi\Fpdi $pdf, string $file, ?string $stamp = null): void
    {
        $pageCount = $pdf->setSourceFile($file);
        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl);
            if ($page === 1 && $stamp && file_exists($stamp)) {
                // coordenadas y tamaño aproximados, ajustar según plantilla
                $pdf->Image($stamp, 150, 230, 40);
            }
        }
    }

    private static function collect_fields(int $post_id): array
    {
        $cliente  = get_field('datos_cliente', $post_id) ?: [];
        $vehiculo = get_field('datos_vehiculo', $post_id) ?: [];
        $gc       = get_field('garantia_contratada', $post_id) ?: [];
        $estado   = get_field('estado_garantia', $post_id) ?: [];

        $tipo_vehiculo = '';
        if (!empty($vehiculo['tipo_vehiculo'])) {
            $term = get_term($vehiculo['tipo_vehiculo'], 'tipo_vehiculo');
            if ($term && ! is_wp_error($term)) {
                $tipo_vehiculo = $term->name;
            }
        }

        $periodo = '';
        if (!empty($gc['meses_contratados'])) {
            $periodo = $gc['meses_contratados'] . ' meses';
        }

        return [
            'pdf_id_matricula'      => $vehiculo['matricula'] ?? '',
            'pdf_nombre_apellidos'  => $cliente['nombre_y_apellidos'] ?? '',
            'pdf_nif'               => $cliente['dni'] ?? '',
            'pdf_direccion'         => $cliente['direccion'] ?? '',
            'pdf_cp'                => $cliente['codigo_postal'] ?? '',
            'pdf_localidad'         => $cliente['localidad'] ?? '',
            'pdf_provincia'         => $cliente['provincia'] ?? '',
            'pdf_telefono'          => $cliente['telefono'] ?? '',
            'pdf_email'             => $cliente['email'] ?? '',
            'pdf_matricula'         => $vehiculo['matricula'] ?? '',
            'pdf_fecha_primera_mat' => $vehiculo['primera_matriculacion'] ?? '',
            'pdf_marca'             => $vehiculo['marca'] ?? '',
            'pdf_modelo'            => $vehiculo['modelo'] ?? '',
            'pdf_cc'                => $vehiculo['cilindrada'] ?? '',
            'pdf_bastidor'          => $vehiculo['numero_bastidor'] ?? '',
            'pdf_km'                => $vehiculo['kilometros'] ?? '',
            'pdf_cv'                => $vehiculo['potencia'] ?? '',
            'pdf_traccion'          => $vehiculo['traccion'] ?? '',
            'pdf_combustible'       => $vehiculo['combustible'] ?? '',
            'pdf_tipo_vehiculo'     => $tipo_vehiculo,
            'pdf_periodo_cobertura' => $periodo,
            'pdf_fecha_inicio'      => $estado['inicio'] ?? '',
            'pdf_fecha_finalizacion'=> $estado['finalizacion'] ?? '',
        ];
    }

    private static function fill_template(string $path, array $fields): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return $path;
        }
        foreach ($fields as $name => $value) {
            $value = self::escape($value);
            $pattern = '/\/T\(' . preg_quote($name, '/') . '\)\s*\/V\(([^\)]*)\)/';
            $replacement = '/T(' . $name . ')/V(' . $value . ')';
            $content = preg_replace($pattern, $replacement, $content);
        }
        $tmp = wp_tempnam('go_pdf');
        file_put_contents($tmp, $content);
        return $tmp;
    }

    private static function escape(string $text): string
    {
        $text = str_replace(['\\', '(' , ')'], ['\\\\', '\\(', '\\)'], $text);
        $text = str_replace(["\r", "\n"], [' ', ' '], $text);
        return $text;
    }

    private static function seller_stamp(int $post_id): ?string
    {
        $gc = get_field('garantia_contratada', $post_id);
        $owner_id = 0;
        if (is_array($gc) && !empty($gc['concesionario_empresa_profesional'])) {
            $owner_id = (int) $gc['concesionario_empresa_profesional'];
        }
        if (! $owner_id) {
            return null;
        }
        $add = get_field('firma_y_sello_add_firma_sello', 'user_' . $owner_id);
        if (! $add) {
            return null;
        }
        $sello_id = get_field('firma_y_sello_sello', 'user_' . $owner_id);
        if (! $sello_id) {
            return null;
        }
        $path = get_attached_file($sello_id);
        return ($path && file_exists($path)) ? $path : null;
    }
}
