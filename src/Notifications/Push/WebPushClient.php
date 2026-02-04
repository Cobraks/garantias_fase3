<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class WebPushClient
{
    /**
     * @param array<string, mixed> $subscription
     * @param array<string, mixed> $payload
     */
    public function send(array $subscription, array $payload): bool
    {
        // Placeholder implementation. The actual HTTP push delivery requires
        // encrypted payloads and VAPID authentication. Here we log the attempt
        // so we can hook in a fully-fledged client later without breaking the
        // control flow of the plugin.
        do_action('go360/push/log', 'dispatch_attempt', [
            'endpoint' => $subscription['endpoint'] ?? '',
            'payload'  => $payload,
        ]);

        return true;
    }
}
