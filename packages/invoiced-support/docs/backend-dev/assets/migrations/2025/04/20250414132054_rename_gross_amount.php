<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameGrossAmount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Payouts')
            ->renameColumn('net', 'gross_amount')
            ->update();
        $this->execute('UPDATE Payouts SET gross_amount=amount + pending_amount');
    }
}
