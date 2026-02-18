<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentDwollaPayment extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->addColumn('dwolla_payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('dwolla_payment_id', 'DwollaPayments', 'id')
            ->update();
    }
}
