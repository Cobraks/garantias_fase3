<?php
namespace GarantiasOnline360VO\Docs;

if (!defined('ABSPATH')) {
    exit;
}

class PrivateDocsManager
{
    const DIR = 'private-docs';

    public static function get_dir(): string
    {
        return trailingslashit(WP_CONTENT_DIR) . self::DIR;
    }

    public static function ensure_directory(): void
    {
        $dir = self::get_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
    }

    private static function get_key(): string
    {
        if (defined('AUTH_KEY')) {
            return hash('sha256', AUTH_KEY);
        }
        return hash('sha256', site_url());
    }

    public static function store(string $binary, string $extension = 'pdf'): string
    {
        self::ensure_directory();
        $hash = hash('sha256', $binary . microtime(true));
        $path = self::get_dir() . '/' . $hash . '.' . $extension . '.enc';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($binary, 'aes-256-cbc', self::get_key(), OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return '';
        }
        $data = base64_encode($iv . $encrypted);
        file_put_contents($path, $data);
        return $hash;
    }

    public static function retrieve(string $hash, string $extension = 'pdf'): ?string
    {
        $path = self::get_dir() . '/' . $hash . '.' . $extension . '.enc';
        if (!file_exists($path)) {
            return null;
        }
        $data = base64_decode(file_get_contents($path));
        if ($data === false) {
            return null;
        }
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $binary = openssl_decrypt($encrypted, 'aes-256-cbc', self::get_key(), OPENSSL_RAW_DATA, $iv);
        return $binary === false ? null : $binary;
    }
}
