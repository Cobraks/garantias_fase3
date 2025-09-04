<?php
namespace GarantiasOnline360VO\Docs;

use setasign\Fpdi\Fpdi;
use GarantiasOnline360VO\GuaranteeLogger;

if (!defined('ABSPATH')) {
    exit;
}

class CertificateGenerator
{
    public static function generate(int $guarantee_id): ?string
    {
        $modalidad_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_garantia', true);
        if (!$modalidad_id) {
            return null;
        }
        $file = function_exists('get_field') ? get_field('documentos_certificado_garantia', $modalidad_id) : null;
        $path = is_array($file) && !empty($file['path']) ? $file['path'] : '';
        if (!$path || !file_exists($path)) {
            return null;
        }
        try {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->setSourceFile($path);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0);
            $content = $pdf->Output('S');
        } catch (\Throwable $e) {
            return null;
        }
        $hash = PrivateDocsManager::store($content, 'pdf');
        if ($hash) {
            GuaranteeLogger::log(get_current_user_id(), $guarantee_id, 'document_generated', 'certificado');
        }
        return $hash ?: null;
    }
}
