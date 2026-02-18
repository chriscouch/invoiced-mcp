<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BiggerClientId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Customers')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('Invoices')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('Estimates')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('CreditNotes')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('Payments')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('Transactions')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('SignUpPages')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->table('Subscriptions')
            ->changeColumn('client_id', 'string', ['length' => 48])
            ->update();
        $this->ensureInstantEnd();
    }
}
