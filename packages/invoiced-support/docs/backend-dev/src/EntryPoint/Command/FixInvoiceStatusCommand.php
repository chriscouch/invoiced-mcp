<?php

namespace App\EntryPoint\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixInvoiceStatusCommand extends Command
{
    public function __construct(private readonly Connection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:invoice_status')
            ->setDescription('Fixes voided status for invoices.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->database->executeQuery('UPDATE Invoices SET status = ? WHERE voided = ?', ['voided', 1]);

        return 0;
    }
}
