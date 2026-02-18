<?php

namespace App\CashApplication\Operations;

use App\CashApplication\Models\RemittanceAdviceLine;

class ResolveRemittanceAdviceLine
{
    public function resolve(RemittanceAdviceLine $line): void
    {
        $line->exception = null;
        $line->saveOrFail();

        $advice = $line->remittance_advice;
        $newStatus = $advice->determineStatus();
        if ($newStatus != $advice->status) {
            $advice->status = $newStatus;
            $advice->saveOrFail();
        }
        $advice->saveOrFail();
    }
}
