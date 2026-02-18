<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TokenizationAndPaymentFlowsRelations extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PaymentFlows')
            ->dropForeignKey('customer_id')
            ->addForeignKey('customer_id', 'Customers', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();

        $this->table('TokenizationFlows')
            ->dropForeignKey('customer_id')
            ->addForeignKey('customer_id', 'Customers', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->update();
    }
}
