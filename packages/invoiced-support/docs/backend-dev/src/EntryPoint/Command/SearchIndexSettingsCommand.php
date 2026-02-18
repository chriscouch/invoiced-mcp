<?php

namespace App\EntryPoint\Command;

use App\Core\Search\Driver\Elasticsearch\ElasticsearchDriver;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SearchIndexSettingsCommand extends Command
{
    public function __construct(private ElasticsearchDriver $elasticsearchDriver, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('search:index-settings')
            ->setDescription('Updates the search index settings')
            ->addArgument(
                'driver',
                InputArgument::REQUIRED,
                'Search backend to use: elasticsearch'
            )
            ->addArgument(
                'index',
                InputArgument::REQUIRED,
                'Specific index (i.e. `invoice` or `customer`).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $driver = $input->getArgument('driver');
        $indexes = explode(',', $input->getArgument('index'));

        foreach ($indexes as $indexName) {
            $output->writeln('Updating index settings for "'.$indexName.'" index in '.$driver.' driver...');

            if ('elasticsearch' == $driver) {
                $this->elasticsearchDriver->updateSettings($indexName, $output);
            } else {
                throw new InvalidArgumentException('Invalid driver: '.$driver);
            }
        }

        return 0;
    }
}
