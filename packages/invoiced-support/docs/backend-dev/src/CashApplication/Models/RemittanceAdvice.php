<?php

namespace App\CashApplication\Models;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Enums\RemittanceAdviceStatus;
use App\Core\I18n\Currencies;
use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property Customer|null          $customer
 * @property Payment|null           $payment
 * @property DateTimeInterface      $payment_date
 * @property string                 $payment_method
 * @property string                 $payment_reference
 * @property float                  $total_gross_amount_paid
 * @property float                  $total_discount
 * @property float                  $total_net_amount_paid
 * @property string                 $currency
 * @property string|null            $notes
 * @property RemittanceAdviceStatus $status
 */
class RemittanceAdvice extends MultitenantModel
{
    private array $lines;

    public function getTablename(): string
    {
        return 'RemittanceAdvice';
    }

    protected static function getProperties(): array
    {
        return [
            'customer' => new Property(
                null: true,
                belongs_to: Customer::class,
            ),
            'payment' => new Property(
                null: true,
                belongs_to: Payment::class,
            ),
            'payment_date' => new Property(
                type: Type::DATE,
            ),
            'payment_method' => new Property(),
            'payment_reference' => new Property(),
            'total_gross_amount_paid' => new Property(
                type: Type::FLOAT,
            ),
            'total_discount' => new Property(
                type: Type::FLOAT,
            ),
            'total_net_amount_paid' => new Property(
                type: Type::FLOAT,
            ),
            'currency' => new Property(
                required: true,
                validate: ['callable', 'fn' => [Currencies::class, 'validateCurrency']],
            ),
            'notes' => new Property(
                null: true,
            ),
            'status' => new Property(
                type: Type::ENUM,
                enum_class: RemittanceAdviceStatus::class,
            ),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['lines'] = [];
        foreach ($this->getLines() as $line) {
            $result['lines'][] = $line->toArray();
        }

        return $result;
    }

    /**
     * @return RemittanceAdviceLine[]
     */
    public function getLines(): array
    {
        if (!isset($this->lines)) {
            $this->lines = RemittanceAdviceLine::where('remittance_advice_id', $this)
                ->all()
                ->toArray();
        }

        return $this->lines;
    }

    /**
     * @param RemittanceAdviceLine[] $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    public function determineStatus(): RemittanceAdviceStatus
    {
        if ($this->payment) {
            return RemittanceAdviceStatus::Posted;
        }

        // Check for an exception
        foreach ($this->getLines() as $line) {
            if ($line->exception) {
                return RemittanceAdviceStatus::Exception;
            }
        }

        return RemittanceAdviceStatus::ReadyToPost;
    }
}
