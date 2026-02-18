<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPaymentBatchAutoNumber extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AutoNumberSequences')
            ->changeColumn('type', 'enum', ['values' => ['credit_note', 'customer', 'estimate', 'invoice', 'vendor', 'vendor_payment', 'vendor_payment_batch', 'customer_payment_batch']])
            ->update();

        $this->execute('INSERT INTO AutoNumberSequences (tenant_id, type, template, next) SELECT id, "customer_payment_batch", "BAT-%05d", 1 FROM Companies');
    }
}
