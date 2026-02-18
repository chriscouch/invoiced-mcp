<?php

namespace App\PaymentProcessing\Search;

use App\Core\Search\Interfaces\SearchDocumentInterface;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentSource;

class PaymentSourceSearchDocument implements SearchDocumentInterface
{
    public function __construct(private PaymentSource $paymentSource)
    {
    }

    public function toSearchDocument(): array
    {
        $document = [
            'type' => $this->paymentSource->object,
            'gateway' => $this->paymentSource->gateway,
            'gateway_id' => $this->paymentSource->gateway_id,
            'gateway_customer' => $this->paymentSource->gateway_customer,
        ];

        if ($this->paymentSource instanceof BankAccount) {
            $document = $this->bankAccountToSearchDocument($document, $this->paymentSource);
        } elseif ($this->paymentSource instanceof Card) {
            $document = $this->cardToSearchDocument($document, $this->paymentSource);
        }

        return $document;
    }

    public function bankAccountToSearchDocument(array $document, BankAccount $bankAccount): array
    {
        $document['last4'] = $bankAccount->last4;
        $document['bank_name'] = $bankAccount->bank_name;
        $document['routing_number'] = $bankAccount->routing_number;
        $document['country'] = $bankAccount->country;

        return $document;
    }

    public function cardToSearchDocument(array $document, Card $card): array
    {
        $document['last4'] = $card->last4;
        $document['exp_month'] = $card->exp_month;
        $document['exp_year'] = $card->exp_year;
        $document['brand'] = $card->brand;

        return $document;
    }
}
