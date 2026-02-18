<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixEmailVerificationColumn extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanyEmailAddresses')
            ->renameColumn('email_verification_token', 'token')
            ->update();
    }
}
