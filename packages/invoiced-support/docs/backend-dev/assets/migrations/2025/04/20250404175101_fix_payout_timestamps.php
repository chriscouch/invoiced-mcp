<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixPayoutTimestamps extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Payouts')
            ->changeColumn('initiated_at', 'timestamp', ['default' => 0])
            ->update();
    }
}
