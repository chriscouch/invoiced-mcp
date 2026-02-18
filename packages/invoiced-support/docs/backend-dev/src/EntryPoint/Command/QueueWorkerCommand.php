<?php

namespace App\EntryPoint\Command;

use App\Core\Queue\ProxyResqueJob;
use App\Core\Queue\Resque;
use App\Core\Queue\ResqueInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class QueueWorkerCommand extends Command
{
    public function __construct(
        private readonly ResqueInitializer $resqueInitializer,
        ServiceLocator $resqueJobLocator,
        private readonly Resque $resque)
    {
        parent::__construct();
        ProxyResqueJob::setJobLocator($resqueJobLocator);
    }

    protected function configure(): void
    {
        $this
            ->setName('queue:work')
            ->setDescription('Starts a queue worker daemon')
            ->addOption(
                'queue',
                null,
                InputOption::VALUE_REQUIRED,
                'Queues to listen to',
                '*'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->resqueInitializer->initialize();
        $this->resque->startWorker($input->getOption('queue'));

        return 0;
    }
}
