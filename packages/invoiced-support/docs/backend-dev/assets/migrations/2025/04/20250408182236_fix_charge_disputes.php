<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixChargeDisputes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('UPDATE Charges SET disputed=1 WHERE id IN (SELECT charge_id FROM Disputes)');
    }
}
