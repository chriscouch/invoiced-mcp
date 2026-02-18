<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PayableAchModels extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('VendorBankAccounts');
        $this->addTenant($table);
        $table->addColumn('vendor_id', 'integer')
            ->addColumn('bank_name', 'string', ['length' => 30])
            ->addColumn('last4', 'string', ['length' => 4])
            ->addColumn('routing_number', 'string', ['length' => 9, 'null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('account_holder_type', 'enum', ['null' => true, 'values' => ['company', 'individual']])
            ->addColumn('account_holder_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('type', 'enum', ['null' => true, 'values' => ['checking', 'savings']])
            ->addColumn('account_number', 'text')
            ->addTimestamps()
            ->addForeignKey('vendor_id', 'Vendors', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();

        $this->table('Vendors')
            ->addColumn('bank_account_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('bank_account_id', 'VendorBankAccounts', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();

        $this->table('CompanyBankAccounts')
            ->addColumn('ach_file_format_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('ach_file_format_id', 'AchFileFormats', 'id')
            ->update();
    }
}
