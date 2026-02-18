<?php

namespace App\Notifications\NotificationEmails;

use App\AccountsReceivable\Models\Customer;
use Doctrine\DBAL\ArrayParameterType;

class SignUpPageCompleted extends AbstractNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Sign up page completed';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/sign-up-page-completed-bulk';
        }

        return 'notifications/sign-up-page-completed';
    }

    public function getVariables(array $events): array
    {
        $ids = $this->getObjectIds($events);

        if (count($events) > static::THRESHOLD) {
            $qb = $this->database->createQueryBuilder();
            $res = $qb->select('count(*) as cnt')
                ->from('Customers')
                ->where($qb->expr()->in('id', ':ids'))
                ->setParameter('ids', $ids, ArrayParameterType::INTEGER)
                ->fetchAllAssociative();

            return [
                'customers' => $res,
            ];
        }

        $items = Customer::where('id IN ('.implode(',', $ids).')')
            ->sort('id')
            ->execute();

        $items = array_map(function (Customer $item) {
            $res = $item->toArray();
            $res['sign_up_page'] = null;
            if ($signUpPage = $item->signUpPage()) {
                $res['sign_up_page'] = $signUpPage->toArray();
            }

            return $res;
        }, $items);

        return [
            'customers' => $items,
        ];
    }
}
