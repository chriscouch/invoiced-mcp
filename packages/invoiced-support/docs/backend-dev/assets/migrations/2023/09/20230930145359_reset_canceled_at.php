<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ResetCanceledAt extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE Companies SET canceled_at=NULL WHERE canceled=0');
        $this->execute('UPDATE Companies SET canceled_at='.time().' WHERE canceled=1 AND canceled_at IS NULL');
    }
}
