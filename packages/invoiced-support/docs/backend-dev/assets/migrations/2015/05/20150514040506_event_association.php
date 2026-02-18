<?php

use Phinx\Migration\AbstractMigration;

final class EventAssociation extends AbstractMigration
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
        $this->table('EventAssociations', ['id' => false, 'primary_key' => ['event', 'object', 'object_id']])
            ->addColumn('event', 'integer')
            ->addColumn('object', 'enum', ['values' => self::$objectTypes])
            ->addColumn('object_id', 'string')
            ->addForeignKey('event', 'Events', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('object')
            ->addIndex('object_id')
            ->create();
    }
}
