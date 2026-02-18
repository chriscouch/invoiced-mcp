<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountTransactionNotification extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('MerchantAccountTransactionNotifications');
        $this->addTenant($table);
        $table
            ->addColumn('merchant_account_transaction_id', 'integer')
            ->addColumn('notified_on', 'date')
            ->addTimestamps()
            ->addForeignKey('merchant_account_transaction_id', 'MerchantAccountTransactions', 'id')
            ->create();
    }
}