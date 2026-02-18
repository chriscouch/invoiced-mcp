<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaBeneficialOwners extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('DwollaBeneficialOwners');
        $this->addTenant($table);
        $table->addColumn('first_name', 'string')
            ->addColumn('last_name', 'string')
            ->addColumn('date_of_birth', 'text')
            ->addColumn('address1', 'string')
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('address3', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string')
            ->addColumn('state', 'string')
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string')
            ->addColumn('ssn', 'text', ['null' => true, 'default' => null])
            ->addColumn('passport_number', 'text', ['null' => true, 'default' => null])
            ->addColumn('dwolla_beneficial_owner_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('status', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
