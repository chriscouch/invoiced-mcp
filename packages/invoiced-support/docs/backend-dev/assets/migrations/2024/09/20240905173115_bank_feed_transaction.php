<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BankFeedTransaction extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('BankFeedTransactions');
        $this->addTenant($table);
        $table->addColumn('transaction_id', 'string')
            ->addColumn('date', 'date')
            ->addColumn('name', 'string')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('check_number', 'string', ['null' => true, 'default' => null])
            ->addColumn('merchant_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_reference_number', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_ppd_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_payee', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_by_order_of', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_payer', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_method', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_processor', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_reason', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_channel', 'string', ['null' => true, 'default' => null])
            ->addColumn('cash_application_bank_account_id', 'integer')
            ->addTimestamps()
            ->addForeignKey('cash_application_bank_account_id', 'CashApplicationBankAccounts', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addIndex(['transaction_id'])
            ->create();

        $this->disableForeignKeyChecks();
        $this->table('Payments')
            ->addColumn('bank_feed_transaction_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('bank_feed_transaction_id', 'BankFeedTransactions', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
        $this->enableForeignKeyChecks();
    }
}
