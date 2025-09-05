<?php
spl_autoload_register(function($class){
    if (strpos($class, 'setasign\\SetaPDF') !== 0) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('setasign\\SetaPDF\\', '', $class) . '.php';
    $path = str_replace('\\', '/', $path);
    if (file_exists($path)) {
        require_once $path;
    }
});
