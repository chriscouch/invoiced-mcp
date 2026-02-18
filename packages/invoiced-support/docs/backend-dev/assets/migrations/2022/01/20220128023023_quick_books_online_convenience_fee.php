<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksOnlineConvenienceFee extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('write_convenience_fees', 'boolean')
            ->update();

        $this->execute('UPDATE QuickBooksOnlineSyncProfiles SET write_convenience_fees=write_invoices');
    }
}
