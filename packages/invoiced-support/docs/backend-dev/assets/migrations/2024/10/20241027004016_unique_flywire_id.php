<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueFlywireId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->removeIndex('payment_id')
            ->addIndex(['tenant_id', 'payment_id'], ['unique' => true])
            ->update();
        $this->table('FlywireDisbursements')
            ->removeIndex('flywire_disbursement_id')
            ->addIndex(['tenant_id', 'flywire_disbursement_id'], ['unique' => true])
            ->update();

        $this->table('FlywireRefunds')
            ->removeIndex('refund_id')
            ->addIndex(['tenant_id', 'refund_id'], ['unique' => true])
            ->update();

        $this->table('FlywireRefundBundles')
            ->removeIndex('bundle_id')
            ->addIndex(['tenant_id', 'bundle_id'], ['unique' => true])
            ->update();
    }
}
