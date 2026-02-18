<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAffirmCapturesNonUnique extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAffirmCaptures')
            ->removeIndex(['payment_flow_id'])
            ->addIndex(['payment_flow_id'])
            ->update();
    }
}
