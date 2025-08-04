<?php

/**
 * PSR-4 Autoloader
 */

namespace GarantiasOnline360VO;

if (! defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    public static function run(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        $prefix = __NAMESPACE__ . '\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
}
