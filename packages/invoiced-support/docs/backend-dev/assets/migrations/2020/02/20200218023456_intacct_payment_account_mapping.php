<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctPaymentAccountMapping extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('payment_accounts', 'text')
            ->update();

        $this->execute('UPDATE IntacctSyncProfiles SET payment_accounts="[]"');
    }
}
