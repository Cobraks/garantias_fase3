<?php

namespace GarantiasOnline360VO;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;

if (! defined('ABSPATH')) {
    exit;
}

class GuaranteeLogger
{
    const TABLE = 'guarantee_logs';

    public static function ensure_table(): void
    {
        ActivityLogger::ensure_table();
    }

    public static function create_table(): void
    {
        ActivityLogger::create_table();
    }

    public static function log(int $user_id, int $guarantee_id, string $event_type, string $details = ''): void
    {
        $mapped = self::map_event_type($event_type);
        $context = self::build_context($event_type, $details, $guarantee_id);

        ActivityLogger::log($mapped, [
            'actor_id'     => $user_id,
            'guarantee_id' => $guarantee_id,
            'target_type'  => 'guarantee',
            'target_id'    => $guarantee_id,
            'message'      => $details,
            'context'      => $context,
        ]);
    }

    public static function get_logs(array $args = []): array
    {
        $result = ActivityLogger::query([
            'actor_id'     => isset($args['user_id']) ? (int) $args['user_id'] : 0,
            'guarantee_id' => isset($args['guarantee_id']) ? (int) $args['guarantee_id'] : 0,
            'per_page'     => isset($args['per_page']) ? (int) $args['per_page'] : 20,
            'page'         => isset($args['page']) ? (int) $args['page'] : 1,
            'category'     => 'guarantee',
        ]);

        return $result['data'] ?? [];
    }

    private static function map_event_type(string $legacy): string
    {
        $legacy = sanitize_key(str_replace(' ', '_', $legacy));
        $map = [
            'created'             => 'guarantee.created',
            'updated'             => 'guarantee.updated',
            'status_changed'      => 'guarantee.status_changed',
            'document_downloaded' => 'document.downloaded',
            'document_uploaded'   => 'document.uploaded',
            'document_generated'  => 'document.generated',
            'email_sent'          => 'email.sent',
            'email_failed'        => 'email.failed',
            'email_skipped'       => 'email.skipped',
            'contracted'          => 'guarantee.updated',
        ];

        return $map[$legacy] ?? 'guarantee.updated';
    }

    private static function build_context(string $event_type, string $details, int $guarantee_id): array
    {
        $context = [
            'legacy_event'   => $event_type,
            'guarantee_id'   => $guarantee_id,
        ];

        $title = get_the_title($guarantee_id);
        if ($title) {
            $context['guarantee_label'] = $title;
        }

        if ($details !== '') {
            $context['legacy_details'] = $details;
        }

        if ($event_type === 'status_changed' && strpos($details, 'De ') === 0) {
            $parts = explode(' a ', substr($details, 3));
            if (count($parts) === 2) {
                $context['old_status'] = trim($parts[0]);
                $context['new_status'] = trim($parts[1]);
            }
        }

        if (in_array($event_type, ['document_downloaded','document_uploaded','document_generated'], true) && $details !== '') {
            $context['document_type'] = $details;
        }

        return $context;
    }
}
