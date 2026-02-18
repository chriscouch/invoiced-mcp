<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePayment extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('FlywirePayments');
        $this->addTenant($table);
        $table->addColumn('payment_id', 'string')
            ->addColumn('amount_from', 'integer')
            ->addColumn('currency_from', 'string')
            ->addColumn('amount_to', 'integer')
            ->addColumn('currency_to', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('external_reference', 'string')
            ->addColumn('cancellation_reason', 'string', ['null' => true])
            ->addColumn('reason', 'string', ['null' => true])
            ->addColumn('reason_code', 'string', ['null' => true])
            ->addColumn('client_reason', 'string', ['null' => true])
            ->addColumn('reversed_type', 'string', ['null' => true])
            ->addColumn('reversed_amount', 'json', ['null' => true])
            ->addColumn('entity_id', 'string', ['null' => true])
            ->addTimestamps()
            ->addIndex('payment_id', ['unique' => true])
            ->create();
    }
}
