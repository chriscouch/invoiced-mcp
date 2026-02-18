<?php

namespace App\Tokenization\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Tokenization\Enums\TokenizationApplicationType;

/**
 * @property TokenizationApplicationType             $type
 * @property string             $identifier
 * @property string             $funding
 * @property string             $brand
 * @property string             $last4
 * @property int                $exp_month
 * @property int                $exp_year
 * @property string             $gateway
 * @property ?string            $gateway_id
 * @property ?string            $gateway_customer
 * @property ?MerchantAccount   $merchant_account
 * @property string             $failure_reason
 * @property string|null        $country
 * @property string             $bank_name
 * @property string|null        $routing_number
 * @property string|null        $account_holder_name
 * @property string|null        $account_holder_type
 * @property string|null        $account_type
 */
class TokenizationApplication extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'type' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: TokenizationApplicationType::class,
            ),
            'identifier' => new Property(
                required: true,
            ),
            'funding' => new Property(
                validate: ['enum', 'choices' => ['credit', 'debit', 'prepaid', 'unknown']],
                default: 'unknown',
            ),
            'brand' => new Property(
                null: true,
            ),
            'last4' => new Property(
                required: true,
            ),
            'exp_month' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'exp_year' => new Property(
                type: Type::INTEGER,
                null: true,
            ),
            'gateway' => new Property(
                default: AdyenGateway::ID,
            ),
            'gateway_id' => new Property(
                null: true,
            ),
            'gateway_customer' => new Property(
                null: true,
            ),
            'merchant_account' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
                belongs_to: MerchantAccount::class,
            ),
            'failure_reason' => new Property(
                null: true,
            ),
            'country' => new Property(
                null: true,
            ),
            'bank_name' => new Property(
                null: true,
            ),
            'routing_number' => new Property(
                null: true,
            ),
            'account_holder_name' => new Property(
                null: true,
            ),
            'account_holder_type' => new Property(
                null: true,
            ),
            'account_type' => new Property(
                null: true,
            ),
        ];
    }
}