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

        return wp_mail(
            $recipients,
            $message->get_subject(),
            $message->get_body(),
            $headers,
            $message->get_attachments()
        );
    }
}
