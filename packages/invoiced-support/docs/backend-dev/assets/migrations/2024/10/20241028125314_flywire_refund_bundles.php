<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywireRefundBundles extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywirePayments')
            ->addColumn('recipient_id', 'string')
            ->update();

        $this->table('FlywireRefunds')
            ->addColumn('recipient_id', 'string')
            ->addColumn('initiated_at', 'timestamp')
            ->update();

        $this->table('FlywireRefundBundles')
            ->addColumn('recipient_id', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('initiated_at', 'timestamp')
            ->addColumn('marked_for_approval', 'boolean')
            ->addColumn('amount', 'integer')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('recipient_date', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('recipient_bank_reference', 'string', ['null' => true, 'default' => null])
            ->addColumn('recipient_account_number', 'string', ['null' => true, 'default' => null])
            ->addColumn('recipient_amount', 'integer', ['null' => true, 'default' => null])
            ->addColumn('recipient_currency', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
