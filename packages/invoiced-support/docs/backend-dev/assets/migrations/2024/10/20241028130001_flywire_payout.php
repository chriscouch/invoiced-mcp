<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywirePayout extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('FlywirePayouts');
        $this->addTenant($table);
        $table->addColumn('payout_id', 'string')
            ->addColumn('payment_id', 'integer')
            ->addColumn('disbursement_id', 'integer')
            ->addColumn('status_text', 'string')
            ->addColumn('currency', 'string')
            ->addColumn('amount', 'integer')
            ->addTimestamps()
            ->addIndex('payout_id', ['unique' => true])
            ->addIndex(['payment_id', 'disbursement_id'], ['unique' => true])
            ->addForeignKey('payment_id', 'FlywirePayments', 'id')
            ->addForeignKey('disbursement_id', 'FlywireDisbursements', 'id')
            ->create();

        $this->table('FlywirePayments')
            ->dropForeignKey('disbursement_id')
            ->removeColumn('disbursement_id')
            ->update();

        $this->table('FlywireDisbursements')
            ->addColumn('status_text', 'string')
            ->addColumn('destination_code', 'string')
            ->addColumn('delivered_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('bank_account_number', 'string')
            ->addColumn('amount', 'integer')
            ->addColumn('currency', 'string')
            ->update();

        $this->table('FlywireRefunds')
            ->addColumn('disbursement_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('disbursement_id', 'FlywireDisbursements', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
    }
}
