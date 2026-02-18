<?php

namespace App\Notifications\NotificationEmails;

use App\AccountsReceivable\Models\Estimate;

abstract class AbstractEstimateNotificationEmail extends AbstractNotificationEmail
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
        $items = Estimate::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->sort('id')->execute();

        $items = array_map(function (Estimate $item) {
            $res = $item->toArray();
            $res['customer'] = $item->relation('customer')->toArray();

            return $res;
        }, $items);

        return [
            'estimates' => $items,
        ];
    }
}
