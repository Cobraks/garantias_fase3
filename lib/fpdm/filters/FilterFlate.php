<?php
class FilterFlate {
    public function decode($data) {
        $filter = new \setasign\Fpdi\PdfParser\Filter\Flate();
        return $filter->decode($data);
    }
}
