<?php
namespace GarantiasOnline360VO\Docs;

use GarantiasOnline360VO\GuaranteeLogger;
use Pdftk\Pdf as PdftkPdf;
use setasign\Fpdi\Fpdi;

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
        $combustible = sanitize_text_field((string) get_post_meta($guarantee_id, 'datos_vehiculo_combustible', true));
        error_log('[CertificateGenerator] combustible ' . $combustible);

        $cp = sanitize_text_field((string) get_post_meta($guarantee_id, 'datos_cliente_codigo_postal', true));
        error_log('[CertificateGenerator] cp ' . $cp);

        $nombre = sanitize_text_field((string) get_post_meta($guarantee_id, 'datos_cliente_nombre_y_apellidos', true));
        error_log('[CertificateGenerator] nombre_apellidos ' . $nombre);

        $data = [];
        if ($combustible !== '') {
            $data['pdf_combustible'] = $combustible;
        }
        if ($cp !== '') {
            $data['pdf_cp'] = $cp;
        }
        if ($nombre !== '') {
            $data['pdf_nombre_apellidos'] = $nombre;
        }
        error_log('[CertificateGenerator] field data ' . wp_json_encode($data));

        $content = '';
        $binary = dirname(__DIR__, 2) . '/lib/pdftk-php/bin/' . (strncasecmp(PHP_OS, 'WIN', 3) === 0 ? 'pdftk.exe' : 'pdftk');
        if (is_file($binary) && is_executable($binary)) {
            try {
                $pdftk = new PdftkPdf($binary);
                $tmpOut = tempnam(sys_get_temp_dir(), 'pdf');
                $pdftk->fillForm($path, $data, $tmpOut);
                $content = file_get_contents($tmpOut) ?: '';
                @unlink($tmpOut);
                error_log('[CertificateGenerator] pdftk form filled');
            } catch (\Throwable $e) {
                error_log('[CertificateGenerator] pdftk error ' . $e->getMessage());
            }
        } else {
            error_log('[CertificateGenerator] pdftk binary missing or not executable');
        }

        if ($content === '') {
            // Fallback: write text using FPDI
            try {
                $pdf = new Fpdi();
                $pdf->setSourceFile($path);
                $tpl = $pdf->importPage(1);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
                $pdf->SetFont('Helvetica', '', 12);
                $y = 10;
                foreach ($data as $value) {
                    $pdf->SetXY(10, $y);
                    $pdf->Write(5, $value);
                    $y += 6;
                }
                $content = $pdf->Output('S');
                error_log('[CertificateGenerator] fallback FPDI used');
            } catch (\Throwable $e) {
                error_log('[CertificateGenerator] FPDI fallback error ' . $e->getMessage());
                return null;
            }
        }

        if ($content === '') {
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

}
