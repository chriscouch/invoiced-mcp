<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PromiseToPayEvents extends MultitenantModelMigration
{
    /**
     * Ordering is VERY IMPORTANT. When adding
     * new values to the ENUM they must go at the
     * end in order to prevent rewriting the entire table.
     */
    protected static array $objectTypes = [
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
        'contact',
        'note',
        'task',
        'letter',
        'text_message',
        'bank_account',
        'card',
        'payment_plan',
        'payment',
        'refund',
        'charge',
        'promise_to_pay',
    ];

    public function change()
    {
        $this->table('Events')
            ->changeColumn('object_type', 'enum', ['values' => self::$objectTypes])
            ->update();

        $this->table('EventAssociations')
            ->changeColumn('object', 'enum', ['values' => self::$objectTypes])
            ->update();
    }
}
