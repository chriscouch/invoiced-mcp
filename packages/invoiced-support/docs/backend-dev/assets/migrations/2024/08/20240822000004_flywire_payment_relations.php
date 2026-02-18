<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePaymentRelations extends MultitenantModelMigration
{
    public function up(): void
    {
        $table = $this->table('FlywireDisbursements');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('flywire_disbursement_id', 'string')
            ->addIndex('flywire_disbursement_id', ['unique' => true])
            ->create();

        $this->execute('DELETE FROM FlywirePayments');

        $this->table('FlywirePayments')
            ->addColumn('ar_payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('ar_payment_id', 'Payments', 'id')
            ->addColumn('merchant_account_id', 'integer')
            ->addForeignKey('merchant_account_id', 'MerchantAccounts', 'id')
            ->addColumn('disbursement_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('disbursement_id', 'FlywireDisbursements', 'id')
            ->update();
    }
}
