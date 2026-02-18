<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class StartingBalance extends MultitenantModelMigration
{
    public function change()
    {
        $this->ensureInstant();
        $this->table('Transactions')
            ->changeColumn('type', 'enum', ['values' => [
                'charge',
                'payment',
                'refund',
                'adjustment',
                'document_adjustment',
            ]])
            ->update();
        $this->ensureInstantEnd();
    }
}
