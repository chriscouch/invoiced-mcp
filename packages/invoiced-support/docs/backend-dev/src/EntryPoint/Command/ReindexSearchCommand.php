<?php

namespace App\EntryPoint\Command;

use App\Companies\Models\Company;
use App\Core\Search\Libs\Reindexer;
use App\Core\Search\Libs\Strategy\InPlace;
use App\Core\Search\Libs\Strategy\Rebuild;
use ICanBoogie\Inflector;
use App\Core\Orm\Iterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReindexSearchCommand extends Command
{
    public function __construct(private Reindexer $reindexer, private InPlace $inPlaceStrategy, private Rebuild $rebuildStrategy, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('search:reindex')
            ->setDescription('Rebuilds the search index')
            ->addArgument(
                'company',
                InputArgument::REQUIRED,
                'Company ID to rebuild index for'
            )
            ->addOption(
                'save',
                null,
                InputOption::VALUE_NONE,
                'Should the changes be applied?'
            )
            ->addOption(
                'strategy',
                null,
                InputOption::VALUE_OPTIONAL,
                'Synchronization strategy',
                'InPlace'
            )
            ->addOption(
                'driver',
                null,
                InputOption::VALUE_OPTIONAL,
                'Search backend to use: database, elasticsearch'
            )
            ->addOption(
                'index',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specific index (i.e. `invoice` or `customer`) or `all` (default).',
                'all',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('company');
        $save = $input->getOption('save');
        $strategyName = Inflector::get()->camelize($input->getOption('strategy'));
        $driver = $input->getOption('driver');
        $indexName = $input->getOption('index');

        // build the update strategy we want to use
        $strategy = $this->inPlaceStrategy;
        $strategy->disableObjectLimit();
        if ('Rebuild' == $strategyName) {
            $strategy = $this->rebuildStrategy;
        }

        if ('all' === $id) {
            foreach ($this->getCompanies() as $company) {
                $this->reindexer->run($company, $strategy, !$save, $output, $driver, $indexName);
            }
        } else {
            $company = Company::find($id);
            if (!$company) {
                $output->writeln("Company # $id not found");

                return 1;
            }

            $this->reindexer->run($company, $strategy, !$save, $output, $driver, $indexName);
        }

        return 0;
    }

    /**
     * Gets a batch of companies to reindex.
     *
     * @return Iterator<Company>
     */
    private function getCompanies(): Iterator
    {
        return Company::where('canceled', false)
            ->sort('search_last_reindexed ASC,id ASC')
            ->all();
    }
}
