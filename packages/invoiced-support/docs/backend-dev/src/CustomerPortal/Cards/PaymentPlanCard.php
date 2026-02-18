<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentPlans\Models\PaymentPlan;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentPlanCard implements CardInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getData(CustomerPortal $customerPortal): array
    {
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        return [
            'paymentPlans' => $this->getPaymentPlans($customer),
        ];
    }

    public function getPaymentPlans(Customer $customer): array
    {
        $invoices = Invoice::where('customer', $customer->id())
            ->where('payment_plan_id IS NOT NULL')
            ->where('draft', false)
            ->where('voided', false)
            ->where('paid', false)
            ->where('balance', 0, '>')
            ->all();

        $company = $customer->tenant();

        $result = [];
        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            /** @var PaymentPlan $paymentPlan */
            $paymentPlan = $invoice->paymentPlan();
            $installments = $paymentPlan->installments;

            $installmentData = $paymentPlan->calculateBalance();
            $startDate = $installments[0]->date;
            $nextDueDate = null;
            foreach ($installments as $installment) {
                if ($installment->balance > 0) {
                    $nextDueDate = $installment->date;
                    break;
                }
            }

            $percentPaid = round(min(100, max(0, (1.0 - $invoice->balance / $invoice->total) * 100)));

            $result[] = [
                'status' => $paymentPlan->status,
                'currency' => $invoice->currency,
                'total_balance' => $invoice->balance,
                'due_now' => $installmentData['balance'],
                'percent_paid' => $percentPaid,
                'start_date' => date($company->date_format, $startDate),
                'next_due_date' => date($company->date_format, (int) $nextDueDate),
                'url' => $invoice->url,
                'payment_url' => $this->urlGenerator->generate('customer_portal_payment_form', [
                    'subdomain' => $company->getSubdomainUsername(),
                    'invoices' => [$invoice->client_id],
                ]),
            ];
        }

        return $result;
    }
}
