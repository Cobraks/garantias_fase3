<?php
namespace GarantiasOnline360VO\Docs;

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
        $file = function_exists('get_field') ? get_field('detalles_modalidad_documentos_certificado_garantia', $modalidad_id) : null;
        error_log('[CertificateGenerator] field value ' . print_r($file, true));
        $path = is_array($file) && isset($file['ID']) ? get_attached_file($file['ID']) : '';
        error_log('[CertificateGenerator] template path ' . $path);
        if (!$path || !file_exists($path)) {
            error_log('[CertificateGenerator] template missing or unreadable: ' . $path);
            return null;
        }
        try {
            $combustible = get_post_meta($guarantee_id, 'datos_vehiculo_combustible', true);
            error_log('[CertificateGenerator] combustible ' . $combustible);

            $fields = [];
            if ($combustible) {
                $fields['pdf_combustible'] = (string) $combustible;
            }

            $pdf = new \FPDM($path);
            $pdf->Load($fields);
            $pdf->Merge();
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
