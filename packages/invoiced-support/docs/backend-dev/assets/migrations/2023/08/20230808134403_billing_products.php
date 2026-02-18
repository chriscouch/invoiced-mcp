<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillingProducts extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Products')
            ->addColumn('name', 'string')
            ->addIndex('name', ['unique' => true])
            ->create();

        $this->table('ProductFeatures')
            ->addColumn('product_id', 'integer')
            ->addColumn('feature', 'string')
            ->addIndex(['product_id', 'feature'], ['unique' => true])
            ->addForeignKey('product_id', 'Products', 'id')
            ->create();

        $table = $this->table('InstalledProducts');
        $this->addTenant($table);
        $table->addColumn('product_id', 'integer')
            ->addColumn('installed_on', 'timestamp')
            ->addForeignKey('product_id', 'Products', 'id')
            ->addIndex(['tenant_id', 'product_id'], ['unique' => true])
            ->create();

        $table = $this->table('ProductPricingPlans');
        $this->addTenant($table);
        $table->addColumn('product_id', 'integer')
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('annual', 'boolean')
            ->addColumn('custom_pricing', 'boolean')
            ->addColumn('effective_date', 'date')
            ->addColumn('posted_on', 'datetime')
            ->addForeignKey('product_id', 'Products', 'id')
            ->addIndex(['tenant_id', 'product_id', 'effective_date'], ['unique' => true])
            ->create();
    }
}
