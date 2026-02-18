<?php

namespace App\CashApplication\Search;

use App\CashApplication\Models\Payment;
use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Search\PaymentSourceSearchDocument;

class PaymentSearchDocument implements SearchDocumentInterface
{
    public function __construct(private Payment $payment)
    {
    }

    public function toSearchDocument(): array
    {
        $customer = $this->payment->customer();
        $charge = $this->payment->charge;

        return [
            'date' => $this->payment->date,
            'method' => $this->payment->method,
            'currency' => $this->payment->currency,
            'amount' => $this->payment->amount,
            'voided' => $this->payment->voided,
            'source' => $this->payment->source,
            'reference' => $this->payment->reference,
            'balance' => $this->payment->balance,
            'charge' => $charge ? $this->chargeToSearchDocument($charge) : null,
            '_customer' => $this->payment->customer,
            'customer' => $customer ? [
                'name' => $customer->name,
            ] : null,
        ];
    }

    private function chargeToSearchDocument(Charge $charge): array
    {
        $paymentSource = $charge->payment_source;

        return [
            'gateway' => $charge->gateway,
            'gateway_id' => $charge->gateway_id,
            'currency' => $charge->currency,
            'amount' => $charge->amount,
            'amount_refunded' => $charge->amount_refunded,
            'status' => $charge->status,
            'disputed' => $charge->disputed,
            'refunded' => $charge->refunded,
            'payment_source' => $paymentSource ? (new PaymentSourceSearchDocument($paymentSource))->toSearchDocument() : null,
        ];
    }
}
