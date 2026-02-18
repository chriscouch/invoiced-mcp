<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentItemsType extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPaymentItems')
            ->addColumn('type', 'integer', ['default' => '1'])
            ->update();
    }
}
