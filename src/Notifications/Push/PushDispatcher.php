<?php

namespace GarantiasOnline360VO\Notifications\Push;

if (! defined('ABSPATH')) {
    exit;
}

class PushDispatcher
{
    /** @var PushSubscriptionRepository */
    private $subscriptions;

    /** @var WebPushClient */
    private $client;

    public function __construct(
        ?PushSubscriptionRepository $subscriptions = null,
        ?WebPushClient $client = null
    ) {
        $this->subscriptions = $subscriptions ?: new PushSubscriptionRepository();
        $this->client = $client ?: new WebPushClient();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(int $user_id, array $payload): void
    {
        if ($user_id <= 0) {
            return;
        }

        $subscriptions = $this->subscriptions->get_user_subscriptions($user_id);
        if (empty($subscriptions)) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            $success = $this->client->send($subscription, $payload);
            if ($success) {
                $this->subscriptions->mark_success((string) $subscription['endpoint']);
            } else {
                $this->subscriptions->mark_failure((string) $subscription['endpoint']);
            }
        }
    }
}
