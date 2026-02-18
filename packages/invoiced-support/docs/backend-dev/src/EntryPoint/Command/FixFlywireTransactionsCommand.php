<?php

namespace App\EntryPoint\Command;

use App\Core\Database\DatabaseHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixFlywireTransactionsCommand extends Command
{
    public function __construct(private readonly Connection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:flywire_transactions')
            ->setDescription('Transactions with method Flywire to proper values.')
            ->addArgument(
                'chunk',
                InputArgument::OPTIONAL,
                'chunk size defaults to 1000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $chunkSize = $input->getArgument('chunk') ?? 1000;
        $methods = ['bank_transfer', 'direct_debit', 'online'];
        foreach ($methods as $method) {
            DatabaseHelper::bigUpdate($this->database,
                'Transactions a',
                "a.method = '$method'",
                "a.method='flywire'",
                $chunkSize,
                'a.id',
                "FlywirePayments ON FlywirePayments.ar_payment_id=a.payment_id AND FlywirePayments.payment_method_type='$method'");
        }
        DatabaseHelper::bigUpdate($this->database, 'Transactions', "method = 'credit_card'", "method = 'flywire'", $chunkSize);

        return 0;
    }
}
