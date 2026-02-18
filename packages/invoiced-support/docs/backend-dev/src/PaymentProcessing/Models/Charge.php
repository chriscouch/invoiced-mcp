<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\CashApplication\Models\Payment;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\Core\Utils\ModelNormalizer;
use App\PaymentProcessing\Interfaces\HasPaymentSourceInterface;
use App\PaymentProcessing\Traits\HasPaymentSourceTrait;
use Exception;

/**
 * A charge represents the exchange of money through a payment gateway.
 *
 * @property int                             $id
 * @property MerchantAccount|null            $merchant_account
 * @property PaymentFlow|null                $payment_flow
 * @property int|null                        $payment_id
 * @property Payment|null                    $payment
 * @property string                          $currency
 * @property float                           $amount
 * @property int|null                        $customer_id
 * @property Customer|null                   $customer
 * @property string                          $status
 * @property float                           $amount_refunded
 * @property bool                            $refunded
 * @property bool                            $disputed
 * @property string|null                     $receipt_email
 * @property string|null                     $failure_message
 * @property string                          $gateway
 * @property string                          $gateway_id
 * @property string|null                     $description
 * @property int                             $last_status_check
 * @property Refund[]                        $refunds
 * @property MerchantAccountTransaction|null $merchant_account_transaction
 */
class Charge extends MultitenantModel implements EventObjectInterface, HasPaymentSourceInterface
{
    use AutoTimestamps;
    use HasPaymentSourceTrait;
    use EventObjectTrait;
    use ApiObjectTrait;

    const SUCCEEDED = 'succeeded';
    const PENDING = 'pending';
    const FAILED = 'failed';

    protected static function getProperties(): array
    {
        return [
            'merchant_account' => new Property(
                null: true,
                belongs_to: MerchantAccount::class,
            ),
            'payment_flow' => new Property(
                null: true,
                belongs_to: PaymentFlow::class,
            ),
            'payment' => new Property(
                null: true,
                belongs_to: Payment::class,
            ),
            'currency' => new Property(
                type: Type::STRING,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'status' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['enum', 'choices' => ['succeeded', 'pending', 'failed']],
            ),
            'amount_refunded' => new Property(
                type: Type::FLOAT,
                default: 0,
            ),
            'refunded' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'disputed' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'receipt_email' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'failure_message' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_source_type' => new Property(
                null: true,
                validate: ['enum', 'choices' => ['card', 'bank_account']],
                in_array: false,
            ),
            'payment_source_id' => new Property(
                type: Type::INTEGER,
                null: true,
                in_array: false,
            ),
            'gateway' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'gateway_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'description' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'last_status_check' => new Property(
                type: Type::DATE_UNIX,
                required: true,
                validate: 'timestamp',
                default: 'now',
                in_array: false,
            ),
            'refunds' => new Property(
                has_many: Refund::class,
            ),
            'merchant_account_transaction' => new Property(
                null: true,
                belongs_to: MerchantAccountTransaction::class,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $paymentSource = $this->payment_source;
        $result['object'] = $this->object;
        $result['payment_source'] = $paymentSource ? $paymentSource->toArray() : null;
        $result['refunds'] = [];
        foreach ($this->refunds as $refund) {
            $result['refunds'][] = $refund->toArray();
        }

        return $result;
    }

    public function delete(array $data = []): bool
    {
        throw new Exception('Deleting charges is not allowed');
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    public function getAmountRefunded(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount_refunded);
    }

    //
    // EventObjectInterface
    //

    public function getEventAssociations(): array
    {
        $result = [];
        if ($customerId = $this->customer_id) {
            $result[] = ['customer', $customerId];
        }

        if ($paymentId = $this->payment_id) {
            $result[] = ['payment', $paymentId];
        }

        return $result;
    }

    public function getEventObject(): array
    {
        return ModelNormalizer::toArray($this, expand: ['customer']);
    }
}
