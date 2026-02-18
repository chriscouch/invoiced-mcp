<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Saml1FeatureFlag extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute("INSERT INTO Features SELECT NULL, company_id, 'saml1', 1 FROM CompanySamlSettings");
    }
}
