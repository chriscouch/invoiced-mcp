<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveNetworkFlag extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('DELETE FROM Features WHERE feature="network" AND enabled=1');
    }
}
