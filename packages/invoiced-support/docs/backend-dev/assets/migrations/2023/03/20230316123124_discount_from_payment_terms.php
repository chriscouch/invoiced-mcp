<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DiscountFromPaymentTerms extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('AppliedRates')
            ->addColumn('from_payment_terms', 'boolean')
            ->update();
        $this->ensureInstantEnd();
    }
}
