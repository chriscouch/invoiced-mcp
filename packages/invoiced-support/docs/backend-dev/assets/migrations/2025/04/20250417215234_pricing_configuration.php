<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PricingConfiguration extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PricingConfigurations')
            ->addColumn('merchant_account', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('card_variable_fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('card_international_added_variable_fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('card_fixed_fee', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('card_interchange_passthrough', 'boolean')
            ->addColumn('ach_variable_fee', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('ach_max_fee', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('ach_fixed_fee', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('chargeback_fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('override_split_configuration_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('split_configuration_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('hash', 'string')
            ->addTimestamps()
            ->addIndex(['hash'], ['unique' => true])
            ->create();
    }
}
