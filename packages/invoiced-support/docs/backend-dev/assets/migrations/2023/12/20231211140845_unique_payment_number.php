<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniquePaymentNumber extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
        $this->table('VendorPaymentBatches')
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->update();
    }
}
