<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywireTargetMor extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT INTO Features (tenant_id, feature, enabled) SELECT id AS tenant_id, "flywire_mor_target" as feature, 1 as enabled FROM Companies WHERE billing_profile_id IN (550,654,280,213,192920,120,7181,196,1216,286,22,168,6953,888,209306,252,140,444,163,551,891,1047,1590,54,185423,337,281,41,731,836,765,197,266,2282,212769,57,19,5446,9,153,15,172,195559)');
    }
}
