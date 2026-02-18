<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillRelationships extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorAdjustments')
            ->addColumn('bill_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('bill_id', 'Bills', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addColumn('vendor_credit_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->dropForeignKey('network_document_id')
            ->update();

        $this->table('VendorPaymentItems')
            ->addColumn('bill_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('bill_id', 'Bills', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addColumn('vendor_credit_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->dropForeignKey('network_document_id')
            ->update();

        $this->table('Bills')
            ->addColumn('due_date', 'date', ['null' => true, 'default' => null])
            ->update();
    }
}
