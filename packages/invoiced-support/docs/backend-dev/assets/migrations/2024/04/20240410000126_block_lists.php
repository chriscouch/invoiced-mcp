<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BlockLists extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BlockListPhoneNumbers')
            ->addColumn('phone', 'string')
            ->addTimestamps()
            ->addIndex('phone', ['unique' => true])
            ->create();

        $this->table('BlockListTaxIds')
            ->addColumn('tax_id_hash', 'string')
            ->addTimestamps()
            ->addIndex('tax_id_hash', ['unique' => true])
            ->create();
    }
}
