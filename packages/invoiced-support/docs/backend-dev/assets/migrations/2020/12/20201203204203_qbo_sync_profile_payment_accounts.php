<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QboSyncProfilePaymentAccounts extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('payment_accounts', 'text')
            ->update();

        $this->execute('UPDATE QuickBooksOnlineSyncProfiles SET payment_accounts="[]"');
    }
}
