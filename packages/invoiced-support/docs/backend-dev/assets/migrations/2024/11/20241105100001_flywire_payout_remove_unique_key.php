<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePayoutRemoveUniqueKey extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayouts')
            ->addIndex(['payout_id', 'tenant_id'], ['unique' => true])
            ->addIndex(['payment_id', 'disbursement_id', 'tenant_id'], ['unique' => true])
            ->removeIndex('payout_id')
            ->removeIndex(['payment_id', 'disbursement_id'])
            ->update();
    }
}
