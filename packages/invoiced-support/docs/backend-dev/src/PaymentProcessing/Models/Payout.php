<?php

namespace App\PaymentProcessing\Models;

use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Traits\EventObjectTrait;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use App\Core\RestApi\Traits\ApiObjectTrait;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PayoutStatus;
use DateTimeInterface;

/**
 * A payout is when funds that have been collected through a payment processor
 * are sent to the client's bank account.
 *
 * @property int                             $id
 * @property MerchantAccount                 $merchant_account
 * @property string                          $reference
 * @property string                          $currency
 * @property float                           $amount
 * @property float                           $pending_amount
 * @property float                           $gross_amount
 * @property string                          $description
 * @property PayoutStatus                    $status
 * @property string                          $bank_account_name
 * @property string|null                     $statement_descriptor
 * @property DateTimeInterface               $initiated_at
 * @property DateTimeInterface|null          $arrival_date
 * @property string|null                     $failure_message
 * @property MerchantAccountTransaction|null $merchant_account_transaction
 * @property string|null $modification_reference
 */
class Payout extends MultitenantModel implements EventObjectInterface
{
    use ApiObjectTrait;
    use AutoTimestamps;
    use EventObjectTrait;

    protected static function getProperties(): array
    {
        return [
            'merchant_account' => new Property(
                required: true,
                belongs_to: MerchantAccount::class,
            ),
            'reference' => new Property(
                type: Type::STRING,
                required: true,
            ),
            'currency' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'pending_amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'gross_amount' => new Property(
                type: Type::FLOAT,
                required: true,
            ),
            'description' => new Property(
                type: Type::STRING,
            ),
            'status' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: PayoutStatus::class,
            ),
            'bank_account_name' => new Property(
                type: Type::STRING,
            ),
            'statement_descriptor' => new Property(
                type: Type::STRING,
            ),
            'initiated_at' => new Property(
                type: Type::DATETIME,
            ),
            'arrival_date' => new Property(
                type: Type::DATE,
                null: true,
            ),
            'failure_message' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'merchant_account_transaction' => new Property(
                null: true,
                belongs_to: MerchantAccountTransaction::class,
            ),
            'modification_reference' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }

    public function getAmount(): Money
    {
        return Money::fromDecimal($this->currency, $this->amount);
    }

    public function getAmountPending(): Money
    {
        return Money::fromDecimal($this->currency, $this->pending_amount);
    }

    protected function getBreakdownValue(): ?array
    {
        /** @var MerchantAccountTransaction[] $transactions */
        $transactions = MerchantAccountTransaction::where('payout_id', $this->id)
            ->all();

        if (!count($transactions)) {
            return null;
        }

        $currency = $this->currency;
        $payments = Money::zero($this->currency);
        $fees = Money::zero($this->currency);
        $refunds = Money::zero($this->currency);
        $disputes = Money::zero($this->currency);
        $adjustments = Money::zero($this->currency);
        foreach ($transactions as $transaction) {
            $gross = Money::fromDecimal($currency, $transaction->amount);
            if (MerchantAccountTransactionType::Payment == $transaction->type) {
                $payments = $payments->add($gross);
            }

            if (MerchantAccountTransactionType::Refund == $transaction->type) {
                $refunds = $refunds->add($gross);
            }

            if (MerchantAccountTransactionType::RefundReversal == $transaction->type) {
                $refunds = $refunds->subtract($gross);
            }

            if (in_array($transaction->type, [MerchantAccountTransactionType::Dispute, MerchantAccountTransactionType::DisputeReversal])) {
                $disputes = $disputes->add($gross);
            }

            if (MerchantAccountTransactionType::Adjustment == $transaction->type) {
                $adjustments = $adjustments->add($gross);
            }

            if ($feeAmount = $transaction->fee) {
                $fees = $fees->subtract(Money::fromDecimal($currency, $feeAmount));
            }
        }

        return [
            'payments' => $payments->toDecimal(),
            'fees' => $fees->toDecimal(),
            'refunds' => $refunds->toDecimal(),
            'disputes' => $disputes->toDecimal(),
            'adjustments' => $adjustments->toDecimal(),
        ];
    }
}
