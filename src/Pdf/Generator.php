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

        $pdf = new \setasign\Fpdi\Fpdi();

        $modalidad = get_field('garantia_contratada_garantia', $post_id);
        $modalidad_id = is_object($modalidad) ? $modalidad->ID : (int) $modalidad;

        $template = get_field('detalles_modalidad_documentos_certificado_garantia', $modalidad_id);
        $path = is_array($template) && isset($template['path']) ? $template['path'] : '';

        if (! $path || ! file_exists($path)) {
            return new WP_Error('no_template', __('Plantilla PDF no encontrada', 'garantias-online-360vo'));
        }

        $pdf->setSourceFile($path);
        $tpl = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        // Aquí se rellenarían los campos con datos reales
        // Ejemplo: $pdf->SetFont('Helvetica', '', 12); $pdf->SetXY(20, 20); $pdf->Write(5, 'Demo');

        $hash = Storage::save($post_id, $pdf);
        return $hash;
    }
}
