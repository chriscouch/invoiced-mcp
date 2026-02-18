<?php

namespace App\Core\Billing\Webhook;

use App\Companies\Libs\MarkCompanyFraudulent;
use App\Core\Billing\Action\CancelSubscriptionAction;
use App\Core\Billing\Disputes\StripeDisputeHandler;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Stripe\HasStripeClientTrait;
use Exception;
use Stripe\Charge;
use Stripe\Dispute;
use Stripe\Event;
use Stripe\Exception\ExceptionInterface as StripeError;
use Stripe\Invoice;

class StripeBillingWebhook extends AbstractBillingWebhook
{
    use HasStripeClientTrait;

    const ERROR_LIVEMODE_MISMATCH = 'livemode_mismatch';
    const ERROR_STRIPE_CONNECT_EVENT = 'stripe_connect_event';

    public function __construct(
        private TenantContext $tenant,
        string $stripeBillingSecret,
        private string $environment,
        private MarkCompanyFraudulent $fraudCommand,
        Mailer $mailer,
        private CancelSubscriptionAction $cancelAction,
        private StripeDisputeHandler $disputeDocumentation
    ) {
        parent::__construct($mailer);
        $this->stripeSecret = $stripeBillingSecret;
    }

    /**
     * This function tells the controller to process the Stripe event.
     */
    public function handle(array $event): string
    {
        if (!isset($event['id'])) {
            return self::ERROR_INVALID_EVENT;
        }

        // check that the livemode matches our development state
        if (!($event['livemode'] && 'production' === $this->environment ||
            !$event['livemode'] && 'production' !== $this->environment)) {
            return self::ERROR_LIVEMODE_MISMATCH;
        }

        if (isset($event['user_id'])) {
            return self::ERROR_STRIPE_CONNECT_EVENT;
        }
        try {
            $stripe = $this->getStripe();
            // retreive the event, unless it is a deauth event
            // since those cannot be retrieved
            $event = ('account.application.deauthorized' == $event['type']) ?
                (object) $event :
                $stripe->events->retrieve($event['id']);

            // get the data attached to the event
            $eventData = $event->data->object;

            // find out which user this event is for by cross-referencing the customer id
            $stripeCustomerId = $eventData->customer;
            if (!$stripeCustomerId && $chargeId = $eventData->charge) {
                $charge = $stripe->charges->retrieve($chargeId);
                $stripeCustomerId = $charge->customer;
            }

            if (!$stripeCustomerId) {
                return self::ERROR_CUSTOMER_NOT_FOUND;
            }

            $billingProfile = BillingProfile::where('billing_system', 'stripe')
                ->where('stripe_customer', $stripeCustomerId)
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
        } catch (StripeError $e) {
            $this->logger->error($e);
        }

        return self::ERROR_GENERIC;
    }

    /**
     * Handles charge.failed.
     */
    public function handleChargeFailed(object $eventData, BillingProfile $billingProfile): void
    {
        // if a charge failed as fraudulent through stripe then the account should be canceled
        // and marked as fraudulent on our system
        $companies = $this->getCompaniesForBillingProfile($billingProfile);
        foreach ($companies as $company) {
            $this->tenant->runAs($company, function () use ($eventData, $company) {
                if ($this->isChargeFraudulent($eventData)) {
                    $this->fraudCommand->markFraud($company);

                    return;
                }

                // currently only handle card charges
                if ('card' != $eventData->source->object) {
                    return;
                }

                // email member about the failure
                $this->mailer->sendToAdministrators(
                    $company,
                    [
                        'subject' => 'Action Required: Payment failed',
                    ],
                    'payment-problem',
                    [
                        'company' => $company->name,
                        'timestamp' => $eventData->created,
                        'payment_time' => date('F j, Y g:i a T', $eventData->created),
                        'amount' => number_format($eventData->amount / 100, 2),
                        'description' => $eventData->description ?: 'Invoiced Subscription',
                        'card_last4' => $eventData->source->last4,
                        'card_expires' => $eventData->source->exp_month.'/'.$eventData->source->exp_year,
                        'card_type' => $eventData->source->brand,
                        'error_message' => $eventData->failure_message,
                    ],
                );
            });
        }
    }

    /**
     * Handles customer.subscription.created.
     */
    public function handleCustomerSubscriptionCreated(object $eventData, BillingProfile $billingProfile): void
    {
        $this->handleCustomerSubscriptionUpdated($eventData, $billingProfile);
    }

    /**
     * Handles customer.subscription.updated.
     */
    public function handleCustomerSubscriptionUpdated(object $eventData, BillingProfile $billingProfile): void
    {
        $billingProfile->past_due = 'past_due' == $eventData->status;
        $billingProfile->save();
    }

    /**
     * Handles customer.subscription.deleted.
     */
    public function handleCustomerSubscriptionDeleted(object $eventData, BillingProfile $billingProfile): void
    {
        $companies = $this->getCompaniesForBillingProfile($billingProfile);
        $canceledReason = $eventData->cancellation_details->reason ?: '';
        foreach ($companies as $company) {
            $this->tenant->runAs($company, function () use ($company, $canceledReason) {
                $this->performCancellation($company, $canceledReason);
            });
        }
    }

    /**
     * Handles invoice.updated.
     */
    public function handleInvoiceUpdated(object $eventData, BillingProfile $billingProfile): void
    {
        // check if all payment attempts are exhausted; if so, void invoice on Stripe
        if (!$eventData->auto_advance && 'open' == $eventData->status) {
            $stripe = $this->getStripe();
            $invoice = $stripe->invoices->retrieve($eventData->id);
            $invoice->voidInvoice();
        }
    }

    /**
     * Handles charge.dispute.created.
     */
    public function handleChargeDisputeCreated(object $eventData, BillingProfile $billingProfile): void
    {
        // send in the evidence
        $stripe = $this->getStripe();
        $dispute = $stripe->disputes->retrieve($eventData->id);
        $this->disputeDocumentation->updateStripeDispute($dispute, $billingProfile);

        // cancel the customer's subscription immediately
        // in certain types of disputes
        if (in_array($eventData->reason, ['fraudulent', 'subscription_canceled', 'product_unacceptable', 'product_not_received', 'credit_not_processed'])) {
            $companies = $this->getCompaniesForBillingProfile($billingProfile);
            foreach ($companies as $company) {
                $this->tenant->runAs($company, function () use ($company) {
                    $this->cancelAction->cancel($company, 'dispute');
                });
            }
        }
    }

    /**
     * Handles charge.failed.
     */
    public function handleChargeRefunded(object $eventData, BillingProfile $billingProfile): void
    {
        // if a charge was refunded as fraudulent through stripe then the account should be canceled
        // and marked as fraudulent on our system
        if ($this->isChargeFraudulent($eventData)) {
            $companies = $this->getCompaniesForBillingProfile($billingProfile);
            foreach ($companies as $company) {
                $this->tenant->runAs($company, function () use ($company) {
                    $this->fraudCommand->markFraud($company);
                });
            }
        }
    }

    private function isChargeFraudulent(object $eventData): bool
    {
        if (isset($eventData->fraud_details->stripe_report) && 'fraudulent' == $eventData->fraud_details->stripe_report) {
            return true;
        }

        if (isset($eventData->fraud_details->user_report) && 'fraudulent' == $eventData->fraud_details->user_report) {
            return true;
        }

        return false;
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
