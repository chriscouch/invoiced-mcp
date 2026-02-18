<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksOnlineMatchTaxRates extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('QuickBooksOnlineSyncProfiles')
            ->addColumn('match_tax_rates', 'boolean')
            ->update();
    }
}
