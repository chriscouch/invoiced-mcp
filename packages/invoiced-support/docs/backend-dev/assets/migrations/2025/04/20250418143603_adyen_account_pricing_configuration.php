<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccountPricingConfiguration extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenAccounts')
            ->addColumn('pricing_configuration_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('pricing_configuration_id', 'PricingConfigurations', 'id')
            ->update();
    }
}
