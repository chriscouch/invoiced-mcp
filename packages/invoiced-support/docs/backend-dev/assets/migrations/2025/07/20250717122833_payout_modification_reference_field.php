<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PayoutModificationReferenceField extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Payouts')
            ->addColumn('modification_reference', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
