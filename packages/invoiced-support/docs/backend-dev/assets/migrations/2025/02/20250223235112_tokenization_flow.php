<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TokenizationFlow extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('TokenizationFlows');
        $this->addTenant($table);
        $table->addColumn('identifier', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('payment_method', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('payment_source_type', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('payment_source_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('make_payment_source_default', 'boolean')
            ->addColumn('return_url', 'string', ['length' => 5000, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('initiated_from', 'smallinteger')
            ->addColumn('sign_up_page_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('canceled_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->addForeignKey('customer_id', 'Customers', 'id')
            ->addForeignKey('sign_up_page_id', 'SignUpPages', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();
    }
}
