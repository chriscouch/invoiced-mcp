<?php

namespace App\SubscriptionBilling\Operations;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Database\TransactionManager;
use App\Core\Orm\Exception\MassAssignmentException;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Operations\AutoPay;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Libs\EmailTriggers;
use App\Sending\Email\Models\EmailTemplate;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Libs\DateSnapper;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Trait\ModifySubscriptionTrait;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class CreateSubscription
{
    use ModifySubscriptionTrait;

    public function __construct(
        private BillSubscription $billSubscription,
        private AutoPay $autoPay,
        private TransactionManager $transaction,
        private EmailSpool $emailSpool,
    ) {
    }

    /**
     * Creates a new subscription.
     *
     * @throws OperationException
     */
    public function create(array $parameters): Subscription
    {
        $subscription = new Subscription();

        // Use company time zone before any date calculations
        $subscription->tenant()->useTimezone();

        $this->verifyCustomer($subscription, $parameters);
        $this->verifyPlan($subscription, $parameters);
        $this->verifyAddons($subscription, $parameters);
        $this->verifyCoupons($subscription, $parameters);
        $this->verifyContractRenewalMethod($subscription, $parameters);
        $this->verifySubscriptionAmount($subscription, $parameters);

        foreach ($parameters as $k => $v) {
            $subscription->$k = $v;
        }

        $this->verifyBillInAdvanceDays($subscription);
        $this->determineCurrentPeriod($subscription);
        $this->calendarBilling($subscription);
        $this->calculateContractPeriod($subscription);
        $this->setStatus($subscription);

        $this->transaction->perform(function () use ($subscription) {
            try {
                if (!$subscription->create()) {
                    if (count($subscription->getErrors()) > 0) {
                        throw new OperationException($subscription->getErrors());
                    }

                    throw new OperationException('There was an error creating the subscription.');
                }
            } catch (MassAssignmentException $e) {
                throw new OperationException($e->getMessage());
            }

            $this->billOnCreate($subscription);
        });

        // Recalculate MRR / Recurring Total
        // Should happen before confirmation email.
        $subscription->updateMrr();

        // send a confirmation email
        $this->sendConfirmationEmail($subscription);

        return $subscription;
    }

    private function verifyCustomer(Subscription $subscription, array &$parameters): void
    {
        if (!isset($parameters['customer'])) {
            throw new OperationException('Customer missing');
        }

        $customer = $parameters['customer'];
        unset($parameters['customer']);

        if ($customer instanceof Customer) {
            $subscription->setCustomer($customer);

            return;
        }

        if (is_numeric($customer)) {
            $customer2 = Customer::find($customer);
            if (!$customer2) {
                throw new OperationException('No such customer: '.$customer);
            }
            $subscription->setCustomer($customer2);
        } else {
            throw new OperationException('Invalid customer');
        }
    }

    /**
     * Determines the current billing period when creating subscriptions.
     */
    private function determineCurrentPeriod(Subscription $subscription): void
    {
        $startDate = $subscription->start_date;
        if ($startDate) {
            $startDate = CarbonImmutable::createFromTimestamp($startDate);
        } else {
            // if the start date is not provided then set it to the beginning of today
            $startDate = CarbonImmutable::now()->setTime(0, 0);
        }

        if ($startDate->lessThan(new CarbonImmutable('-5 years'))) {
            // subscription cannot start more than 5 years in the past
            throw new OperationException('Subscriptions cannot start more than 5 years in the past!');
        }

        // subscription for an AutoPay customer cannot start more than 32 days in the past
        $hasAutoPay = $subscription->customer()->autopay;
        if ($hasAutoPay && $startDate->lessThan(new CarbonImmutable('-32 days'))) {
            throw new OperationException('Subscriptions for AutoPay customers cannot start more than 1 month in the past!');
        }

        $subscription->start_date = $startDate->getTimestamp();

        try {
            $subscription->setCurrentBillingCycle($subscription->billingPeriods()->initial());
        } catch (InvalidArgumentException $e) {
            throw new OperationException($e->getMessage());
        }

        // handle cancellations at the end of the billing period
        if ($subscription->cancel_at_period_end) {
            $subscription->canceled_at = CarbonImmutable::now()->getTimestamp();
        }
    }

    /**
     * Bills the subscription after it is created, if needed.
     *
     * @throws OperationException
     */
    private function billOnCreate(Subscription $subscription): void
    {
        // generate first invoice immediately (if it's time)
        $invoice = $this->billSubscription->bill($subscription);

        // if an AutoPay invoice attempt collection now
        // if it fails then the whole transaction will be rolled back
        if ($invoice && self::shouldCollect($subscription, $invoice)) {
            // We never want to send a failed AutoPay
            // notification here because the transaction
            // will be rolled back automatically and
            // any update payment info link in the email
            // will be invalid. Also, this message is not
            // really appropriate for a new sign up.
            try {
                $this->autoPay->collect($invoice, AutoPay::PAYMENT_PLAN_MODE_CURRENTLY_DUE, false);
            } catch (AutoPayException $e) {
                throw new OperationException($e->getMessage());
            }
        }
    }

    private function calendarBilling(Subscription $subscription): void
    {
        $nthDay = (int) $subscription->snap_to_nth_day;
        if (!$nthDay) {
            return;
        }

        // We do not allow plans with an interval count > 1 to
        // be used with calendar billing. For example, a quarterly billing
        // interval cannot be used with calendar billing.
        $plan = $subscription->plan();
        if ($nthDay > 0 && $plan->interval_count > 1) {
            throw new OperationException('Calendar billing cannot be used when the plan interval count is greater than 1.');
        }

        // Validate the given nth day
        try {
            (new DateSnapper($plan->interval()))->validate($nthDay);
        } catch (InvalidArgumentException $e) {
            throw new OperationException($e->getMessage());
        }
    }

    /**
     * Checks if an invoice generated from this subscription
     * after creation should be collected.
     */
    private function shouldCollect(Subscription $subscription, Invoice $invoice): bool
    {
        // we want to collect if these are true:
        // i)   it's an AutoPay invoice
        // ii)  has not been paid yet
        // iii) the customer has a verified payment source
        // iv)  next payment attempt is in the future
        // v)  generated invoice is not a draft (subscription_draft_invoices to false)

        if (!$invoice->autopay) {
            return false;
        }

        if ($invoice->paid) {
            return false;
        }

        if ($invoice->draft) {
            return false;
        }

        $paymentSource = $subscription->customer()->payment_source;
        if (!$paymentSource) {
            return false;
        }

        if ($paymentSource->needsVerification()) {
            return false;
        }

        // Newly created AutoPay invoices have a payment date one hour
        // ahead. If the payment is scheduled less than 1 hour away then
        // we can collect the payment now.
        if ($invoice->next_payment_attempt > (new CarbonImmutable('+1 hour'))->getTimestamp()) {
            return false;
        }

        return true;
    }

    private function sendConfirmationEmail(Subscription $subscription): void
    {
        // send a confirmation email (if turned on)
        if (EmailTriggers::make($subscription->tenant())->isEnabled('new_subscription')) {
            $emailTemplate = EmailTemplate::make($subscription->tenant_id, EmailTemplate::SUBSCRIPTION_CONFIRMATION);
            // If the confirmation email fails to spool then we don't
            // pass along the error because we want the operation
            // to succeed.
            $this->emailSpool->spoolDocument($subscription, $emailTemplate, [], false);
        }
    }
}
