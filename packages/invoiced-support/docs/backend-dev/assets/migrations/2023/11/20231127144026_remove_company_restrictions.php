<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveCompanyRestrictions extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ActiveSessions')
            ->removeColumn('company_restrictions')
            ->update();
    }
}
