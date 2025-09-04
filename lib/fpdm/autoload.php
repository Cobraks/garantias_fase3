<?php
spl_autoload_register(function($class){
    if ($class === 'FPDM') {
        require __DIR__ . '/fpdm.php';
    }
});
