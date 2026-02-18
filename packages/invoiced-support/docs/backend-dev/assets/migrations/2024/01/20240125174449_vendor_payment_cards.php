<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentCards extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->renameColumn('vendor_bank_account_id', 'bank_account_id')
            ->update();

        $this->table('VendorPaymentBatches')
            ->renameColumn('vendor_bank_account_id', 'bank_account_id')
            ->update();

        $this->table('VendorPaymentBatches')
            ->addColumn('card_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('card_id', 'CompanyCards', 'id')
            ->changeColumn('bank_account_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
