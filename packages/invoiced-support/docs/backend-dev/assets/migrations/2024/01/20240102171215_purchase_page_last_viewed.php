<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PurchasePageLastViewed extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PurchasePageContexts')
            ->addColumn('last_viewed', 'timestamp', ['null' => true, 'default' => null])
            ->changeColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
