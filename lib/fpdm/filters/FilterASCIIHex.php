<?php
class FilterASCIIHex {
    public function decode($data) {
        $filter = new \setasign\Fpdi\PdfParser\Filter\AsciiHex();
        return $filter->decode($data);
    }
}
