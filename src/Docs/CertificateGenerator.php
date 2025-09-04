<?php
namespace GarantiasOnline360VO\Docs;

use mikehaertl\pdftk\Pdf;
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

            $binary = defined('GO_PDFTK_PATH') ? GO_PDFTK_PATH : 'pdftk';
            error_log('[CertificateGenerator] using pdftk binary ' . $binary);
            $options = [
                'useExec' => true,
                'escapeArgs' => false,
                'command' => $binary,
            ];
            $pdf = new Pdf($path, $options);
            if ($data) {
                $pdf->fillForm($data)->needAppearances()->flatten();
            }

            $content = $pdf->toString();
            if ($content === false) {
                $cmd = $pdf->getCommand();
                $error = $pdf->getError();
                error_log('[CertificateGenerator] pdftk error ' . $error);
                if ($cmd) {
                    error_log('[CertificateGenerator] pdftk command ' . $cmd->getCommand());
                    error_log('[CertificateGenerator] pdftk exit code ' . $cmd->getExitCode());
                }
                if (!$cmd || $cmd->getExitCode() === 127) {
                    error_log('[CertificateGenerator] pdftk binary missing or not executable');
                }
                error_log('[CertificateGenerator] falling back to FPDI');
                $content = self::generateWithFpdi($path, $combustible);
            }
        } catch (\Throwable $e) {
            error_log('[CertificateGenerator] pdftk exception ' . $e->getMessage());
            error_log('[CertificateGenerator] falling back to FPDI');
            $content = self::generateWithFpdi($path, $combustible);
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
