<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisputeFee extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('DisputeFees');
        $this->addTenant($table);
        $table->addColumn('gateway_id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('dispute_id', 'integer')
            ->addColumn('reason', 'string')
            ->addColumn('success', 'smallinteger')
            ->addTimestamps()
            ->addForeignKey('dispute_id', 'Disputes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
