<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePaymentReference extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->addColumn('reference', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
