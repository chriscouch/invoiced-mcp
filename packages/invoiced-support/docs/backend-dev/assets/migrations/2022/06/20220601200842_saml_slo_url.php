<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SamlSloUrl extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanySamlSettings')
            ->addColumn('slo_url', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
