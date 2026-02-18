<?php

namespace App\Notifications\NotificationEmails;

use App\CashApplication\Models\Payment;

class LockboxCheckReceived extends AbstractPaymentNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New check received in lockbox';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/lockbox-received-bulk';
        }

        return 'notifications/lockbox-received';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return $this->getBulkVariables($events);
        }

        $ids = $this->getObjectIds($events);
        $items = Payment::where('id IN ('.implode(',', $ids).')')->sort('id')->execute();

        return [
            'payments' => array_map(fn ($item) => $item->toArray(), $items),
        ];
    }
}
