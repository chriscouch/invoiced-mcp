<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationErrorDescription extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ReconciliationErrors')
            ->addColumn('description', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
