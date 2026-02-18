<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VerificationModels extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Companies')
            ->changeColumn('type', 'enum', ['values' => ['company', 'person', 'government', 'non_profit'], 'null' => true, 'default' => null])
            ->update();

        $table = $this->table('CompanyEmailAddresses');
        $this->addTenant($table);
        $table->addColumn('email', 'string')
            ->addColumn('email_verification_token', 'string', ['length' => 24])
            ->addColumn('code', 'string', ['length' => 6])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        $table = $this->table('CompanyAddresses');
        $this->addTenant($table);
        $table->addColumn('address1', 'string')
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string')
            ->addColumn('state', 'string')
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2])
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        $table = $this->table('CompanyTaxIds');
        $this->addTenant($table);
        $table->addColumn('country', 'string', ['length' => 2])
            ->addColumn('irs_code', 'integer', ['null' => true, 'default' => null])
            ->addColumn('tax_id', 'text')
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
