<?php

namespace App\EntryPoint\Command;

use App\Core\Search\Driver\Elasticsearch\ElasticsearchIndexCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PruneElasticsearchIndexesCommand extends Command
{
    public function __construct(private ElasticsearchIndexCleaner $indexCleaner, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('search:prune-elasticsearch-indexes')
            ->setDescription('Prunes Elasticsearch search indexes for orphaned accounts')
            ->addOption(
                'save',
                null,
                InputOption::VALUE_NONE,
                'Should the changes be applied?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $save = $input->getOption('save');

        $this->indexCleaner->deleteOrphaned(!$save, $output);

        return 0;
    }
}
