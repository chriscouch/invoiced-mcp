<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameUnappliedPaymentToPayment extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('UnappliedPayments')
            ->rename('Payments')
            ->update();

        $this->table('Attachments')
            ->changeColumn('parent_type', 'enum', ['values' => ['comment', 'credit_note', 'estimate', 'invoice', 'payment', 'email', 'customer']])
            ->update();

        // Needed with 10.5.8 until this is fixed: https://jira.mariadb.org/browse/MDEV-22775
        $this->disableForeignKeyChecks();
        $this->execute('ALTER TABLE Transactions CHANGE COLUMN unapplied_payment_id payment_id INT NULL');
        $this->enableForeignKeyChecks();
    }
}
