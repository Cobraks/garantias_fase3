<?php
namespace Pdftk;

class Pdf
{
    private string $binary;

    public function __construct(string $binary)
    {
        $this->binary = $binary;
    }

    /**
     * Fill form fields in $template and write to $output.
     * @throws \RuntimeException
     */
    public function fillForm(string $template, array $fields, string $output): void
    {
        $fdf = $this->createFdf($fields);
        $fdfFile = tempnam(sys_get_temp_dir(), 'fdf');
        file_put_contents($fdfFile, $fdf);
        $cmd = escapeshellcmd($this->binary) . ' ' . escapeshellarg($template) . ' fill_form ' . escapeshellarg($fdfFile) . ' output ' . escapeshellarg($output) . ' flatten';
        exec($cmd, $out, $ret);
        unlink($fdfFile);
        if ($ret !== 0) {
            throw new \RuntimeException('pdftk failed: ' . implode("\n", $out));
        }
    }

    private function createFdf(array $fields): string
    {
        $out = "%FDF-1.2\n1 0 obj <</FDF<< /Fields [";
        foreach ($fields as $name => $value) {
            $out .= '<</T (' . $this->escape($name) . ')/V (' . $this->escape($value) . ')>>';
        }
        $out .= "] >> >>\nendobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        return $out;
    }

    private function escape(string $str): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $str);
    }
}
