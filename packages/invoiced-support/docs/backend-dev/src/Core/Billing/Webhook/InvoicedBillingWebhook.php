<?php

namespace App\Core\Billing\Webhook;

use App\Companies\Models\CompanyNote;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use Exception;
use Invoiced\Client;
use Invoiced\Collection;
use Invoiced\Task;
use Throwable;

class InvoicedBillingWebhook extends AbstractBillingWebhook
{
    public function __construct(
        Mailer $mailer,
        private Client $client,
        private TenantContext $tenant,
    ) {
        parent::__construct($mailer);
    }

    /**
     * This function tells the controller to process the Invoiced event.
     */
    public function handle(array $event): string
    {
        if (!isset($event['id'])) {
            return self::ERROR_INVALID_EVENT;
        }

        try {
            // convert event (multidimensional array) to object
            $event = json_decode((string) json_encode($event));

            // get the object attached to the event
            $eventData = $event->data->object;

            // find out which user this event is for by cross-referencing the customer id
            if (str_starts_with($event->type, 'customer.')) {
                $invoicedCustomerId = $eventData->id;
            } else {
                $invoicedCustomerId = $eventData->customer->id;
            }

            if (!$invoicedCustomerId) {
                return self::ERROR_CUSTOMER_NOT_FOUND;
            }

            $billingProfile = BillingProfile::where('billing_system', 'invoiced')
                ->where('invoiced_customer', $invoicedCustomerId)
                ->oneOrNull();

            if (!$billingProfile) {
                return self::ERROR_CUSTOMER_NOT_FOUND;
            }

            $method = $this->getHandleMethod($event);
            if (!method_exists($this, $method)) {
                return self::ERROR_EVENT_NOT_SUPPORTED;
            }

            $this->$method($eventData, $billingProfile);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->logger->error($e);
        }

        return self::ERROR_GENERIC;
    }

    /**
     * Handles customer.updated event.
     */
    public function handleCustomerUpdated(object $eventData, BillingProfile $billingProfile): void
    {
        $billingProfile->name = $eventData->name;
        $billingProfile->saveOrFail();
    }

    /**
     * Handles subscription.updated event.
     */
    public function handleSubscriptionUpdated(object $eventData, BillingProfile $billingProfile): void
    {
        $billingProfile->past_due = 'past_due' == $eventData->status;
        $billingProfile->save();
    }

    /**
     * Handles subscription.deleted event.
     */
    public function handleSubscriptionDeleted(object $eventData, BillingProfile $billingProfile): void
    {
        // Do not cancel the account if the user still has at least 1 active subscription
        /** @var Collection $metadata */
        [, $metadata] = $this->client->Subscription->all([
            'filter[customer]' => $billingProfile->invoiced_customer,
        ]);

        if ($metadata->total_count > 0) {
            return;
        }

        $canceledReason = $eventData->canceled_reason ?: '';
        $companies = $this->getCompaniesForBillingProfile($billingProfile);
        foreach ($companies as $company) {
            $this->tenant->runAs($company, function () use ($company, $canceledReason) {
                $this->performCancellation($company, $canceledReason);
            });
        }
    }

    /**
     * Handles task.created event.
     */
    public function handleTaskCreated(object $eventData, BillingProfile $billingProfile): void
    {
        // If a "Shut off service" task is created this will automatically cancel any associated accounts
        if ('Shut off service' == $eventData->name) {
            $companies = $this->getCompaniesForBillingProfile($billingProfile);
            foreach ($companies as $company) {
                $this->tenant->runAs($company, function () use ($company) {
                    $this->performCancellation($company, 'nonpayment');

                    // Create a company note
                    $note = new CompanyNote();
                    $note->tenant_id = $company->id;
                    $note->note = 'This account has been canceled automatically due to non-payment. The subscription is still active in the billing system. The account can be reactivated in the admin panel within 90 days after any outstanding balance is paid.';
                    $note->created_by = 'System';
                    $note->save();
                });
            }

            // Mark the task as complete on Invoiced
            /** @var Task $task */
            $task = $this->client->Task->retrieve($eventData->id);
            $task->complete = true;
            $task->save();
        }
    }

    /**
     * Used for testing.
     */
    public function handleTest(object $eventData, BillingProfile $billingProfile): void
    {
    }

    /**
     * Used for testing.
     */
    public function handleTestError(object $eventData, BillingProfile $billingProfile): never
    {
        throw new Exception('');
    }
}
