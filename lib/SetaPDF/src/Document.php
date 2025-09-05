<?php
namespace setasign\SetaPDF;

use setasign\SetaPDF\Core\Writer\StringWriter;

class Document
{
    private string $path;
    private ?StringWriter $writer = null;
    private string $buffer = '';

    public function __construct(string $path)
    {
        $this->path = $path;
        if (file_exists($path)) {
            $this->buffer = (string) file_get_contents($path);
        }
    }

    public function setWriter(StringWriter $writer): void
    {
        $this->writer = $writer;
    }

    public function save(): self
    {
        if ($this->writer) {
            $this->writer->setBuffer($this->buffer);
        }
        return $this;
    }

    public function finish(): void
    {
        // nothing to do in stub
    }
}
