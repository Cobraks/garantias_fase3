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

    /** @var string[] */
    private $cc;

    /** @var string[] */
    private $bcc;

    /** @var string */
    private $reply_to;

    /** @var array */
    private $attachments;

    public function __construct(array $to, string $subject, string $body, array $headers = [], array $attachments = [], array $metadata = [])
    {
        $this->to          = $this->sanitize_recipients($to);
        $this->subject     = wp_strip_all_tags($subject);
        $this->body        = $body;
        $this->headers     = $headers;
        $this->attachments = $attachments;
        $this->cc          = $this->sanitize_recipients($metadata['cc'] ?? []);
        $this->bcc         = $this->sanitize_recipients($metadata['bcc'] ?? []);
        $this->reply_to    = $this->sanitize_single($metadata['reply_to'] ?? '');
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

    /** @return string[] */
    public function get_cc(): array
    {
        return $this->cc;
    }

    /** @return string[] */
    public function get_bcc(): array
    {
        return $this->bcc;
    }

    public function get_reply_to(): string
    {
        return $this->reply_to;
    }

    /** @return array */
    public function get_attachments(): array
    {
        return $this->attachments;
    }

    public function has_recipients(): bool
    {
        return ! empty($this->to) || ! empty($this->cc) || ! empty($this->bcc);
    }

    /**
     * @param string|string[] $recipients
     * @return string[]
     */
    private function sanitize_recipients($recipients): array
    {
        if (! is_array($recipients)) {
            $recipients = $recipients ? [$recipients] : [];
        }

        $normalized = [];
        foreach ($recipients as $recipient) {
            $sanitized = $this->sanitize_single($recipient);
            if ($sanitized !== '') {
                $normalized[$sanitized] = $sanitized;
            }
        }

        return array_values($normalized);
    }

    private function sanitize_single($recipient): string
    {
        return sanitize_email((string) $recipient);
    }
}
