<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywireRenameFields extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywireDisbursements')
            ->renameColumn('flywire_disbursement_id', 'disbursement_id')
            ->update();

        $this->table('FlywireRefunds')
            ->removeColumn('payment_id')
            ->update();

        $this->table('FlywireRefunds')
            ->addColumn('payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('payment_id', 'FlywirePayments', 'id')
            ->update();
    }
}
