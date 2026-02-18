<?php

namespace App\SubscriptionBilling\Operations;

use App\Core\Database\TransactionManager;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;

class PauseSubscription
{
    use ModifySubscriptionTrait;

    public function __construct(private TransactionManager $transaction)
    {
    }

    /**
     * Pauses a subscription.
     *
     * @throws OperationException
     */
    public function pause(Subscription $subscription): void
    {
        if ($subscription->paused) {
            throw new OperationException('This subscription is already paused.');
        }

        $subscription->paused = true;
        $this->setStatus($subscription);

        $this->transaction->perform(function () use ($subscription) {
            if (!$subscription->save()) {
                if (count($subscription->getErrors()) > 0) {
                    throw new OperationException($subscription->getErrors());
                }

                throw new OperationException('There was an error pausing the subscription.');
            }
        });
    }
}
