<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAccountReference extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('TRUNCATE TABLE AdyenAccounts');
        $this->table('AdyenAccounts')
            ->addColumn('terms_of_service_acceptance_version', 'string', ['null' => true, 'default' => null])
            ->removeColumn('payment_methods')
            ->renameColumn('store_reference', 'reference')
            ->update();
    }
}
