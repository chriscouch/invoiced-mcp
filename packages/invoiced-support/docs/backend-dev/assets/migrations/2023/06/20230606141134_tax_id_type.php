<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class TaxIdType extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CanceledCompanies')
            ->changeColumn('type', 'enum', ['values' => ['company', 'person', 'government', 'non_profit'], 'null' => true, 'default' => null])
            ->update();

        $this->table('CompanyTaxIds')
            ->addColumn('tax_id_type', 'smallinteger')
            ->update();
    }
}
