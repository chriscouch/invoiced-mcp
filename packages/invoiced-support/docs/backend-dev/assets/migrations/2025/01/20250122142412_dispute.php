<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Dispute extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Disputes');
        $this->addTenant($table);
        $table->addColumn('charge_id', 'integer')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('gateway', 'string')
            ->addColumn('gateway_id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('reason', 'string')
            ->addColumn('status', 'smallinteger')
            ->addTimestamps()
            ->addForeignKey('charge_id', 'Charges', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
