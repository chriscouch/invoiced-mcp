<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddDataTrackingToPlaidBankAccountLinks extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PlaidBankAccountLinks')
            ->addColumn('data_starts_at', 'integer')
            ->addColumn('last_retrieved_data_at', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
