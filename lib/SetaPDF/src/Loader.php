<?php
namespace setasign\SetaPDF;

class Loader
{
    public static function loadFile(string $path): Document
    {
        return new Document($path);
    }
}
