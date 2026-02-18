<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('MerchantAccounts')
            ->addColumn('settings', 'json', ['default' => '{}', 'null' => false])
            ->update();
    }
}