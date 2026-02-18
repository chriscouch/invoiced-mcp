<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaPayment extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaPayments')
            ->addColumn('from_company_id', 'integer', ['null' => true])
            ->addColumn('to_company_id', 'integer', ['null' => true])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('dwolla_transfer_id', 'string')
            ->addColumn('dwolla_correlation_id', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('ach_id', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('dwolla_transfer_id')
            ->addIndex('dwolla_correlation_id')
            ->addForeignKey('from_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addForeignKey('to_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->create();
    }
}
