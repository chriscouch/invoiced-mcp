<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceDisputes extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('DisputeReasons');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('enabled', 'boolean')
            ->addColumn('order', 'tinyinteger')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addTimestamps()
            ->create();

        $table = $this->table('InvoiceDisputes');
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer')
            ->addColumn('status', 'tinyinteger')
            ->addColumn('reason', 'integer', ['null' => true, 'default' => null])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('reason', 'DisputeReasons', 'id')
            ->create();

        $this->table('CustomerPortalSettings')
            ->addColumn('allow_invoice_disputes', 'boolean')
            ->update();
    }
}
