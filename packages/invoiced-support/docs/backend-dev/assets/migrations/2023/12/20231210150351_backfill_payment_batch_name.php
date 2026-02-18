<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillPaymentBatchName extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE VendorPaymentBatches SET name="Payment Batch"');
    }
}
