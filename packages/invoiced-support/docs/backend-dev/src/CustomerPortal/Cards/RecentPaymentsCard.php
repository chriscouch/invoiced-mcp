<?php

namespace App\CustomerPortal\Cards;

use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Interfaces\CardInterface;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalHelper;
use App\CashApplication\Models\Transaction;

class RecentPaymentsCard implements CardInterface
{
    public function getData(CustomerPortal $customerPortal): array
    {
        /** @var Customer $customer */
        $customer = $customerPortal->getSignedInCustomer();

        return [
            'recentPayments' => $this->getMostRecentPayments($customer),
        ];
    }

    private function getMostRecentPayments(Customer $customer): array
    {
        $recentPayments = Transaction::where('customer', $customer->id())
            ->where('(type="'.Transaction::TYPE_CHARGE.'" OR type="'.Transaction::TYPE_PAYMENT.'" OR payment_id IS NOT NULL)')
            ->where('parent_transaction IS NULL')
            ->sort('date DESC')
            ->first(3);

        $result = [];

        /** @var Transaction $recentPayment */
        foreach ($recentPayments as $recentPayment) {
            $method = $recentPayment->getMethod()->toString();
            $paymentSource = $recentPayment->payment_source;
            if ($paymentSource) {
                $method = $paymentSource->toString(true);
            }
            $amount = $recentPayment->paymentAmount();

            $result[] = [
                'currency' => $amount->currency,
                'amount' => $amount->toDecimal(),
                'date' => date($customer->tenant()->date_format, $recentPayment->date),
                'method' => $method,
                'icon' => $paymentSource ? CustomerPortalHelper::getPaymentSourceIcon($paymentSource) : null,
                'status' => $recentPayment->status,
                'pdf_url' => $recentPayment->pdf_url,
                'failure_reason' => Transaction::STATUS_FAILED == $recentPayment->status ? $recentPayment->failure_reason : null,
            ];
        }

        return $result;
    }
}
