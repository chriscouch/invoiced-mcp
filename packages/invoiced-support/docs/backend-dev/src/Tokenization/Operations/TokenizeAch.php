<?php

namespace App\Tokenization\Operations;

use App\Tokenization\Enums\TokenizationApplicationType;
use App\Tokenization\Models\TokenizationApplication;

class TokenizeAch extends Tokenize
{
    public function getParameters(array $data): array
    {
        $parameters = $this->getBaseParameters();
        $parameters['paymentMethod'] = ['type' => 'ach'];
        $parameters['bankAccount'] = [
            'bankAccountNumber' => $data['bank_account']['account_number'],
            'bankLocationId' => $data['bank_account']['routing_number'],
            'ownerName' => $data['bank_account']['account_holder_name'],
        ];

        return $parameters;
    }

    public function makeApplication(array $data, array $input): TokenizationApplication
    {
        $application = parent::makeApplication($data, $input);
        $application->type = TokenizationApplicationType::ACH;
        $application->last4 = $data['additionalData']['bankSummary'] ?? '0000';
        $application->routing_number = $input['bank_account']['routing_number'] ?? null;
        $application->account_type = $input['bank_account']['account_type'] ?? null;
        $application->account_holder_type = $input['bank_account']['account_holder_type'] ?? null;
        $application->account_holder_name = $input['bank_account']['account_holder_name'] ?? null;
        $application->gateway_id = $data['additionalData']['tokenization.storedPaymentMethodId'] ?? $data['additionalData']['recurring.recurringDetailReference'] ?? null;
        $application->gateway_customer = $data['additionalData']['tokenization.shopperReference'] ?? $data['additionalData']['recurring.shopperReference'] ?? null;

        return $application;
    }

}
