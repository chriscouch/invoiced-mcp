<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AdyenAccounts');
        $this->addTenant($table);
        $table->addColumn('legal_entity_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('business_line_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('store_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('store_reference', 'string', ['null' => true, 'default' => null])
            ->addColumn('account_holder_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('balance_account_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('payment_methods', 'string')
            ->addTimestamps()
            ->create();
    }
}
