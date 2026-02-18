<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroPaymentAccounts extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('payment_accounts', 'text')
            ->update();

        $this->execute('UPDATE XeroSyncProfiles SET payment_accounts="[]"');
    }
}
