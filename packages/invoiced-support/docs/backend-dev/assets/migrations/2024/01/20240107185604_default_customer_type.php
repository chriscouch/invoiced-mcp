<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DefaultCustomerType extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountsReceivableSettings')
            ->addColumn('default_customer_type', 'enum', ['values' => ['company', 'person', 'government', 'non_profit'], 'default' => 'company'])
            ->update();
    }
}
