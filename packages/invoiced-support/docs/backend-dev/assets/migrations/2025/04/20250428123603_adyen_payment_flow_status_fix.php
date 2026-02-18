<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenPaymentFlowStatusFix extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('UPDATE PaymentFlows SET status = 1 WHERE status = 3');
    }
}
