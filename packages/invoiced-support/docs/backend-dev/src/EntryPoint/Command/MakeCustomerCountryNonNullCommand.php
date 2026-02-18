<?php

namespace App\EntryPoint\Command;

use App\Core\Database\DatabaseHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCustomerCountryNonNullCommand extends Command
{
    public function __construct(private readonly Connection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:make_customer_country_non_null')
            ->setDescription('Converts customer countries to non null.')
            ->addArgument(
                'chunk',
                InputArgument::OPTIONAL,
                'chunk size defaults to 1000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $chunkSize = $input->getArgument('chunk') ?? 1000;


        $companies = $this->database->fetchAllAssociative("SELECT id, country FROM Companies");
        foreach ($companies as $company) {
            DatabaseHelper::bigUpdate($this->database, 'Customers', "country = '{$company['country']}'", "(country IS NULL OR country = '') AND tenant_id = '{$company['id']}'", $chunkSize);
        }

        return 0;
    }
}
