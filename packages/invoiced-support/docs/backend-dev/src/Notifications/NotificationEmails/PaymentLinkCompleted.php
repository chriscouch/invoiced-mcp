<?php

namespace App\Notifications\NotificationEmails;

use App\AccountsReceivable\Models\PaymentLinkSession;
use Doctrine\DBAL\ArrayParameterType;

class PaymentLinkCompleted extends AbstractNotificationEmail
{
    private const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Payment link completed';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > self::THRESHOLD) {
            return 'notifications/payment-link-completed-bulk';
        }

        return 'notifications/payment-link-completed';
    }

    public function getVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);

        if (count($events) > self::THRESHOLD) {
            $qb = $this->database->createQueryBuilder();
            $res = $qb->select('count(*) as cnt')
                ->from('PaymentLinkSessions')
                ->where($qb->expr()->in('id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();

            return [
                'sessions' => $res,
            ];
        }

        $items = PaymentLinkSession::where('id IN ('.implode(',', $ids).')')
            ->sort('id')
            ->execute();

        $items = array_map(function (PaymentLinkSession $item) {
            $res = $item->toArray();
            $res['customer'] = $item->customer?->toArray();
            $res['payment_link'] = $item->payment_link->toArray();

            return $res;
        }, $items);

        return [
            'sessions' => $items,
        ];
    }
}
