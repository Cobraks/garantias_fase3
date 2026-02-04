<?php

namespace GarantiasOnline360VO\Notifications\Email;

if (! defined('ABSPATH')) {
    exit;
}

class TemplateRenderer
{
    /** @var string */
    private $base_path;

    public function __construct(?string $base_path = null)
    {
        $this->base_path = $base_path ?: plugin_dir_path(GARANTIAS360VO__FILE__) . 'templates/email/';
    }

    public function render(string $template, array $context = []): string
    {
        $file = trailingslashit($this->base_path) . $template . '.php';
        if (! file_exists($file)) {
            return '';
        }

        if (! empty($context)) {
            extract($context, EXTR_SKIP);
        }

        ob_start();
        include $file;
        return trim((string) ob_get_clean());
    }
}
