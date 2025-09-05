<?php
namespace setasign\SetaPDF\Core\Writer;

class StringWriter
{
    private string $buffer = '';

    public function setBuffer(string $buffer): void
    {
        $this->buffer = $buffer;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
