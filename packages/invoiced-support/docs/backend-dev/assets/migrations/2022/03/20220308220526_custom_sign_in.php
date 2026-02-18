<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomSignIn extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Settings')
            ->addColumn('customer_portal_auth_url', 'string', ['length' => 1000, 'null' => true])
            ->update();
    }
}
