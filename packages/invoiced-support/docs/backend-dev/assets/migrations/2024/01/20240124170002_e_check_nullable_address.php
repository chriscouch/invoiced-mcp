<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ECheckNullableAddress extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('EChecks')
            ->changeColumn('address1', 'string', ['null' => true, 'default' => null])
            ->changeColumn('address2', 'string', ['null' => true, 'default' => null])
            ->changeColumn('city', 'string', ['null' => true, 'default' => null])
            ->changeColumn('state', 'string', ['null' => true, 'default' => null])
            ->changeColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->changeColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->update();
    }
}
