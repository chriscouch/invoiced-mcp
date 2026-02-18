<?php

final class StripeCustomer extends App\Core\Multitenant\MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('StripeCustomers', ['id' => false, 'primary_key' => ['customer_id']]);
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('stripe_id', 'string')
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('stripe_id')
            ->create();
    }
}
