<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Inbox extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Inboxes');
        $this->addTenant($table);
        $table->addColumn('external_id', 'string', ['length' => 10])
            ->addTimestamps()
            ->addIndex('external_id', ['unique' => true])
            ->create();

        $table = $this->table('EmailThreads');
        $this->addTenant($table);
        $table->addColumn('inbox_id', 'integer')
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('assignee_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('related_to_type', 'integer', ['null' => true, 'default' => null])
            ->addColumn('related_to_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('status', 'enum', ['default' => 'open', 'values' => ['open', 'pending', 'closed']])
            ->addColumn('close_date', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex(['tenant_id', 'inbox_id', 'related_to_type', 'related_to_id'])
            ->addForeignKey('inbox_id', 'Inboxes', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('assignee_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('InboxEmails');
        $this->addTenant($table);
        $table->addColumn('thread_id', 'integer')
            ->addColumn('reply_to_email_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('message_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('tracking_id', 'string', ['length' => 32, 'null' => true, 'default' => null])
            ->addColumn('subject', 'string', ['default' => ''])
            ->addColumn('date', 'integer')
            ->addColumn('incoming', 'boolean', ['default' => true])
            ->addColumn('opens', 'integer')
            ->addTimestamps()
            ->addIndex(['message_id'])
            ->addIndex(['tracking_id'])
            ->addForeignKey('thread_id', 'EmailThreads', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('reply_to_email_id', 'InboxEmails', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('EmailParticipants');
        $this->addTenant($table);
        $table->addColumn('email_address', 'string')
            ->addColumn('name', 'string', ['default' => ''])
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['email_address'])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('EmailParticipantAssociations', ['id' => false, 'primary_key' => ['email_id', 'participant_id', 'type']]);
        $table->addColumn('participant_id', 'integer')
            ->addColumn('email_id', 'integer')
            ->addColumn('type', 'enum', ['values' => ['to', 'from', 'cc', 'bcc']])
            ->create();

        $this->table('Attachments')
            ->changeColumn('parent_type', 'enum', ['values' => ['comment', 'credit_note', 'estimate', 'invoice', 'unapplied_payment', 'email', 'customer']])
            ->update();
    }
}
