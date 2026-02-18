<?php

namespace App\Integrations\ChartMogul\Syncs;

use App\Core\Utils\ModelUtility;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Models\Event;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;
use ChartMogul\Exceptions\ChartMogulException;
use ChartMogul\Resource\Collection;
use ChartMogul\Subscription as ChartMogulSubscription;
use App\Core\Utils\InfuseUtility as Utility;

class CancellationSync extends AbstractSync
{
    public static function getDefaultPriority(): int
    {
        return 0;
    }

    public function sync(ChartMogulAccount $account): void
    {
        $this->syncCancellations($account);
        $this->syncFinishedSubscriptions($account);

        // Clear cache once complete
        $this->clearCache();
    }

    /**
     * Syncs subscription cancellations using the event log.
     */
    private function syncCancellations(ChartMogulAccount $account): void
    {
        $this->logger->info('Syncing subscription cancellations to ChartMogul');

        // Load cancellation events from Invoiced
        $query = Event::where('type', EventType::SubscriptionCanceled->value)
            ->where('timestamp', $account->sync_cursor, '>=');
        $events = ModelUtility::getAllModelsGenerator($query);
        foreach ($events as $event) {
            try {
                $this->syncCancellation($event);
            } catch (ChartMogulException $e) {
                throw new SyncException('Subscription # '.$event->object_id.': '.$e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    private function syncCancellation(Event $event): void
    {
        $subscriptionId = (int) $event->object_id;
        $customerId = $event->getAssociations()['customer'] ?? 0;
        $subscriptionUuids = $this->findSubscriptions($customerId, $subscriptionId);

        foreach ($subscriptionUuids as $subscriptionUuid) {
            $this->cancelSubscription($subscriptionUuid, CarbonImmutable::createFromTimestamp($event->timestamp));
        }
    }

    /**
     * Syncs finished subscriptions as canceled subscriptions.
     */
    private function syncFinishedSubscriptions(ChartMogulAccount $account): void
    {
        $this->logger->info('Syncing finished subscriptions to ChartMogul');

        // Load subscriptions from Invoiced
        $query = Subscription::where('finished', true)
            ->where('updated_at', Utility::unixToDb($account->sync_cursor), '>');
        $subscriptions = ModelUtility::getAllModelsGenerator($query);
        foreach ($subscriptions as $subscription) {
            try {
                $this->syncFinishedSubscription($subscription);
            } catch (ChartMogulException $e) {
                throw new SyncException('Subscription # '.$subscription->id().': '.$e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    private function syncFinishedSubscription(Subscription $subscription): void
    {
        $subscriptionUuids = $this->findSubscriptions($subscription->customer, $subscription->id);
        $cancelDate = $this->getFinishedDate($subscription);
        if (!$cancelDate) {
            return;
        }

        foreach ($subscriptionUuids as $subscriptionUuid) {
            $this->cancelSubscription($subscriptionUuid, $cancelDate);
        }
    }

    /**
     * Finds matching subscriptions on ChartMogul. There can be
     * more than one subscription for a single Invoiced subscription
     * if there are addons.
     */
    private function findSubscriptions(int $customerId, int $subscriptionId): array
    {
        // retrieve the customer
        $customer = $this->lookupCustomer($customerId);
        if (!$customer) {
            return [];
        }

        // retrieve subscriptions for the customer
        /** @var Collection $result */
        $result = ChartMogulSubscription::all([
            'customer_uuid' => $customer->uuid,
        ]);

        // find the matching subscriptions in the list
        $uuids = [];
        foreach ($result as $subscription) {
            if ($subscription->subscription_set_external_id == $subscriptionId) {
                $uuids[] = $subscription->uuid;
            }
        }

        return $uuids;
    }

    /**
     * Gets the data a subscription was finished.
     */
    private function getFinishedDate(Subscription $subscription): ?CarbonImmutable
    {
        return $subscription->billingPeriods()->endDate();
    }

    /**
     * Cancels a subscription on ChartMogul.
     */
    private function cancelSubscription(string $uuid, CarbonImmutable $cancelDate): void
    {
        $chartMogulSubscription = new ChartMogulSubscription(['uuid' => $uuid]);
        $chartMogulSubscription->cancel((string) $this->datetimeToDate($cancelDate));
    }
}
