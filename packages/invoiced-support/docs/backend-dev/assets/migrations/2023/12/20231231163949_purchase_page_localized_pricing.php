<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PurchasePageLocalizedPricing extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PurchasePageContexts')
            ->addColumn('localized_pricing', 'boolean')
            ->update();
    }
}
