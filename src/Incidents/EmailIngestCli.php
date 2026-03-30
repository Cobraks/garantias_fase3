<?php

namespace GarantiasOnline360VO\Incidents;

use GarantiasOnline360VO\ActivityLog\ActivityLogger;

if (! defined('ABSPATH')) {
    exit;
}

class EmailIngestCli
{
    private const EVENT_TYPE = 'incident.email_received';

    public static function register(): void
    {
        if (! defined('WP_CLI') || ! \WP_CLI) {
            return;
        }

        \WP_CLI::add_command('go360 mail-ingest', [__CLASS__, 'handle']);
    }

    /**
     * Ingesta correos IMAP y emite eventos internos de avería.
     *
     * ## OPTIONS
     *
     * [--env-file=<path>]
     * : Ruta del fichero .env con configuración IMAP.
     *
     * [--limit=<n>]
     * : Máximo de correos a procesar por ejecución.
     *
     * ## EXAMPLES
     *
     *     wp go360 mail-ingest
     *     wp go360 mail-ingest --env-file=/var/www/vhosts/360vo.es/.secrets/mail-ingest.env --limit=20
     */
    public static function handle(array $args, array $assoc_args): void
    {
        if (! function_exists('imap_open')) {
            \WP_CLI::error('La extensión IMAP de PHP no está disponible.');
            return;
        }

        $env_path = isset($assoc_args['env-file']) ? (string) $assoc_args['env-file'] : self::default_env_path();
        $file_config = self::load_env_file($env_path);

        $config = self::build_config($file_config);
        $limit = isset($assoc_args['limit']) ? max(1, (int) $assoc_args['limit']) : $config['poll_limit'];

        if ($config['imap_host'] === '' || $config['imap_username'] === '' || $config['imap_password'] === '') {
            \WP_CLI::error('Config IMAP incompleta: revisa host/usuario/password en el .env.');
            return;
        }

        $mailbox = self::build_mailbox_string($config);
        $imap = @imap_open($mailbox, $config['imap_username'], $config['imap_password'], 0, 1);
        if (! $imap) {
            $errors = imap_errors();
            $error_message = is_array($errors) && ! empty($errors) ? implode(' | ', $errors) : 'No se pudo abrir IMAP.';
            \WP_CLI::error('Error IMAP: ' . $error_message);
            return;
        }

        $criteria = $config['only_unseen'] ? 'UNSEEN' : 'ALL';
        $uids = imap_sort($imap, SORTDATE, 1, SE_UID, $criteria);
        if (! is_array($uids)) {
            $uids = [];
        }

        $processed = 0;
        $matched = 0;
        $created = 0;
        $skipped = 0;
        $errors_count = 0;

        foreach ($uids as $uid) {
            if ($processed >= $limit) {
                break;
            }
            $processed++;

            $overview_rows = imap_fetch_overview($imap, (string) $uid, FT_UID);
            if (! is_array($overview_rows) || empty($overview_rows)) {
                $errors_count++;
                continue;
            }
            $overview = $overview_rows[0];

            $subject = self::decode_subject((string) ($overview->subject ?? ''));
            $from = self::extract_sender_email((string) ($overview->from ?? ''));
            $message_id = trim((string) ($overview->message_id ?? ''));
            $received_at = self::normalize_received_at((string) ($overview->date ?? ''));

            $plate = self::extract_plate($subject, $config);
            if ($plate === '') {
                continue;
            }
            $matched++;

            $dedupe_key = self::build_dedupe_key($message_id, $uid, $subject, $from, $received_at);
            if (get_transient($dedupe_key)) {
                $skipped++;
                continue;
            }

            $log_id = ActivityLogger::log(self::EVENT_TYPE, [
                'level'   => 'warning',
                'source'  => $config['log_source'],
                'context' => [
                    'from_email'  => $from,
                    'subject'     => $subject,
                    'plate'       => $plate,
                    'received_at' => $received_at,
                    'message_id'  => $message_id,
                ],
            ]);

            set_transient($dedupe_key, 1, $config['dedupe_ttl']);

            if ($log_id) {
                $created++;
            } else {
                $errors_count++;
            }
        }

        imap_close($imap);

        \WP_CLI::success(sprintf(
            'Ingesta completada. Revisados: %d | Coinciden patrón: %d | Notificaciones creadas: %d | Duplicados: %d | Errores: %d',
            $processed,
            $matched,
            $created,
            $skipped,
            $errors_count
        ));
    }

    /**
     * @param array<string,string> $file_config
     * @return array<string,mixed>
     */
    private static function build_config(array $file_config): array
    {
        $read = static function (string $key, string $default = '') use ($file_config): string {
            $env_val = getenv($key);
            if (is_string($env_val) && $env_val !== '') {
                return $env_val;
            }
            return $file_config[$key] ?? $default;
        };

        return [
            'imap_host'          => trim($read('GO360_IMAP_HOST', '')),
            'imap_port'          => max(1, (int) $read('GO360_IMAP_PORT', '993')),
            'imap_encryption'    => strtolower(trim($read('GO360_IMAP_ENCRYPTION', 'ssl'))),
            'imap_validate_cert' => $read('GO360_IMAP_VALIDATE_CERT', '1') !== '0',
            'imap_username'      => trim($read('GO360_IMAP_USERNAME', '')),
            'imap_password'      => (string) $read('GO360_IMAP_PASSWORD', ''),
            'imap_mailbox'       => trim($read('GO360_IMAP_MAILBOX', 'INBOX')),
            'subject_prefix'     => trim($read('GO360_MAIL_SUBJECT_PREFIX', 'Notificación avería')),
            'subject_regex'      => trim($read('GO360_MAIL_SUBJECT_REGEX', '/^Notificaci[oó]n aver[ií]a\s+([A-Z0-9-]{5,12})$/iu')),
            'poll_limit'         => max(1, (int) $read('GO360_MAIL_POLL_LIMIT', '25')),
            'dedupe_ttl'         => max(60, (int) $read('GO360_MAIL_DEDUPE_TTL_SECONDS', '2592000')),
            'log_source'         => trim($read('GO360_MAIL_LOG_SOURCE', 'imap_ingest')),
            'only_unseen'        => true,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function load_env_file(string $path): array
    {
        $config = [];
        if ($path === '' || ! is_readable($path)) {
            return $config;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return $config;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim((string) $parts[0]);
            $value = trim((string) $parts[1]);
            if ($key === '') {
                continue;
            }
            $config[$key] = trim($value, "\"'");
        }

        return $config;
    }

    private static function default_env_path(): string
    {
        $base = dirname(untrailingslashit(ABSPATH));
        return $base . '/.secrets/mail-ingest.env';
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function build_mailbox_string(array $config): string
    {
        $host = (string) $config['imap_host'];
        $port = (int) $config['imap_port'];
        $mailbox = (string) $config['imap_mailbox'];

        $flags = '/imap';
        $enc = (string) $config['imap_encryption'];
        if ($enc === 'ssl' || $enc === 'tls') {
            $flags .= '/' . $enc;
        }
        if (empty($config['imap_validate_cert'])) {
            $flags .= '/novalidate-cert';
        }

        return sprintf('{%s:%d%s}%s', $host, $port, $flags, $mailbox);
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function extract_plate(string $subject, array $config): string
    {
        $subject = trim($subject);
        if ($subject === '') {
            return '';
        }

        $regex = (string) $config['subject_regex'];
        if ($regex !== '' && @preg_match($regex, $subject, $matches)) {
            if (! empty($matches[1])) {
                return strtoupper(sanitize_text_field((string) $matches[1]));
            }
        }

        $prefix = (string) $config['subject_prefix'];
        if ($prefix !== '' && stripos($subject, $prefix) === 0) {
            $plate = trim(substr($subject, strlen($prefix)));
            if ($plate !== '') {
                return strtoupper(sanitize_text_field($plate));
            }
        }

        return '';
    }

    private static function decode_subject(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $decoded = @imap_mime_header_decode($raw);
        if (! is_array($decoded) || empty($decoded)) {
            return sanitize_text_field($raw);
        }

        $parts = [];
        foreach ($decoded as $part) {
            $text = isset($part->text) ? (string) $part->text : '';
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return sanitize_text_field(trim(implode('', $parts)));
    }

    private static function extract_sender_email(string $from): string
    {
        if ($from === '') {
            return '';
        }
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            return sanitize_email((string) $m[1]);
        }
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $from, $m)) {
            return sanitize_email((string) $m[0]);
        }
        return sanitize_email($from);
    }

    private static function normalize_received_at(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return current_time('mysql');
        }
        return wp_date('Y-m-d H:i:s', $timestamp, wp_timezone());
    }

    private static function build_dedupe_key(string $message_id, $uid, string $subject, string $from, string $received_at): string
    {
        $fingerprint = $message_id !== ''
            ? $message_id
            : ((string) $uid . '|' . $subject . '|' . $from . '|' . $received_at);

        return 'go360_mail_ingest_' . md5($fingerprint);
    }
}

