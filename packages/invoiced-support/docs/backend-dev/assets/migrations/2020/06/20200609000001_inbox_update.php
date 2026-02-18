<?php

use App\Core\Multitenant\MultitenantModelMigration;
use Phinx\Db\Adapter\AdapterInterface;

final class InboxUpdate extends MultitenantModelMigration
{
    public function change()
    {
        $emailThreads = $this->table('EmailThreads', ['id' => false, 'primary_key' => 'id']);
        $inboxEmails = $this->table('InboxEmails', ['id' => false, 'primary_key' => 'id']);
        $emailParticipants = $this->table('EmailParticipants', ['id' => false, 'primary_key' => 'id']);
        $emailParticipantAssociations = $this->table('EmailParticipantAssociations');

        $inboxEmails
            ->dropForeignKey('thread_id')
            ->dropForeignKey('reply_to_email_id')
            ->update();

        $emailThreads->changeColumn('id', AdapterInterface::PHINX_TYPE_BIG_INTEGER, ['identity' => true])
            ->update();

        $inboxEmails->changeColumn('id', AdapterInterface::PHINX_TYPE_BIG_INTEGER, ['identity' => true])
            ->changeColumn('thread_id', AdapterInterface::PHINX_TYPE_BIG_INTEGER)
            ->changeColumn('reply_to_email_id', AdapterInterface::PHINX_TYPE_BIG_INTEGER, ['null' => true, 'default' => null])
            ->update();

        $this->disableForeignKeyChecks();
        $inboxEmails
            ->addForeignKey('thread_id', 'EmailThreads', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('reply_to_email_id', 'InboxEmails', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
        $this->enableForeignKeyChecks();

        $emailParticipants->changeColumn('id', AdapterInterface::PHINX_TYPE_BIG_INTEGER, ['identity' => true])
            ->update();

        $emailParticipantAssociations->changeColumn('participant_id', AdapterInterface::PHINX_TYPE_BIG_INTEGER)
            ->changeColumn('email_id', AdapterInterface::PHINX_TYPE_BIG_INTEGER)
            ->update();
    }
}
