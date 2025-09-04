<?php
class FilterASCII85 {
    public function decode($data) {
        $filter = new \setasign\Fpdi\PdfParser\Filter\Ascii85();
        return $filter->decode($data);
    }
}
