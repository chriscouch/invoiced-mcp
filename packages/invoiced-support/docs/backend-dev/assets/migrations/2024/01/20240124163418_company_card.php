<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CompanyCard extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CompanyCards');
        $this->addTenant($table);
        $table->addColumn('brand', 'string', ['length' => 30])
            ->addColumn('last4', 'string', ['length' => 4])
            ->addColumn('funding', 'string', ['length' => 30])
            ->addColumn('exp_month', 'integer', ['length' => 2])
            ->addColumn('exp_year', 'integer', ['length' => 4])
            ->addColumn('issuing_country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addColumn('gateway', 'string')
            ->addColumn('stripe_customer', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('stripe_payment_method', 'string', ['null' => true, 'default' => null, 'collation' => 'utf8_bin'])
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();

        $this->table('VendorPayments')
            ->addColumn('card_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('card_id', 'CompanyCards', 'id')
            ->update();
    }
}
