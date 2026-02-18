<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixDisputeAmounts extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE Disputes SET amount = amount / 100');
    }
}
