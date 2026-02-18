<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ChangeNetworkConnection extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('DELETE FROM NetworkConnections WHERE is_customer=0');

        $this->table('NetworkConnections')
            ->renameColumn('tenant_id', 'vendor_id')
            ->renameColumn('connected_to_id', 'customer_id')
            ->removeColumn('is_customer')
            ->removeColumn('is_vendor')
            ->addIndex(['vendor_id', 'customer_id'], ['unique' => true])
            ->update();
    }
}
