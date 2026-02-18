<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentFlowMerchantAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentFlows')
            ->addColumn('gateway', 'string', ['null' => true, 'default' => null])
            ->addColumn('merchant_account_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('funding', 'string', ['null' => true, 'default' => null])
            ->addColumn('last4', 'string', ['null' => true, 'default' => null])
            ->addColumn('expMonth', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('expYear', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
