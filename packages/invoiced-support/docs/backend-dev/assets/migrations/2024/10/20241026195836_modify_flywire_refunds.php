<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ModifyFlywireRefunds extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('FlywireRefundBundles');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('bundle_id', 'string')
            ->addIndex('bundle_id', ['unique' => true])
            ->create();

        $this->table('FlywireRefunds')
            ->removeColumn('bundle_id')
            ->update();

        $this->table('FlywireRefunds')
            ->addColumn('bundle_id', 'integer', ['null' => true, 'default' => null])
            ->changeColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount_to', 'integer')
            ->addColumn('currency_to', 'string', ['length' => 3])
            ->addForeignKey('bundle_id', 'FlywireRefundBundles', 'id')
            ->update();
    }
}
