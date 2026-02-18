<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Payout extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Payouts');
        $this->addTenant($table);
        $table
            ->addColumn('merchant_account_id', 'integer')
            ->addColumn('reference', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('description', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('bank_account_name', 'string')
            ->addColumn('statement_descriptor', 'string', ['null' => true, 'default' => null])
            ->addColumn('initiated_at', 'timestamp')
            ->addColumn('arrival_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('failure_message', 'string', ['null' => true, 'default' => null])
            ->addColumn('merchant_account_transaction_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('merchant_account_id', 'MerchantAccounts', 'id')
            ->addForeignKey('merchant_account_transaction_id', 'MerchantAccountTransactions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addIndex(['merchant_account_id', 'reference'], ['unique' => true])
            ->create();

        $this->table('MerchantAccountTransactions')
            ->addForeignKey('payout_id', 'Payouts', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->update();
    }
}
