<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateEnterprisePlans extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT id,"enterprise",1 as `value` FROM Companies WHERE plan="enterprise"');
        $this->execute('UPDATE Companies SET plan="custom" WHERE plan="enterprise"');
    }
}
