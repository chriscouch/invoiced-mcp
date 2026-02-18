<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SessionCompanyRestriction extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ActiveSessions')
            ->addColumn('company_restrictions', 'string', ['default' => '[]'])
            ->update();

        $this->table('CompanySamlSettings')
            ->addColumn('disable_sp_initiated', 'tinyinteger')
            ->addColumn('disable_non_sso', 'tinyinteger')
            ->removeIndex('domain')
            ->update();
    }
}
