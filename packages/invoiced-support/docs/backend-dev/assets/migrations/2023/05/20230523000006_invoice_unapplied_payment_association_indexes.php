<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceUnappliedPaymentAssociationIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->execute("SET SESSION alter_algorithm='INPLACE'");
        $this->table('InvoiceUnappliedPaymentAssociations')
            ->addIndex(['group_id'])
            ->update();
        $this->execute("SET SESSION alter_algorithm='DEFAULT'");
    }
}
