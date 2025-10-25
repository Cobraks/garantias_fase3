<?php
spl_autoload_register(function ($class) {
    $prefixes = [
        'mikehaertl\\pdftk\\' => __DIR__ . '/pdftk/',
        'mikehaertl\\tmp\\'   => __DIR__ . '/tmp/',
        'mikehaertl\\shellcommand\\' => __DIR__ . '/shellcommand/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});
