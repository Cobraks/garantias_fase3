<?php

namespace GarantiasOnline360VO\Notifications\Email;

if (! defined('ABSPATH')) {
    exit;
}

class EmailMessage
{
    /** @var string[] */
    private $to;

    /** @var string */
    private $subject;

    /** @var string */
    private $body;

    /** @var array */
    private $headers;

    /** @var array */
    private $attachments;

    public function __construct(array $to, string $subject, string $body, array $headers = [], array $attachments = [])
    {
        $this->to          = array_values(array_filter(array_map('sanitize_email', $to)));
        $this->subject     = wp_strip_all_tags($subject);
        $this->body        = $body;
        $this->headers     = $headers;
        $this->attachments = $attachments;
    }

    /** @return string[] */
    public function get_recipients(): array
    {
        return $this->to;
    }

    public function get_subject(): string
    {
        return $this->subject;
    }

    public function get_body(): string
    {
        return $this->body;
    }

    /** @return array */
    public function get_headers(): array
    {
        return $this->headers;
    }

    /** @return array */
    public function get_attachments(): array
    {
        return $this->attachments;
    }

    public function has_recipients(): bool
    {
        return ! empty($this->to);
    }
}
