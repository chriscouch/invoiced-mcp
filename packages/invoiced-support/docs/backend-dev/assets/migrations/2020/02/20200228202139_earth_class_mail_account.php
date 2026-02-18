<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EarthClassMailAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EarthClassMailAccounts');
        $this->addTenant($table);
        $table->addColumn('api_key_enc', 'string', ['length' => 678])
            ->addColumn('inbox_id', 'string')
            ->addColumn('last_retrieved_data_at', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
