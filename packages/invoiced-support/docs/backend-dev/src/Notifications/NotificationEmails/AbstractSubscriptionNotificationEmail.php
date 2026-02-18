<?php

namespace App\Notifications\NotificationEmails;

use App\SubscriptionBilling\Models\Subscription;

abstract class AbstractSubscriptionNotificationEmail extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        /** @var Subscription[] $items */
        $items = Subscription::where('id IN ('.implode(',', $ids).')')
            ->with('plan')
            ->with('customer')
            ->sort('id')->execute();

        $items = array_map(function (Subscription $item) {
            $res = $item->toArray();
            $res['customer'] = $item->relation('customer')->toArray();

            return $res;
        }, $items);

        return [
            'subscriptions' => $items,
        ];
    }
}
