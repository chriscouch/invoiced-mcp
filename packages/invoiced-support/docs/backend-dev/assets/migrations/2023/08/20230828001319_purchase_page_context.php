<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PurchasePageContext extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PurchasePageContexts')
            ->addColumn('identifier', 'string')
            ->addColumn('billing_profile_id', 'integer')
            ->addColumn('expiration_date', 'date')
            ->addColumn('reason', 'smallinteger')
            ->addColumn('tenant_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('sales_rep', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2])
            ->addColumn('payment_terms', 'smallinteger')
            ->addColumn('changeset', 'text')
            ->addColumn('activation_fee', 'decimal', ['precision' => 20, 'scale' => 2, 'null' => true, 'default' => null])
            ->addColumn('note', 'text', ['null' => true, 'default' => null])
            ->addColumn('completed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('completed_by_ip', 'string', ['null' => true, 'default' => null])
            ->addColumn('completed_by_name', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->addIndex(['expiration_date', 'completed_at'])
            ->addForeignKey('billing_profile_id', 'BillingProfiles', 'id')
            ->addForeignKey('tenant_id', 'Companies', 'id', ['delete' => 'set null', 'update' => 'cascade'])
            ->create();

        $this->table('PurchaseParityConversionRates')
            ->addColumn('country', 'string', ['length' => 2])
            ->addColumn('year', 'smallinteger')
            ->addColumn('conversion_rate', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addIndex(['country', 'year'], ['unique' => true])
            ->create();
    }
}
