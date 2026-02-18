<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PayableDocumentAttachments extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('BillAttachments');
        $this->addTenant($table);
        $table
            ->addColumn('bill_id', 'integer')
            ->addColumn('file_id', 'integer')
            ->addIndex(['file_id', 'bill_id'], ['unique' => true])
            ->addForeignKey('bill_id', 'Bills', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('file_id', 'Files', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addTimestamps()
            ->create();

        $table = $this->table('VendorCreditAttachments');
        $this->addTenant($table);
        $table
            ->addColumn('vendor_credit_id', 'integer')
            ->addColumn('file_id', 'integer')
            ->addIndex(['file_id', 'vendor_credit_id'], ['unique' => true])
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('file_id', 'Files', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addTimestamps()
            ->create();
    }
}
