<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLink extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentLinks')
            ->rename('PaymentLinkSessions')
            ->update();

        $table = $this->table('PaymentLinks');
        $this->addTenant($table);
        $table->addColumn('customer', 'integer', ['null' => true, 'default' => null])
            ->addColumn('reusable', 'boolean')
            ->addColumn('status', 'tinyinteger')
            ->addColumn('after_completion_url', 'string', ['length' => 5000, 'null' => true, 'default' => null])
            ->addColumn('client_id', 'string', ['length' => 48])
            ->addColumn('client_id_exp', 'integer')
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('client_id', ['unique' => true])
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('PaymentLinkItems');
        $this->addTenant($table);
        $table->addColumn('payment_link_id', 'integer')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('payment_link_id', 'PaymentLinks', 'id')
            ->create();
    }
}
