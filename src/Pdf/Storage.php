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
            wp_mkdir_p($dir);
            @chmod($dir, 0750);
        }
    }

    public static function save(int $post_id, \FPDF $pdf): string
    {
        self::ensure_dir();
        $hash = wp_hash($post_id . '|' . time() . '|' . wp_rand());
        $path = self::dir() . '/' . $hash . '.pdf';
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
            status_header(404);
            exit;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificado.pdf"');
        readfile($path);
    }

    public static function post_id_from_hash(string $hash): int
    {
        $q = new WP_Query([
            'post_type'      => GuaranteeCPT::POST_TYPE,
            'meta_key'       => self::META_KEY,
            'meta_value'     => $hash,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        return $q->have_posts() ? (int) $q->posts[0] : 0;
    }
}
