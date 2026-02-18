<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MicrosoftClaimedId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Users')
            ->addColumn('microsoft_claimed_id', 'string', ['default' => null, 'null' => true])
            ->addIndex('microsoft_claimed_id', ['unique' => true])
            ->update();
    }
}
