<?php

namespace App\Notifications\NotificationEmails;

use App\Chasing\Models\PromiseToPay;
use Doctrine\DBAL\ArrayParameterType;

class PromiseCreated extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New promise-to-pay';
    }

    public function getVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);

        if (count($events) > static::THRESHOLD) {
            $qb = $this->database->createQueryBuilder();
            $res = $qb->select('count(*) as cnt, sum(amount) as amount, currency')
                ->from('ExpectedPaymentDates')
                ->where($qb->expr()->in('id', ':ids'))
                ->groupBy('currency')
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();

            return [
                'promises' => $res,
            ];
        }

        $items = PromiseToPay::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->with('invoice')
            ->sort('id')->execute();

        $items = array_map(function (PromiseToPay $item) {
            $res = $item->toArray();
            $res['customer'] = $item->customer->toArray();
            $res['invoice'] = $item->invoice->toArray();

            return $res;
        }, $items);

        return [
            'promises' => $items,
        ];
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/promise-bulk';
        }

        return 'notifications/promise';
    }
}
