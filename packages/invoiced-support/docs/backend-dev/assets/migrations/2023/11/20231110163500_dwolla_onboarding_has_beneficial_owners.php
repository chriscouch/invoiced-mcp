<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaOnboardingHasBeneficialOwners extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaOnboardingApplications')
            ->addColumn('has_beneficial_owners', 'boolean', ['null' => true, 'default' => null])
            ->update();
    }
}
