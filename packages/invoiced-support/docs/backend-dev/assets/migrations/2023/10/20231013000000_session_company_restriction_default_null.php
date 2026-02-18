<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SessionCompanyRestrictionDefaultNull extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ActiveSessions')
            ->changeColumn('company_restrictions', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
