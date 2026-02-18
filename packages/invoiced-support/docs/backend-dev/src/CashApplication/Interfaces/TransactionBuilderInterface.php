<?php

namespace App\CashApplication\Interfaces;

use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\Core\Orm\Model;

interface TransactionBuilderInterface
{
    /**
     * Builds a list of models that should be created
     * in order to apply this type of payment split.
     *
     * @throws ApplyPaymentException
     *
     * @return Model[]
     */
    public function build(Payment $payment, array $split): array;
}
