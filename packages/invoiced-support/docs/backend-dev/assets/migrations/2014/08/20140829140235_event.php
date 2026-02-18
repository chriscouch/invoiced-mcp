<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Event extends MultitenantModelMigration
{
    protected static $objectTypes = [
        'comment',
        'credit_note',
        'customer',
        'document_view',
        'email',
        'estimate',
        'import',
        'invoice',
        'line_item',
        'subscription',
        'transaction',
    ];

    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('Events')) {
            return;
        }

        $table = $this->table('Events');
        $table->addColumn('company', 'integer')
            ->addColumn('tenant_id', 'integer')
            ->addColumn('type', 'string', ['length' => 50])
            ->addColumn('timestamp', 'integer')
            ->addColumn('object_type', 'enum', ['values' => self::$objectTypes])
            ->addColumn('object_id', 'string', ['length' => 32])
            ->addColumn('user_id', 'integer')
            ->addColumn('object', 'text', ['null' => true, 'default' => null])
            ->addColumn('previous', 'text', ['null' => true, 'default' => null])
            ->addForeignKey('company', 'Companies', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('type')
            ->addIndex('timestamp')
            ->addIndex('object_type')
            ->addIndex('object_id')
            ->addIndex('user_id')
            ->create();
    }
}
