<?php
spl_autoload_register(function($class){
    if(strpos($class,'Pdftk\\')!==0)return;
    $path=__DIR__.'/src/'.str_replace('Pdftk\\','',$class).'.php';
    if(file_exists($path))require_once $path;
});
