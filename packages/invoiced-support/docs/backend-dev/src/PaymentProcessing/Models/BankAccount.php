<?php

namespace App\PaymentProcessing\Models;

use App\Core\I18n\Currencies;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Gateways\TestGateway;

/**
 * This references a bank account stored on a payment gateway.
 *
 * @property string      $last4
 * @property string      $bank_name
 * @property string|null $routing_number
 * @property bool        $verified
 * @property string      $country
 * @property string      $currency
 * @property int         $verification_last_sent
 * @property string|null $account_holder_name
 * @property string|null $account_holder_type
 * @property string|null $type
 * @property string|null $account_number
 */
class BankAccount extends PaymentSource
{
    protected static function getProperties(): array
    {
        return [
            'last4' => new Property(
                required: true,
            ),
            'bank_name' => new Property(
                required: true,
            ),
            'routing_number' => new Property(),
            'verified' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'country' => new Property(
                required: true,
                default: 'US',
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'verification_last_sent' => new Property(
                type: Type::DATE_UNIX,
                in_array: false,
            ),
            'account_holder_name' => new Property(
                null: true,
            ),
            'account_holder_type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['company', 'individual']],
            ),
            'type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['checking', 'savings']],
            ),
            'account_number' => new Property(
                null: true,
                encrypted: true,
                in_array: false,
            ),
        ];
    }

    public function toString($short = false): string
    {
        $bankName = match (strtolower($this->bank_name)) {
            'unknown' => '',
            default => $this->bank_name,
        };

        return $bankName.' *'.$this->last4;
    }

    public function getMethod(): string
    {
        // Flywire is always Direct Debit
        if (FlywireGateway::ID == $this->gateway) {
            return PaymentMethod::DIRECT_DEBIT;
        }

        // GoCardless is always Direct Debit
        if (GoCardlessGateway::ID == $this->gateway) {
            return PaymentMethod::DIRECT_DEBIT;
        }

        // Use ACH in US, Direct Debit everywhere else
        return 'US' == $this->country ? PaymentMethod::ACH : PaymentMethod::DIRECT_DEBIT;
    }

    public function needsVerification(): bool
    {
        return !$this->verified && in_array($this->gateway, [StripeGateway::ID, MockGateway::ID, TestGateway::ID, AdyenGateway::ID]);
    }
}
