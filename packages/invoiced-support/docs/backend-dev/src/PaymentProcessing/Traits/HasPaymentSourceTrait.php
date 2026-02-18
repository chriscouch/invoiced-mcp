<?php

namespace App\PaymentProcessing\Traits;

use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentSource;

/**
 * @property PaymentSource|null $payment_source
 * @property string|null        $payment_source_type
 * @property int|null           $payment_source_id
 */
trait HasPaymentSourceTrait
{
    private ?PaymentSource $paymentSource;
    private bool $refreshPaymentSource = false;

    /**
     * Gets the payment_source property (if a source exists).
     */
    protected function getPaymentSourceValue(): ?PaymentSource
    {
        if (!isset($this->paymentSource) || $this->refreshPaymentSource) {
            $this->refreshPaymentSource = false;
            $type = $this->payment_source_type;
            if (ObjectType::BankAccount->typeName() === $type) {
                $this->paymentSource = BankAccount::find($this->payment_source_id);
            } elseif (ObjectType::Card->typeName() === $type) {
                $this->paymentSource = Card::find($this->payment_source_id);
            } else {
                $this->paymentSource = null;
            }
        }

        return $this->paymentSource;
    }

    /**
     * Sets the associated payment source.
     */
    public function setPaymentSource(PaymentSource $paymentSource): void
    {
        $this->payment_source_id = (int) $paymentSource->id();
        $this->payment_source_type = $paymentSource->object;
        $this->paymentSource = $paymentSource;
    }

    /**
     * Clears the payment source association.
     */
    public function clearPaymentSource(): void
    {
        $this->paymentSource = null;
        $this->payment_source_type = null;
        $this->payment_source_id = null;
    }

    public function getPaymentSourceType(): ?string
    {
        return $this->payment_source_type;
    }

    public function getPaymentSourceId(): ?int
    {
        return $this->payment_source_id;
    }
}
