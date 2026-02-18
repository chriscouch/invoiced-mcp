<?php

namespace App\EntryPoint\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateEmailParticipantsCommand extends Command
{
    public function __construct(private Connection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('seed:email-participants')
            ->setDescription('Populates EmailParticipants table from Contacts and Customers tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->database->executeStatement('SET innodb_lock_wait_timeout = 7200;');

        $this->database->executeStatement('
            INSERT INTO EmailParticipants (tenant_id, email_address, name)
                SELECT tenant_id, email, name FROM Customers WHERE email IS NOT NULL ON DUPLICATE KEY UPDATE name = values(name);');
        $this->database->executeStatement('
            INSERT INTO EmailParticipants (tenant_id, email_address, name)
                SELECT tenant_id, email, name FROM Contacts WHERE email IS NOT NULL ON DUPLICATE KEY UPDATE name = values(name);');

        return 0;
    }
}
