<?php
namespace GarantiasOnline360VO\Docs;

use setasign\Fpdi\Fpdi;
use GarantiasOnline360VO\GuaranteeLogger;
use FPDM;

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
        $content = null;
        try {
            $data = [];
            $combustible = get_post_meta($guarantee_id, 'datos_vehiculo_combustible', true);
            error_log('[CertificateGenerator] combustible ' . $combustible);
            if ($combustible) {
                $data['pdf_combustible'] = (string) $combustible;
            }

            $cp = get_post_meta($guarantee_id, 'datos_cliente_codigo_postal', true);
            error_log('[CertificateGenerator] cp ' . $cp);
            if ($cp) {
                $data['pdf_cp'] = (string) $cp;
            }

            $nombre = get_post_meta($guarantee_id, 'datos_cliente_nombre_y_apellidos', true);
            error_log('[CertificateGenerator] nombre_apellidos ' . $nombre);
            if ($nombre) {
                $data['pdf_nombre_apellidos'] = (string) $nombre;
            }

            error_log('[CertificateGenerator] field data ' . wp_json_encode($data));

            $pdf = new FPDM($path);
            if ($data) {
                $pdf->Load($data);
            }
            $pdf->Merge();
            $content = $pdf->Output('S');
            error_log('[CertificateGenerator] FPDM merge completed');
        } catch (\Throwable $e) {
            error_log('[CertificateGenerator] FPDM error ' . $e->getMessage());
            $content = self::generateWithFpdi($path, isset($combustible) ? $combustible : '');
        }

        if (!$content) {
            error_log('[CertificateGenerator] no content generated');
            return null;
        }
        $hash = PrivateDocsManager::store($content, 'pdf');
        error_log('[CertificateGenerator] stored hash ' . $hash);
        if ($hash) {
            GuaranteeLogger::log(get_current_user_id(), $guarantee_id, 'document_generated', 'certificado');
        }
        return $hash ?: null;
    }

    private static function generateWithFpdi(string $path, string $combustible = ''): ?string
    {
        error_log('[CertificateGenerator] FPDI fallback using template ' . $path);
        try {
            $pdf = new Fpdi();
            $pdf->AddPage();
            $pdf->setSourceFile($path);
            $tpl = $pdf->importPage(1);
            $pdf->useTemplate($tpl, 0, 0);
            if ($combustible) {
                $pdf->SetFont('Arial', '', 12);
                $pdf->Text(10, 10, (string) $combustible);
            }
            return $pdf->Output('S');
        } catch (\Throwable $e) {
            error_log('[CertificateGenerator] FPDI fallback failed ' . $e->getMessage());
            return null;
        }
    }
}
