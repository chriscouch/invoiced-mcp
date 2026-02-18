<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkFix extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentLinks')
            ->renameColumn('customer', 'customer_id')
            ->update();
    }
}
