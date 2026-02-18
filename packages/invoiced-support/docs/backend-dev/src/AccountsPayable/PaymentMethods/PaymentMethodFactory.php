<?php

namespace App\AccountsPayable\PaymentMethods;

use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Interfaces\AccountsPayablePaymentMethodInterface;

class PaymentMethodFactory
{
    public function __construct(
        private readonly AchPaymentMethod $ach,
        private readonly CreditCardPaymentMethod $creditCard,
        private readonly PrintCheckPaymentMethod $printCheck,
        private readonly ECheckPaymentMethod $eCheck,
    ) {
    }

    /**
     * @throws AccountsPayablePaymentException
     */
    public function get(string $id): AccountsPayablePaymentMethodInterface
    {
        return match ($id) {
            'ach' => $this->ach,
            'credit_card' => $this->creditCard,
            'echeck' => $this->eCheck,
            'print_check' => $this->printCheck,
            default => throw new AccountsPayablePaymentException('Payment method not recognized: '.$id),
        };
    }
}
