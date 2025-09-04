<?php
namespace GarantiasOnline360VO\Documents;

use setasign\Fpdi\Fpdi;

if (! defined('ABSPATH')) {
    exit;
}

class CertificateGenerator
{
    public static function generate(int $guarantee_id): array
    {
        error_log('[CertificateGenerator] Iniciando para garantía ' . $guarantee_id);

        $modality_id = (int) get_post_meta($guarantee_id, 'garantia_contratada_garantia', true);
        $file = get_field('detalles_modalidad_documentos_certificado_garantia', $modality_id);
        if (empty($file['url'])) {
            error_log('[CertificateGenerator] PDF base no encontrado');
            return [];
        }

        $tmp = download_url($file['url']);
        if (is_wp_error($tmp)) {
            error_log('[CertificateGenerator] Error descargando PDF base');
            return [];
        }

        $fpdi = new Fpdi();
        $fpdi->setSourceFile($tmp);
        $tpl = $fpdi->importPage(1);
        $fpdi->addPage();
        $fpdi->useTemplate($tpl);
        $fpdi->SetFont('Helvetica');

        $data = [
            'pdf_id_matricula'    => get_post_meta($guarantee_id, 'datos_vehiculo_matricula', true),
            'pdf_nombre_apellidos'=> get_post_meta($guarantee_id, 'datos_cliente_nombre_y_apellidos', true),
            'pdf_marca'           => get_post_meta($guarantee_id, 'datos_vehiculo_marca', true),
            'pdf_modelo'          => get_post_meta($guarantee_id, 'datos_vehiculo_modelo', true),
        ];

        $positions = [
            'pdf_id_matricula'     => [20, 20],
            'pdf_nombre_apellidos' => [20, 30],
            'pdf_marca'            => [20, 40],
            'pdf_modelo'           => [20, 50],
        ];

        foreach ($data as $field => $value) {
            error_log('[CertificateGenerator] Campo ' . $field . ' => ' . $value);
            if ($value === '') {
                continue;
            }
            if (isset($positions[$field])) {
                [$x, $y] = $positions[$field];
                $fpdi->SetXY($x, $y);
                $fpdi->Write(5, (string) $value);
            }
        }

        $pdf_content = $fpdi->Output('S');
        @unlink($tmp);

        return EncryptedStorage::save($pdf_content);
    }
}
