<?php
spl_autoload_register(function($class){
    $prefixes = [
        'mikehaertl\\pdftk\\' => __DIR__ . '/pdftk/',
        'mikehaertl\\shellcommand\\' => __DIR__ . '/shellcommand/',
        'mikehaertl\\tmp\\' => __DIR__ . '/tmp/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $relative = substr($class, strlen($prefix));
            $file = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
});
