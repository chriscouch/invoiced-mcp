<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemovePaymentFlowMethod extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentFlows')
            ->removeColumn('method')
            ->update();
    }
}
