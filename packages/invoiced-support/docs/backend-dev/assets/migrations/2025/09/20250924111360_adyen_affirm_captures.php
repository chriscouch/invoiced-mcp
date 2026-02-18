<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenAffirmCaptures extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AdyenAffirmCaptures');
        if (!$table->exists()) {
            $this->addTenant($table);
            $table->addColumn('payment_flow_id', 'integer')
                ->addColumn('payment_id', 'integer', ['null' => true, 'default' => null])
                ->addColumn('status', 'integer')
                ->addColumn('reference', 'string', ['null' => true, 'default' => null])
                ->addColumn('line_items', 'json', ['default' => '{}'])
                ->addTimestamps()
                ->addForeignKey('payment_id', 'Payments', 'id')
                ->addForeignKey('payment_flow_id', 'PaymentFlows', 'id')
                ->addIndex(['payment_id'], ['unique' => true])
                ->addIndex(['payment_flow_id'], ['unique' => true])
                ->create();
        }
    }
}
