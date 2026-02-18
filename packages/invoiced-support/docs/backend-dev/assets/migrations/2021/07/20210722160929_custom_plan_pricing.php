<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomPlanPricing extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Plans')
            ->changeColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true])
            ->changeColumn('pricing_mode', 'enum', ['values' => ['per_unit', 'tiered', 'volume', 'custom'], 'default' => 'per_unit'])
            ->update();

        $this->table('Subscriptions')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true])
            ->update();

        $this->table('SubscriptionAddons')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true])
            ->update();
    }
}
