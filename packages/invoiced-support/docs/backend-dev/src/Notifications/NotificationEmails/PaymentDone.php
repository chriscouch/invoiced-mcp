<?php

namespace App\Notifications\NotificationEmails;

use App\CashApplication\Models\Payment;

class PaymentDone extends AbstractPaymentNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New payment received';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/payment-bulk';
        }

        return 'notifications/payment';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return $this->getBulkVariables($events);
        }

        $ids = $this->getObjectIds($events);
        $items = Payment::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->sort('id')->execute();

        $items = array_map(function (Payment $item) {
            $res = $item->toArray();
            $res['customer'] = $item->relation('customer')->toArray();

            return $res;
        }, $items);

        return [
            'payments' => $items,
        ];
    }
}
