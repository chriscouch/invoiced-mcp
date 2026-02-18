<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentInstructions extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentInstructions');
        $this->addTenant($table);
        $table->addColumn('payment_method_id', 'string', ['length' => 32])
            ->addColumn('meta', 'text')
            ->addColumn('country', 'string', ['length' => 2])
            ->addIndex(['tenant_id', 'payment_method_id', 'country'], ['unique' => true])
            ->create();
    }
}
