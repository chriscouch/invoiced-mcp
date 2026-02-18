<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveBrokenUserLinks extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('DELETE FROM UserLinks
            where type = "verify_email" AND user_id in (
                select user_id from Members where tenant_id in (
                    select company_id from CompanySamlSettings where disable_non_sso = 1
                )
            )
       ');
    }
}
