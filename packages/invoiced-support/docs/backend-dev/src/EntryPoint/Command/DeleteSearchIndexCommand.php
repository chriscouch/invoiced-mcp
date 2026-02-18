<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Search\Libs\IndexRegistry;
use App\Core\Search\Libs\Search;
use App\Core\Utils\Enums\ObjectType;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteSearchIndexCommand extends Command
{
    public function __construct(private IndexRegistry $indexRegistry, private Search $search, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('search:delete-index')
            ->setDescription('Deletes a search index')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to rebuild index for'
            )
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'Specific index (i.e. `invoice` or `customer`) or `all`.'
            )
            ->addOption(
                'driver',
                null,
                InputOption::VALUE_OPTIONAL,
                'Search backend to use: database, elasticsearch'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');
        $indexName = $input->getArgument('index');
        $driver = $input->getOption('driver');

        $company = Company::find($id);
        if (!$company) {
            $output->writeln("Company # $id not found");

            return 1;
        }

        if ('all' == $indexName) {
            $objects = $this->indexRegistry->getIndexableObjectsForCompany($company);
        } else {
            $modelClass = ObjectType::fromTypeName($indexName)->modelClass();
            $objects = [$modelClass];
        }

        foreach ($objects as $modelClass) {
            $index = $this->search->getIndex($company, $modelClass, $driver);
            $output->writeln("Deleting {$index->getName()}");
            $index->delete();
        }

        return 0;
    }
}
