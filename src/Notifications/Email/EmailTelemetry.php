<?php

namespace GarantiasOnline360VO\Notifications\Email;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;

if (! defined('ABSPATH')) {
    exit;
}

class EmailTelemetry
{
    public static function init(): void
    {
        add_action('wp_mail_failed', [__CLASS__, 'on_mail_failed'], 10, 1);
        add_action('go360/email/send_result', [__CLASS__, 'on_send_result'], 10, 6);
    }

    public static function on_mail_failed(\WP_Error $error): void
    {
        $data = $error->get_error_data();
        $payload = is_array($data) ? $data : [];

        ActivityLogger::log('email.failed', [
            'event_category' => 'communications',
            'level'          => 'error',
            'context'        => [
                'source'      => 'wp_mail_failed',
                'error_code'  => $error->get_error_code(),
                'error'       => $error->get_error_message(),
                'mail_data'   => wp_json_encode($payload),
            ],
        ]);
    }

    /**
     * @param array<int,string> $recipients
     * @param array<int,string> $headers
     */
    public static function on_send_result(bool $sent, string $correlation_id, EmailMessage $message, array $recipients, array $headers): void
    {
        ActivityLogger::log($sent ? 'email.sent' : 'email.failed', [
            'event_category' => 'communications',
            'level'          => $sent ? 'info' : 'error',
            'context'        => [
                'correlation_id' => $correlation_id,
                'to'             => implode(',', $recipients),
                'cc'             => implode(',', $message->get_cc()),
                'bcc'            => implode(',', $message->get_bcc()),
                'reply_to'       => $message->get_reply_to(),
                'subject'        => $message->get_subject(),
                'headers'        => wp_json_encode($headers),
            ],
        ]);
    }
}
