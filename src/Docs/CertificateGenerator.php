<?php
namespace GarantiasOnline360VO\Docs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles certificate persistence and debug logging.
 */
class CertificateGenerator
{
    /**
     * Decode a base64 PDF and store it using PrivateDocsManager.
     * Returns the hash or an empty string on failure.
     */
    public static function store_from_base64(string $base64, int $guarantee_id = 0): string
    {
        error_log('[CertificateGenerator] storing certificate for ' . $guarantee_id);
        $binary = base64_decode($base64);
        if ($binary === false) {
            error_log('[CertificateGenerator] base64 decode failed');
            return '';
        }
        $hash = PrivateDocsManager::store($binary, 'pdf');
        if (!$hash) {
            error_log('[CertificateGenerator] store failed');
        } else {
            error_log('[CertificateGenerator] stored with hash ' . $hash);
        }
        return $hash;
    }
}
