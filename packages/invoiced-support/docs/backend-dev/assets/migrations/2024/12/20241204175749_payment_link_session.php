<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkSession extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentLinkSessions')
            ->drop()
            ->update();

        $table = $this->table('PaymentLinkSessions');
        $this->addTenant($table);
        $table->addColumn('payment_link_id', 'integer')
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('hash', 'string', ['length' => 64, 'null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('payment_link_id', 'PaymentLinks', 'id')
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addIndex(['hash', 'payment_link_id'])
            ->create();

        $this->table('PaymentLinks')
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->update();

        $this->table('PaymentLinkItems')
            ->removeColumn('currency')
            ->update();
    }
}
