<?php

namespace App\AccountsReceivable\Search;

use App\AccountsReceivable\Models\Customer;
use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\PaymentProcessing\Search\PaymentSourceSearchDocument;

class CustomerSearchDocument implements SearchDocumentInterface
{
    public function __construct(private Customer $customer)
    {
    }

    public function toSearchDocument(): array
    {
        $paymentSource = $this->customer->payment_source;

        return [
            'name' => $this->customer->name,
            'number' => $this->customer->number,
            'autopay' => $this->customer->autopay,
            'payment_terms' => $this->customer->payment_terms,
            'payment_source' => $paymentSource ? (new PaymentSourceSearchDocument($paymentSource))->toSearchDocument() : null,
            'currency' => $this->customer->currency,
            'chase' => $this->customer->chase,
            'attention_to' => $this->customer->attention_to,
            'address1' => $this->customer->address1,
            'address2' => $this->customer->address2,
            'city' => $this->customer->city,
            'state' => $this->customer->state,
            'postal_code' => $this->customer->postal_code,
            'country' => $this->customer->country,
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'tax_id' => $this->customer->tax_id,
            'metadata' => (array) $this->customer->metadata,
            '_customer' => $this->customer->id(),
        ];
    }
}
