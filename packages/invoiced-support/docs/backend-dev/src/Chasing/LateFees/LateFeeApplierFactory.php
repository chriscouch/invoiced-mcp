<?php

namespace App\Chasing\LateFees;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\LateFee;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Database\TransactionManager;

/**
 * Determines LateFee application logic.
 */
class LateFeeApplierFactory
{
    public function __construct(
        private readonly TransactionManager $transactionManager
    ) {
    }

    public function create(?LateFee $fee, LateFeeSchedule $schedule, Invoice $invoice): AbstractLateFeeApplier
    {
        if (1 === $fee?->version) {
            return new LateFeeApplierLegacy($this->transactionManager, $fee, $schedule, $invoice);
        }

        return new LateFeeApplier($this->transactionManager, $fee, $schedule, $invoice);
    }
}
