<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EChecks extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('EChecks');
        $this->addTenant($table);
        $table->addColumn('hash', 'string', ['length' => 64])
            ->addColumn('bill_id', 'integer')
            ->addColumn('payment_id', 'integer')
            ->addColumn('account_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('viewed', 'tinyinteger')
            ->addColumn('address1', 'string')
            ->addColumn('address2', 'string')
            ->addColumn('city', 'string')
            ->addColumn('state', 'string')
            ->addColumn('postal_code', 'string')
            ->addColumn('country', 'string', ['length' => 2])
            ->addColumn('email', 'string')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('check_number', 'integer')
            ->addColumn('signature', 'string', ['length' => 64])
            ->addForeignKey('bill_id', 'Bills', 'id')
            ->addForeignKey('payment_id', 'VendorPayments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('account_id', 'VendorBankAccounts', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addTimestamps()
            ->create();

        $table = $this->table('VendorPaymentAttachments');
        $this->addTenant($table);
        $table
            ->addColumn('vendor_payment_id', 'integer')
            ->addColumn('file_id', 'integer')
            ->addIndex(['file_id', 'vendor_payment_id'], ['unique' => true])
            ->addForeignKey('vendor_payment_id', 'VendorPayments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('file_id', 'Files', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addTimestamps()
            ->create();

        $this->table('Vendors')
            ->addColumn('address1', 'string', ['null' => true, 'default' => null])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->update();

        $this->table('VendorBankAccounts')
            ->addColumn('signature', 'text')
            ->addColumn('plaid_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('plaid_id', 'PlaidBankAccountLinks', 'id', ['update' => 'set null', 'delete' => 'set null'])
            ->update();
    }
}
