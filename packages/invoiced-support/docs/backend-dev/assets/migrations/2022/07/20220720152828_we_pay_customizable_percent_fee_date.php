<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayCustomizablePercentFeeDate extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('WePayData')
            ->addColumn('fee_effective_date', 'date', ['null' => true, 'default' => null])
            ->update();
    }
}
