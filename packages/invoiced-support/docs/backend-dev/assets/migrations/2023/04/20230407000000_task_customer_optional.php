<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TaskCustomerOptional extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Tasks');
        $table->changeColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
