<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SessionCompanyRestrictionNullable extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ActiveSessions')
            ->changeColumn('company_restrictions', 'string', ['default' => '[]', 'null' => true])
            ->update();
    }
}
