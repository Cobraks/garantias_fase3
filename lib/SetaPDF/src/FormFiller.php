<?php
namespace setasign\SetaPDF;

class FormFiller
{
    private Document $document;
    private Fields $fields;

    public function __construct(Document $document)
    {
        $this->document = $document;
        $this->fields = new Fields();
    }

    public function getFields(): Fields
    {
        return $this->fields;
    }
}

class Fields
{
    private array $fields = [];

    public function get(string $name): Field
    {
        if (!isset($this->fields[$name])) {
            $this->fields[$name] = new Field($name);
        }
        return $this->fields[$name];
    }
}

class Field
{
    private string $name;
    private $value = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }
}
