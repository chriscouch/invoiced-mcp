<?php

namespace App\SubscriptionBilling\Libs;

use App\Companies\Models\Company;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;

class BilledSoonNotifier
{
    public CarbonImmutable $start;
    public CarbonImmutable $end;

    public function __construct(public Company $company, public int $days)
    {
        $this->start = (new CarbonImmutable('+'.$days.' days'))->setTime(0, 0);
        $this->end = (new CarbonImmutable('+'.$days.' days'))->setTime(23, 59, 59);
    }

    /**
     * Gets the company.
     */
    public function getCompany(): Company
    {
        return $this->company;
    }

    /**
     * Gets the # of days in advance to notify about subscriptions.
     */
    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * Gets the beginning timestamp of the notification window.
     */
    public function getStart(): CarbonImmutable
    {
        return $this->start;
    }

    /**
     * Gets the ending timestamp of the notification window.
     */
    public function getEnd(): CarbonImmutable
    {
        return $this->end;
    }

    /**
     * Gets all the subscriptions within the notifier time window.
     *
     * @return Subscription[]
     */
    public function getSubscriptions()
    {
        /* @phpstan-ignore-next-line */
        return Subscription::queryWithTenant($this->company)
            ->where('paused', false)
            ->where('renews_next', $this->start->getTimestamp(), '>=')
            ->where('renews_next', $this->end->getTimestamp(), '<=')
            ->where('cancel_at_period_end', false)
            ->all();
    }
}
