<?php

namespace App\Notifications\NotificationEmails;

use App\Notifications\Models\NotificationEvent;
use Doctrine\DBAL\ArrayParameterType;

abstract class AbstractPaymentNotificationEmail extends AbstractNotificationEmail
{
    /**
     * @param NotificationEvent[] $events
     */
    protected function getBulkVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);
        $qb = $this->database->createQueryBuilder();
        $res = $qb->select('count(*) as cnt, sum(amount) as amount, currency')
            ->from('Payments')
            ->where($qb->expr()->in('id', ':ids'))
            ->groupBy('currency')
            ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
            ->fetchAllAssociative();

        return [
            'payments' => $res,
        ];
    }
}
