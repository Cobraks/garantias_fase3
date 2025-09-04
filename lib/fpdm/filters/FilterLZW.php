<?php
class FilterLZW {
    public function decode($data) {
        $filter = new \setasign\Fpdi\PdfParser\Filter\Lzw();
        return $filter->decode($data);
    }
}
