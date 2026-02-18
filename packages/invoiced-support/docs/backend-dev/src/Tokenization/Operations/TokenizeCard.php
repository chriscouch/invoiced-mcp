<?php

namespace App\Tokenization\Operations;

use App\Tokenization\Enums\TokenizationApplicationType;
use App\Tokenization\Models\TokenizationApplication;

class TokenizeCard extends Tokenize
{
    public function getParameters(array $data): array
    {
        $parameters = $this->getBaseParameters();
        $parameters['riskData'] = $data['card']['riskData'];
        $parameters['paymentMethod'] = [
            "type" => "scheme",
            "encryptedCardNumber" => $data['card']['encryptedCardNumber'],
            "encryptedExpiryMonth" => $data['card']['encryptedExpiryMonth'],
            "encryptedExpiryYear" => $data['card']['encryptedExpiryYear'],
            "encryptedSecurityCode" => $data['card']['encryptedSecurityCode'],
            "holderName" => $data['card']['name'],
        ];

        return $parameters;
    }
    public function makeApplication(array $data, array $input): TokenizationApplication
    {
        $application = parent::makeApplication($data, $input);
        $application->type = TokenizationApplicationType::CARD;
        $application->funding = strtolower($data['additionalData']['fundingSource'] ?? 'unknown');
        $application->brand = $data['additionalData']['paymentMethod'] ?? $data['paymentMethod']['brand'] ?? 'Unknown';
        $application->last4 = $data['additionalData']['cardSummary'] ?? '0000';


        $expiry = $data['additionalData']['expiryDate'] ?? '';
        $expiryParts = explode('/', $expiry);

        $application->exp_month = (int) ($expiryParts[0] ?? '12');
        $application->exp_year = (int) ($expiryParts[1] ?? date('Y'));
        $application->gateway_id = $data['additionalData']['tokenization.storedPaymentMethodId'] ?? $data['additionalData']['recurring.recurringDetailReference'] ?? null;
        $application->gateway_customer = $data['additionalData']['tokenization.shopperReference'] ?? $data['additionalData']['recurring.shopperReference'] ?? null;
        $application->country = $data['additionalData']['cardIssuingCountry'] ?? null;

        return $application;
    }

}
