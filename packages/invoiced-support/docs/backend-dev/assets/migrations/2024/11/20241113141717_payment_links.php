<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinks extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('PaymentLinks');
        $this->addTenant($table);
        $table->addColumn('hash', 'string', ['length' => 64])
            ->addIndex(['tenant_id', 'hash'], ['unique' => true])
            ->create();

        $this->table('CustomerPortalSettings')
            ->addColumn('payment_links', 'boolean')
            ->update();
    }
}
