<?php
namespace GarantiasOnline360VO\Documents;

if (! defined('ABSPATH')) {
    exit;
}

class EncryptedStorage
{
    private const CIPHER = 'AES-256-CBC';

    public static function save(string $binary): array
    {
        $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
        $iv  = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($binary, self::CIPHER, $key, 0, $iv);
        $filename  = sha1(random_bytes(40)) . '.pdf';
        $dir       = WP_CONTENT_DIR . '/private-docs';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $path = $dir . '/' . $filename;
        error_log('[EncryptedStorage] Guardando PDF en ' . $path);
        file_put_contents($path, $encrypted);
        return ['hash' => $filename, 'iv' => base64_encode($iv)];
    }

    public static function read(string $filename, string $ivB64): string
    {
        $key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
        $iv  = base64_decode($ivB64);
        $path = WP_CONTENT_DIR . '/private-docs/' . $filename;
        error_log('[EncryptedStorage] Leyendo PDF de ' . $path);
        if (!file_exists($path)) {
            return '';
        }
        $encrypted = file_get_contents($path);
        return openssl_decrypt($encrypted, self::CIPHER, $key, 0, $iv);
    }
}
