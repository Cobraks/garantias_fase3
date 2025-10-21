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
    /**
     * @return array{sent:int,failed:int,failures:array<int,string>}
     */
    public function dispatch(int $user_id, array $payload): array
    {
        $result = [
            'sent'     => 0,
            'failed'   => 0,
            'failures' => [],
        ];

        if ($user_id <= 0) {
            return $result;
        }

        $subscriptions = $this->subscriptions->get_user_subscriptions($user_id);
        if (empty($subscriptions)) {
            return $result;
        }

        foreach ($subscriptions as $subscription) {
            $success = $this->client->send($subscription, $payload);
            if ($success) {
                $this->subscriptions->mark_success((string) $subscription['endpoint']);
                $result['sent']++;
            } else {
                $this->subscriptions->mark_failure((string) $subscription['endpoint']);
                $result['failed']++;
                $result['failures'][] = (string) $subscription['endpoint'];
            }
        }

        return $result;
    }

    /**
     * @return array<int>
     */
    public function get_admin_user_ids_with_subscriptions(): array
    {
        $records = $this->subscriptions->get_admin_subscriptions();
        if (empty($records)) {
            return [];
        }

        $user_ids = array_map(
            static function ($record) {
                return isset($record['user_id']) ? (int) $record['user_id'] : 0;
            },
            $records
        );

        $filtered = array_filter($user_ids, static function ($id) {
            return $id > 0;
        });

        return array_values(array_unique($filtered));
    }
}
