<?php

namespace App\Notifications\NotificationEmails;

use App\AccountsReceivable\Models\Invoice;

class InvoiceViewed extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Invoice was viewed in customer portal';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/invoice-viewed-bulk';
        }

        return 'notifications/invoice-viewed';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        $items = Invoice::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->sort('id')->execute();

        $items = array_map(function (Invoice $item) {
            $res = $item->toArray();
            $res['customer'] = $item->relation('customer')->toArray();

            return $res;
        }, $items);

        return [
            'invoices' => $items,
        ];
    }
}
