<?php

namespace App\CashApplication\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Traits\AutoTimestamps;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int                             $id
 * @property CashApplicationBankAccount|null $cash_application_bank_account
 * @property string                          $transaction_id
 * @property DateTimeInterface               $date
 * @property string                          $description
 * @property float                           $amount
 * @property string|null                     $check_number
 * @property string|null                     $merchant_name
 * @property string|null                     $payment_reference_number
 * @property string|null                     $payment_ppd_id
 * @property string|null                     $payment_payee
 * @property string|null                     $payment_by_order_of
 * @property string|null                     $payment_payer
 * @property string|null                     $payment_method
 * @property string|null                     $payment_processor
 * @property string|null                     $payment_reason
 * @property string|null                     $payment_channel
 */
class BankFeedTransaction extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'cash_application_bank_account' => new Property(
                null: true,
                belongs_to: CashApplicationBankAccount::class,
            ),
            'transaction_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'date' => new Property(
                type: Type::DATE,
                required: true,
            ),
            'description' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'check_number' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'merchant_name' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_reference_number' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_ppd_id' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_payee' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_by_order_of' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_payer' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_method' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_processor' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_reason' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'payment_channel' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }
}
