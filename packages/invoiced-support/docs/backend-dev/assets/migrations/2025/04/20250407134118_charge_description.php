<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ChargeDescription extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Charges')
            ->addColumn('description', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
