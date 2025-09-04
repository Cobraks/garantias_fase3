<?php
namespace GarantiasOnline360VO\Pdf;

use GarantiasOnline360VO\GuaranteeCPT;
use WP_Query;

if (! defined('ABSPATH')) {
    exit;
}

class Storage
{
    public const META_KEY = '_go_doc_hash';

    public static function dir(): string
    {
        return WP_CONTENT_DIR . '/private-docs';
    }

    public static function ensure_dir(): void
    {
        $dir = self::dir();
        if (! file_exists($dir)) {
            error_log('[GO] Creando directorio de documentos: ' . $dir);
            wp_mkdir_p($dir);
            @chmod($dir, 0750);
        }
    }

    public static function save(int $post_id, \FPDF $pdf): string
    {
        self::ensure_dir();
        $hash = wp_hash($post_id . '|' . time() . '|' . wp_rand());
        $path = self::dir() . '/' . $hash . '.pdf';
        error_log('[GO] Guardando PDF en ' . $path);
        $pdf->Output('F', $path);
        update_post_meta($post_id, self::META_KEY, $hash);
        return $hash;
    }

    public static function path(string $hash): string
    {
        return self::dir() . '/' . $hash . '.pdf';
    }

    public static function serve(string $hash): void
    {
        $path = self::path($hash);
        if (! file_exists($path)) {
            error_log('[GO] Archivo no encontrado para hash ' . $hash);
            status_header(404);
            exit;
        }
        error_log('[GO] Sirviendo archivo ' . $path);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificado.pdf"');
        readfile($path);
    }

    public static function post_id_from_hash(string $hash): int
    {
        error_log('[GO] Buscando post por hash ' . $hash);
        $q = new WP_Query([
            'post_type'      => GuaranteeCPT::POST_TYPE,
            'meta_key'       => self::META_KEY,
            'meta_value'     => $hash,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        $post_id = $q->have_posts() ? (int) $q->posts[0] : 0;
        error_log('[GO] post_id_from_hash resultado: ' . $post_id);
        return $post_id;
    }
}
