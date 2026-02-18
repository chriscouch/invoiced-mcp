<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\SalesTax\Exception\TaxCalculationException;
use App\SubscriptionBilling\Exception\PricingException;
use App\SubscriptionBilling\Libs\UpcomingInvoice;
use App\SubscriptionBilling\Models\Subscription;

class ActiveSubscriptionsCard implements CardInterface
{
    public function getData(CustomerPortal $customerPortal): array
    {
        $company = $customerPortal->company();
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        $addSubscriptionUrl = false;
        $signUpPage = $customer->signUpPage();
        if ($signUpPage && $signUpPage->allow_multiple_subscriptions) {
            $addSubscriptionUrl = $signUpPage->customerUrl($customer);
        }

        return [
            'subscriptions' => $this->getSubscriptions($customer, $company),
            'addSubscriptionUrl' => $addSubscriptionUrl,
            'canCancelSubscriptions' => $customerPortal->allowSubscriptionCancellation(),
        ];
    }

    private function getSubscriptions(Customer $customer, Company $company): array
    {
        /** @var Subscription[] $iterator */
        $iterator = Subscription::where('customer', $customer->id())
            ->where('canceled', false)
            ->where('finished', false)
            ->all();

        $subscriptions = [];
        foreach ($iterator as $subscription) {
            $plan = $subscription->plan();

            // custom fields
            $customFieldValues = CustomerPortalHelper::getCustomFields($company, $customer, $subscription->object, $subscription);

            // upcoming invoice
            $renewsNext = null;
            $nextChargeAmount = null;
            if ($subscription->renews_next > 0 && !$subscription->cancel_at_period_end) {
                $upcoming = new UpcomingInvoice($customer);
                $upcoming->setSubscription($subscription);

                try {
                    $invoice = $upcoming->build();

                    $renewsNext = date($company->date_format, $invoice->date);
                    if ($invoice->total > 0) {
                        $nextChargeAmount = $invoice->total;
                    }
                } catch (InvoiceCalculationException|TaxCalculationException|PricingException) {
                    // do nothing since this is a preview
                }
            }

            $endDate = $subscription->billingPeriods()->endDate();
            if ($endDate) {
                $endDate = $endDate->format($company->date_format);
            }

            $subscriptions[] = [
                'plan' => [
                    'name' => $plan->getCustomerFacingName(),
                    'interval' => $plan->toString(),
                ],
                'currency' => $plan->currency,
                'recurring_total' => $subscription->recurring_total,
                'renews_next' => $renewsNext,
                'renews_next_amount' => $nextChargeAmount,
                'end_date' => $endDate,
                'status' => $subscription->status,
                'url' => $subscription->url,
                'customFields' => $customFieldValues,
            ];
        }

        return $subscriptions;
    }
}
