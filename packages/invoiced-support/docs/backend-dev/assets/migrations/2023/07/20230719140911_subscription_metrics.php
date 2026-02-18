<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionMetrics extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('MrrVersions');
        $this->addTenant($table);
        $table->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('last_updated', 'timestamp', ['null' => true, 'default' => null])
            ->create();

        $table = $this->table('MrrItems');
        $this->addTenant($table);
        $table->addColumn('version_id', 'integer')
            ->addColumn('customer_id', 'integer')
            ->addColumn('line_item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('subscription_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('plan_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('month', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('partial_month', 'boolean')
            ->addColumn('mrr', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('discount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addForeignKey('version_id', 'MrrVersions', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('line_item_id', 'LineItems', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('subscription_id', 'Subscriptions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('plan_id', 'Plans', 'internal_id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('item_id', 'CatalogItems', 'internal_id', ['delete' => 'set null', 'update' => 'cascade'])
            ->create();

        $table = $this->table('MrrMovements');
        $this->addTenant($table);
        $table->addColumn('version_id', 'integer')
            ->addColumn('customer_id', 'integer')
            ->addColumn('line_item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('subscription_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('credit_note_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('plan_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('item_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('movement_type', 'smallinteger')
            ->addColumn('month', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('mrr', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('discount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addForeignKey('version_id', 'MrrVersions', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('line_item_id', 'LineItems', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('subscription_id', 'Subscriptions', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('credit_note_id', 'CreditNotes', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('plan_id', 'Plans', 'internal_id', ['delete' => 'set null', 'update' => 'cascade'])
            ->addForeignKey('item_id', 'CatalogItems', 'internal_id', ['delete' => 'set null', 'update' => 'cascade'])
            ->create();
    }
}
