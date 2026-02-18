<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\I18n\MoneyFormatter;
use App\Core\I18n\ValueObjects\Money;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\PaymentProcessing\Models\PaymentMethod;

class BalanceDueCard implements CardInterface
{
    public function __construct(private CustomerBalanceGenerator $balanceGenerator)
    {
    }

    public function getData(CustomerPortal $customerPortal): array
    {
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();
        $balance = $this->balanceGenerator->generate($customer);
        $formatter = MoneyFormatter::get();

        // Available credits = Open Credit Notes + Credit Balance
        $availableCredits = $balance->openCreditNotes->add($balance->availableCredits);

        return [
            'currency' => $balance->currency,
            'totalOutstanding' => $balance->totalOutstanding->toDecimal(),
            'hasCredits' => $availableCredits->isPositive(),
            'availableCredits' => $availableCredits->toDecimal(),
            'openEstimates' => $this->getTotalOpenEstimates($customerPortal, $customer),
            'showPayNowBtn' => $this->showPayNowButton($customerPortal, $customer, $balance->totalOutstanding),
        ];
    }

    private function getTotalOpenEstimates(CustomerPortal $customerPortal, Customer $customer): int
    {
        if (!$customerPortal->hasEstimates()) {
            return 0;
        }

        return Estimate::where('customer', $customer->id())
            ->where('draft', false)
            ->where('closed', false)
            ->where('voided', false)
            ->where('approved IS NULL')
            ->where('invoice_id IS NULL')
            ->where('status', EstimateStatus::EXPIRED, '<>')
            ->count();
    }

    private function showPayNowButton(CustomerPortal $customerPortal, Customer $customer, Money $totalOutstanding): bool
    {
        // if advance payments are allowed then this is always visible
        $settings = $customerPortal->getPaymentFormSettings();
        if ($settings->allowAdvancePayments) {
            return true;
        }

        // must owe money
        if (!$totalOutstanding->isPositive()) {
            return false;
        }

        // must accept some form of payments
        if (!PaymentMethod::acceptsPayments($customerPortal->company())) {
            return false;
        }

        // show pay now button when there is no payment method
        // on file or the customer has open non-autopay invoices
        if (!$customer->payment_source) {
            return true;
        }

        return Invoice::where('customer', $customer)
            ->where('currency', $totalOutstanding->currency)
            ->where('autopay', false)
            ->where('paid', false)
            ->where('draft', false)
            ->where('closed', false)
            ->where('voided', false)
            ->where('date', time(), '<=')
            ->count() > 0;
    }
}
