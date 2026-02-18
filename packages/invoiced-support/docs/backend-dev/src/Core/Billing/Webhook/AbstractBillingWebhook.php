<?php

namespace App\Core\Billing\Webhook;

use App\Companies\Models\Company;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Mailer\Mailer;
use ICanBoogie\Inflector;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractBillingWebhook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    const ERROR_GENERIC = 'error';
    const ERROR_INVALID_EVENT = 'invalid_event';
    const ERROR_EVENT_NOT_SUPPORTED = 'event_not_supported';
    const ERROR_CUSTOMER_NOT_FOUND = 'customer_not_found';
    const SUCCESS = 'OK';

    private array $companies;

    public function __construct(
        protected Mailer $mailer,
    ) {
    }

    /**
     * Takes event and returns method handler name based on event type
     * i.e. customer.subscription.created -> handleCustomerSubscriptionCreated.
     */
    public function getHandleMethod(object $event): string
    {
        $inflector = Inflector::get();
        $method = str_replace('.', '_', $event->type);

        return 'handle'.$inflector->camelize($method);
    }

    protected function performCancellation(Company $company, string $reason): void
    {
        // do not send if already canceled
        if ($company->canceled) {
            return;
        }

        $company->canceled = true;
        $company->canceled_at = time();
        if ($reason) {
            $company->canceled_reason = $reason;
        }

        // Only set canceled_reason if it has not been set yet because
        // it could have already been set by CancelSubscriptionAction if
        // atPeriodEnd = false
        if (!$company->canceled_reason) {
            $company->canceled_reason = 'unspecified';
        }

        $company->save();

        $this->mailer->sendToAdministrators(
            $company,
            [
                'subject' => 'Your Invoiced account has been canceled',
            ],
            'subscription-canceled',
            [
                'company' => $company->name,
            ],
        );
    }

    /**
     * This function tells the controller to process the event.
     */
    abstract public function handle(array $event): string;

    /**
     * @return Company[]
     */
    protected function getCompaniesForBillingProfile(BillingProfile $billingProfile): array
    {
        if (isset($this->companies)) {
            $companies = $this->companies;
            unset($this->companies);

            return $companies;
        }

        return Company::where('billing_profile_id', $billingProfile)->all()->toArray();
    }

    /**
     * Used for testing.
     */
    public function setCompanies(array $companies): void
    {
        $this->companies = $companies;
    }
}
