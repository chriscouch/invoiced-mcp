<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillPaymentBatchTotal extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE VendorPaymentBatches SET total=(SELECT SUM(amount) FROM VendorPaymentBatchBills WHERE vendor_payment_batch_id=VendorPaymentBatches.id)');
        $this->execute('UPDATE VendorPaymentBatches SET currency=(SELECT currency FROM Companies WHERE id=VendorPaymentBatches.tenant_id)');
    }
}
