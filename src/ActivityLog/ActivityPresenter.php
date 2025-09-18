<?php

namespace GarantiasOnline360VO\ActivityLog;

use function __;
use function array_filter;
use function array_map;
use function array_values;
use function trim;

if (! defined('ABSPATH')) {
    exit;
}

class ActivityPresenter
{
    /**
     * @param array<string, mixed> $item
     * @return array{title: string, lines: array<int, string>}
     */
    public static function format(array $item): array
    {
        $title = (string) ($item['event_label'] ?? ($item['event_type'] ?? ''));
        $context = is_array($item['context'] ?? null) ? $item['context'] : [];
        $actor = is_array($item['actor'] ?? null) ? $item['actor'] : [];
        $lines = [];

        switch ($item['event_type'] ?? '') {
            case 'guarantee.contracted':
                $lines = self::formatGuaranteeContracted($context, $actor);
                break;
            case 'guarantee.created':
                $lines = self::formatGuaranteeCreated($context, $actor);
                break;
            case 'email.sent':
                $lines = self::formatEmailSent($context);
                break;
            case 'payment.recorded':
                $lines = self::formatPaymentRecorded($context);
                break;
            default:
                $message = isset($item['message']) ? (string) $item['message'] : '';
                if ($message !== '') {
                    $lines[] = $message;
                }
                break;
        }

        $clean = array_values(array_filter(array_map([self::class, 'cleanLine'], $lines)));

        return [
            'title' => $title,
            'lines' => $clean,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $actor
     * @return array<int, string>
     */
    private static function formatGuaranteeContracted(array $context, array $actor): array
    {
        $lines = [];
        $lines[] = self::vehicleLine($context);
        $lines[] = self::vendorLine($context, $actor);

        if (! empty($context['current_state_label'])) {
            $lines[] = sprintf(
                __('Estado actual: %s', 'garantias-online-360vo'),
                (string) $context['current_state_label']
            );
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $actor
     * @return array<int, string>
     */
    private static function formatGuaranteeCreated(array $context, array $actor): array
    {
        $initiator = $context['initiator_label'] ?? ($actor['name'] ?? '');
        if ($initiator === '') {
            $initiator = __('Usuario sin identificar', 'garantias-online-360vo');
        }

        $lines = [
            sprintf(
                __('%s ha iniciado una nueva garantía.', 'garantias-online-360vo'),
                $initiator
            ),
        ];

        $vehicle = self::vehicleLine($context);
        if ($vehicle !== '') {
            $lines[] = $vehicle;
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private static function formatEmailSent(array $context): array
    {
        $recipient = (string) ($context['email_primary_label'] ?? '');
        if ($recipient === '') {
            $recipient = __('destinatarios', 'garantias-online-360vo');
        }

        $subject = (string) ($context['email_template_label'] ?? '');
        if ($subject === '' && ! empty($context['guarantee_label'])) {
            $subject = sprintf(
                __('Garantía %s', 'garantias-online-360vo'),
                (string) $context['guarantee_label']
            );
        }

        $lines = [
            sprintf(
                __('Correo enviado a %1$s (%2$s).', 'garantias-online-360vo'),
                $recipient,
                $subject
            ),
        ];

        if (! empty($context['email_to'])) {
            $lines[] = sprintf(
                __('Destinatarios: %s', 'garantias-online-360vo'),
                (string) $context['email_to']
            );
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private static function formatPaymentRecorded(array $context): array
    {
        $actor = (string) ($context['payment_actor_label'] ?? '');
        $method = (string) ($context['payment_method_label'] ?? '');
        $guarantee = (string) ($context['guarantee_label'] ?? '');

        $lines = [];
        if ($method !== '') {
            if ($actor !== '') {
                $lines[] = sprintf(
                    __('%1$s por %2$s.', 'garantias-online-360vo'),
                    $method,
                    $actor
                );
            } else {
                $lines[] = $method;
            }
        }

        if ($guarantee !== '') {
            $lines[] = sprintf(
                __('Garantía %s', 'garantias-online-360vo'),
                $guarantee
            );
        }

        if (! empty($context['current_state_label'])) {
            $lines[] = sprintf(
                __('Estado actual: %s', 'garantias-online-360vo'),
                (string) $context['current_state_label']
            );
        }

        return $lines;
    }

    private static function vehicleLine(array $context): string
    {
        $vehicle = (string) ($context['vehicle_label'] ?? '');
        $plate = (string) ($context['vehicle_plate'] ?? '');
        $guarantee = (string) ($context['guarantee_label'] ?? '');

        if ($vehicle === '' && $guarantee !== '') {
            $vehicle = $guarantee;
        }

        if ($vehicle === '' && $plate === '') {
            return '';
        }

        if ($vehicle !== '' && $plate !== '') {
            return sprintf(
                __('%1$s con matrícula %2$s', 'garantias-online-360vo'),
                $vehicle,
                $plate
            );
        }

        return $vehicle !== '' ? $vehicle : $plate;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $actor
     */
    private static function vendorLine(array $context, array $actor): string
    {
        $vendor = (string) ($context['vendor_name'] ?? '');
        if ($vendor === '' && ! empty($context['initiator_label'])) {
            $vendor = (string) $context['initiator_label'];
        }
        if ($vendor === '' && ! empty($actor['name'])) {
            $vendor = (string) $actor['name'];
        }

        if ($vendor === '') {
            return '';
        }

        $channel = (string) ($context['channel_label'] ?? '');
        $channelPart = $channel !== '' ? ' (' . $channel . ')' : '';

        return sprintf(
            __('por %1$s%2$s.', 'garantias-online-360vo'),
            $vendor,
            $channelPart
        );
    }

    private static function cleanLine($line): string
    {
        return trim((string) $line);
    }
}
