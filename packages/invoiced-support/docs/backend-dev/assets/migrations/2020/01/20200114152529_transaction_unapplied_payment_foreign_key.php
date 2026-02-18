<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionUnappliedPaymentForeignKey extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Transactions')
            ->addColumn('unapplied_payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('unapplied_payment_id', 'UnappliedPayments', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
