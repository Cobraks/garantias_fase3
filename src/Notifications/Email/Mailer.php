<?php

namespace GarantiasOnline360VO\Notifications\Email;

if (! defined('ABSPATH')) {
    exit;
}

class Mailer
{
    public function send(EmailMessage $message): bool
    {
        if (! $message->has_recipients()) {
            return false;
        }

        $recipients = $message->get_recipients();
        if (empty($recipients)) {
            $recipients = ['undisclosed-recipients:;'];
        }

        $headers = $message->get_headers();
        $cc = $message->get_cc();
        if (! empty($cc)) {
            $headers[] = 'Cc: ' . implode(',', $cc);
        }

        $bcc = $message->get_bcc();
        if (! empty($bcc)) {
            $headers[] = 'Bcc: ' . implode(',', $bcc);
        }

        $reply_to = $message->get_reply_to();
        if ($reply_to !== '') {
            $headers[] = 'Reply-To: ' . $reply_to;
        }
        $has_content_type = false;

        foreach ($headers as $header) {
            if (stripos($header, 'content-type:') === 0) {
                $has_content_type = true;
                break;
            }
        }

        if (! $has_content_type) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $buffer_started = false;
        if (function_exists('ob_start')) {
            ob_start();
            $buffer_started = true;
        }

        $attachments = $this->normalize_attachments($message->get_attachments());

        $sent = wp_mail(
            $recipients,
            $message->get_subject(),
            $message->get_body(),
            $headers,
            $attachments
        );

        if ($buffer_started) {
            $output = ob_get_clean();
            if (is_string($output) && trim($output) !== '') {
                $sanitized = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($output) : strip_tags($output);
                error_log('[Mailer] Unexpected output: ' . trim($sanitized));
            }
        }

        return $sent;
    }

    /**
     * @param mixed $attachments
     * @return string[]
     */
    private function normalize_attachments($attachments): array
    {
        if (! is_array($attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                $path = trim($attachment);
                if ($path !== '') {
                    $normalized[] = $path;
                }
                continue;
            }

            if (! is_array($attachment) || empty($attachment['file'])) {
                continue;
            }

            $path = (string) $attachment['file'];
            if ($path === '') {
                continue;
            }

            $normalized[] = $path;
        }

        return array_values($normalized);
    }
}
