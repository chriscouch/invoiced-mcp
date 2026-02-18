<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TaskApprovalFks extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Tasks');
        $table->dropForeignKey('bill_id')
            ->addForeignKey('bill_id', 'Bills', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->dropForeignKey('vendor_credit_id')
            ->addForeignKey('vendor_credit_id', 'VendorCredits', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->update();
    }
}
