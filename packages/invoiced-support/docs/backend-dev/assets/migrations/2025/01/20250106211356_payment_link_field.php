<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentLinkField extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('PaymentLinkFields');
        $this->addTenant($table);
        $table->addColumn('payment_link_id', 'integer')
            ->addColumn('object_type', 'smallinteger')
            ->addColumn('custom_field_id', 'string')
            ->addColumn('required', 'boolean')
            ->addColumn('order', 'integer')
            ->addTimestamps()
            ->addForeignKey('payment_link_id', 'PaymentLinks', 'id')
            ->addIndex(['payment_link_id', 'object_type', 'custom_field_id'], ['unique' => true])
            ->create();
    }
}
