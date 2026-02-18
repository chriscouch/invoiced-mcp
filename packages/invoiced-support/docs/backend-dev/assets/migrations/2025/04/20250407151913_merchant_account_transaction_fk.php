<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountTransactionFk extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableForeignKeyChecks();
        $this->table('Charges')
            ->addColumn('merchant_account_transaction_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('merchant_account_transaction_id', 'MerchantAccountTransactions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->update();
        $this->table('Refunds')
            ->addColumn('merchant_account_transaction_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('merchant_account_transaction_id', 'MerchantAccountTransactions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->update();
        $this->table('Disputes')
            ->addColumn('merchant_account_transaction_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('merchant_account_transaction_id', 'MerchantAccountTransactions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->update();
        $this->enableForeignKeyChecks();
    }
}
