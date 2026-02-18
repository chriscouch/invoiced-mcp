<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayCustomizablePercent extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('WePayData')
            ->addColumn('credit_card_percent_fee', 'smallinteger', ['null' => true, 'default' => null])
            ->update();
    }
}
