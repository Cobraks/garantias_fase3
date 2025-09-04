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
        error_log('[CertificateGenerator] start for guarantee ' . $guarantee_id);

        $modalidad_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_garantia', true);
        error_log('[CertificateGenerator] modalidad ' . $modalidad_id);
        if (!$modalidad_id) {
            error_log('[CertificateGenerator] missing modalidad');
            return null;
        }
        $file = function_exists('get_field') ? get_field('documentos_certificado_garantia', $modalidad_id) : null;
        $path = is_array($file) && !empty($file['path']) ? $file['path'] : '';
        error_log('[CertificateGenerator] template path ' . $path);
        if (!$path || !file_exists($path)) {
            error_log('[CertificateGenerator] template not found');
            return null;
        }
        try {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->setSourceFile($path);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0);

            $combustible = get_post_meta($guarantee_id, 'datos_vehiculo_combustible', true);
            error_log('[CertificateGenerator] combustible ' . $combustible);
            if ($combustible) {
                $pdf->SetFont('Helvetica', '', 12);
                $pdf->SetXY(10, 10);
                $pdf->Write(5, (string) $combustible);
            }

            $content = $pdf->Output('S');
        } catch (\Throwable $e) {
            error_log('[CertificateGenerator] error ' . $e->getMessage());
            return null;
        }
        $hash = PrivateDocsManager::store($content, 'pdf');
        error_log('[CertificateGenerator] stored hash ' . $hash);
        if ($hash) {
            GuaranteeLogger::log(get_current_user_id(), $guarantee_id, 'document_generated', 'certificado');
        }
        return $hash ?: null;
    }
}
