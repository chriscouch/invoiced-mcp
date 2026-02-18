<?php

namespace App\Notifications\NotificationEmails;

use App\PaymentPlans\Models\PaymentPlan;

class PaymentPlanApproved extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Payment plan was approved';
    }

    public function getVariables(array $events): array
    {
        if (count($events) > static::THRESHOLD) {
            return [
                'count' => count($events),
            ];
        }

        $ids = $this->getObjectIds($events);
        $items = PaymentPlan::where('id IN ('.implode(',', $ids).')')
            ->with('invoice_id')
            ->sort('id')
            ->execute();

        $items = array_map(function (PaymentPlan $item) {
            $res = $item->toArray();
            $res['invoice'] = $item->relation('invoice_id')->toArray();

            return $res;
        }, $items);

        return [
            'plans' => $items,
        ];
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/plan-approved-bulk';
        }

        return 'notifications/plan-approved';
    }
}
