<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AmexPricing extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PricingConfigurations')
            ->addColumn('amex_interchange_variable_markup', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->update();
    }
}
