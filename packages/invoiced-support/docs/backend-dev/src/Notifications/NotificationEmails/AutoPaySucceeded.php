<?php

namespace App\Notifications\NotificationEmails;

use App\PaymentProcessing\Models\Charge;
use Doctrine\DBAL\ArrayParameterType;
use InvalidArgumentException;

class AutoPaySucceeded extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'New AutoPay payment received';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/autopay-succeeded-bulk';
        }

        return 'notifications/autopay-succeeded';
    }

    public function getVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);

        if (count($events) > static::THRESHOLD) {
            $qb = $this->database->createQueryBuilder();
            $res = $qb->select('count(*) as cnt, sum(amount) as amount, currency')
                ->from('Charges')
                ->where($qb->expr()->in('id', ':ids'))
                ->groupBy('currency')
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();

            return [
                'charges' => $res,
            ];
        }

        $items = Charge::where('id IN ('.implode(',', $ids).')')
            ->with('customer')
            ->sort('id')
            ->execute();

        $items = array_map(function (Charge $item) use (&$customer) {
            $res = $item->toArray();
            $customer = $item->customer;
            if (!$customer) {
                throw new InvalidArgumentException('Customer should be always set');
            }
            $res['customer'] = $customer->toArray();

            return $res;
        }, $items);

        return [
            'charges' => $items,
        ];
    }
}
