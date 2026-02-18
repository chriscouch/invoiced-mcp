<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillLineItem extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('BillLineItems');
        $this->addTenant($table);
        $table->addColumn('bill_id', 'integer')
            ->addColumn('description', 'string')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('order', 'integer')
            ->addTimestamps()
            ->addForeignKey('bill_id', 'Bills', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();

        $table = $this->table('VendorCreditLineItems');
        $this->addTenant($table);
        $table->addColumn('vendor_credit_id', 'integer')
            ->addColumn('description', 'string')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('order', 'integer')
            ->addTimestamps()
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();

        $this->table('Bills')
            ->addColumn('source', 'smallinteger')
            ->update();

        $this->table('VendorCredits')
            ->addColumn('source', 'smallinteger')
            ->update();

        $this->execute('UPDATE Bills SET source=1');
        $this->execute('UPDATE VendorCredits SET source=1');
    }
}
