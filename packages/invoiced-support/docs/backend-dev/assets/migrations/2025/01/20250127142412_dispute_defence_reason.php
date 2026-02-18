<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisputeDefenceReason extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Disputes')
            ->addColumn('defense_reason', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
