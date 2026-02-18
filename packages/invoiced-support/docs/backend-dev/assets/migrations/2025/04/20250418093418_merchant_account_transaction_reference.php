<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountTransactionReference extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('MerchantAccountTransactions')
            ->addColumn('merchant_reference', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
