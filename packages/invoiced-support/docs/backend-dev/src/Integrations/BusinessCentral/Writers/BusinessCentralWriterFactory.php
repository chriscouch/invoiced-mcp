<?php

namespace App\Integrations\BusinessCentral\Writers;

use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Interfaces\AccountingWriterInterface;
use App\Integrations\AccountingSync\Writers\NullWriter;

class BusinessCentralWriterFactory
{
    public function __construct(
        private BusinessCentralPaymentWriter $payments,
    ) {
    }

    public function get(AccountingWritableModelInterface $model): AccountingWriterInterface
    {
        return match (get_class($model)) {
            Payment::class => $this->payments,
            default => new NullWriter(),
        };
    }
}
