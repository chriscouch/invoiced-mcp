<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentFlowMethod extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentFlows')
            ->addColumn('payment_method', 'smallinteger', ['null' => true, 'default' => null])
            ->update();
    }
}
