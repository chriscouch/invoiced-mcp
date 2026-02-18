<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentFixes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->changeColumn('reference', 'string', ['null' => true, 'default' => null])
            ->changeColumn('notes', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
