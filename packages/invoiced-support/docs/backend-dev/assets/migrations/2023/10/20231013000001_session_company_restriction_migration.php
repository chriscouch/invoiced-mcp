<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SessionCompanyRestrictionMigration extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('Update ActiveSessions set company_restrictions = null where company_restrictions = "[]"');
    }
}
