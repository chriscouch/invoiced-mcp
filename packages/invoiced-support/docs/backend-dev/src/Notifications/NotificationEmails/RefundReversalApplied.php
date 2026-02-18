<?php

namespace App\Notifications\NotificationEmails;
use App\PaymentProcessing\Models\Refund;
use Doctrine\DBAL\ArrayParameterType;

class RefundReversalApplied extends AbstractNotificationEmail
{
    const THRESHOLD = 3;
    
    protected function getSubject(): string
    {
        return 'Refund Reversal Applied';
    }
    
    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/refund-reversal-applied-bulk';
        }
        
        return 'notifications/refund-reversal-applied';
    }
    
    public function getVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);
        
        if (count($events) > static::THRESHOLD) {
            $qb = $this->database->createQueryBuilder();
            $res = $qb->select('count(*) as cnt')
                ->from('Refunds')
                ->where($qb->expr()->in('id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();
            
            return [
                'refunds' => $res,
            ];
        }
        
        $items = Refund::where('id', $ids)
            ->sort('id')
            ->execute();
        
        $items = array_map(function (Refund $item) {
            return $item->toArray();
        }, $items);
        
        return [
            'refunds' => $items,
        ];
    }
}
