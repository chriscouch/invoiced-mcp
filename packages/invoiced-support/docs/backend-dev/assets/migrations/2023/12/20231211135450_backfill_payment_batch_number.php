<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillPaymentBatchNumber extends MultitenantModelMigration
{
    public function up(): void
    {
        $tables = [
            ['VendorPaymentBatches', 'vendor_payment_batch', 'BAT-%05d'],
            ['VendorPayments', 'vendor_payment', 'PAY-%05d'],
        ];

        foreach ($tables as $input) {
            [$tablename, $objectType, $template] = $input;
            $tenantIds = $this->fetchAll('SELECT DISTINCT tenant_id FROM '.$tablename);
            foreach ($tenantIds as $row) {
                $tenantId = $row['tenant_id'];
                // Fetch the next number
                $row2 = $this->fetchRow('SELECT next FROM AutoNumberSequences WHERE tenant_id='.$tenantId.' AND type="'.$objectType.'"');
                $nextNumber = $row2['next'];

                $hasMore = true;
                while ($hasMore) {
                    $recordIds = $this->fetchAll('SELECT id FROM '.$tablename.' WHERE tenant_id='.$tenantId.' AND number="" ORDER BY id LIMIT 1000');
                    foreach ($recordIds as $row3) {
                        $recordId = $row3['id'];
                        $number = sprintf($template, $nextNumber);
                        $this->execute('UPDATE '.$tablename.' SET number="'.$number.'" WHERE id='.$recordId);
                        ++$nextNumber;
                    }

                    $hasMore = count($recordIds) > 0;
                }

                // Save back the final number
                $this->execute('UPDATE AutoNumberSequences SET next='.$nextNumber.' WHERE tenant_id='.$tenantId.' AND type="'.$objectType.'"');
            }
        }
    }
}
