<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountTransaction extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('MerchantAccountTransactions');
        $this->addTenant($table);
        $table
            ->addColumn('merchant_account_id', 'integer')
            ->addColumn('type', 'smallinteger')
            ->addColumn('reference', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('fee_details', 'json')
            ->addColumn('net', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('available_on', 'date')
            ->addColumn('description', 'string')
            ->addColumn('source_type', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('source_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payout_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['merchant_account_id', 'reference'], ['unique' => true])
            ->addTimestamps()
            ->addForeignKey('merchant_account_id', 'MerchantAccounts', 'id')
            ->create();
    }
}
